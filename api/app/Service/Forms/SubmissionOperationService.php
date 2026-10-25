<?php

namespace App\Service\Forms;

use App\Events\Forms\FormSubmitted;
use App\Models\Forms\SubmissionOperation;
use Closure;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Throwable;

class SubmissionOperationService
{
    public function fingerprint(array $data): string
    {
        $normalize = function ($value) use (&$normalize) {
            if ($value instanceof UploadedFile) {
                // Partial HTTP submissions can contain an UploadedFile, whose
                // testing/stream properties are not JSON serializable. Bind the
                // fingerprint to bytes and public file metadata, never its temp path.
                return ['uploaded_file' => [
                    'name' => $value->getClientOriginalName(), 'mime' => $value->getClientMimeType(),
                    'size' => $value->getSize(), 'sha256' => hash_file('sha256', $value->getPathname()),
                ]];
            }
            if (! is_array($value)) {
                return $value;
            }
            if (! array_is_list($value)) {
                ksort($value);
            }

            return array_map($normalize, $value);
        };

        return hash('sha256', json_encode($normalize($data), JSON_THROW_ON_ERROR));
    }

    public function execute(int $formId, string $key, string $hash, Closure $process): SubmissionOperation
    {
        $operation = DB::transaction(function () use ($formId, $key, $hash, $process) {
            DB::table('submission_operations')->insertOrIgnore([
                'form_id' => $formId, 'operation_key' => $key, 'payload_hash' => $hash,
                'outbox_state' => 'none', 'created_at' => now(), 'updated_at' => now(),
            ]);
            $operation = SubmissionOperation::where('form_id', $formId)
                ->where('operation_key', $key)->lockForUpdate()->firstOrFail();
            if (! hash_equals($operation->payload_hash, $hash)) {
                throw new DomainException('Submission operation key was reused with a different payload');
            }
            if ($operation->completed_at) {
                return $operation;
            }
            // Includes file processing and the existing submission transaction.
            // A replay cannot perform those side effects before acquiring this row.
            $result = $process();
            $operation->fill([
                'submission_id' => $result['submission_id'], 'processed_data' => $result['data'],
                'submission_meta' => $result['meta'], 'temporary_paths' => array_values(array_unique($result['temporary_paths'])),
                'completed_at' => now(), 'outbox_state' => $result['partial'] ? 'none' : 'pending',
            ])->save();

            return $operation;
        });
        DB::afterCommit(function () use ($operation) {
            $this->cleanupTemporaryFiles($operation->id);
            $this->dispatchOutbox($operation->id);
        });

        return $operation;
    }

    public function dispatchOutbox(int $id): bool
    {
        $token = (string) Str::uuid();
        $claimed = SubmissionOperation::whereKey($id)->whereNotNull('completed_at')
            ->where('outbox_state', 'pending')->update([
                'outbox_state' => 'dispatching', 'claim_token' => $token, 'updated_at' => now(),
            ]);
        if (! $claimed) {
            return false;
        }
        try {
            $operation = SubmissionOperation::with('form')->find($id);
            if (! $operation?->form) {
                return false;
            }
            FormSubmitted::dispatch($operation->form, $operation->processed_data, $operation->submission_meta ?? [], $id);
            SubmissionOperation::whereKey($id)->where('claim_token', $token)->where('outbox_state', 'dispatching')
                ->update(['outbox_state' => 'dispatched', 'updated_at' => now()]);
        } catch (Throwable $exception) {
            SubmissionOperation::whereKey($id)->where('claim_token', $token)->where('outbox_state', 'dispatching')
                ->update(['outbox_state' => 'ambiguous', 'updated_at' => now()]);
            Log::error('Submission outbox dispatch outcome is ambiguous', [
                'operation_id' => $id, 'exception_type' => get_class($exception),
            ]);
        }

        return true;
    }

    public function invokeEffect(int $operationId, int $integrationId, Closure $invoke): bool
    {
        $token = (string) Str::uuid();
        $claimed = DB::transaction(function () use ($operationId, $integrationId, $token) {
            $operation = SubmissionOperation::whereKey($operationId)->whereNotNull('completed_at')->first();
            if (! $operation || $operation->outbox_state === 'none'
                || ! DB::table('form_integrations')->where('id', $integrationId)->where('form_id', $operation->form_id)
                    ->where('status', 'active')->whereNull('deleted_at')->exists()) {
                return false;
            }
            DB::table('submission_effects')->insertOrIgnore([
                'operation_id' => $operationId, 'integration_id' => $integrationId,
                'state' => 'pending', 'created_at' => now(), 'updated_at' => now(),
            ]);

            return DB::table('submission_effects')->where('operation_id', $operationId)
                ->where('integration_id', $integrationId)->where('state', 'pending')
                ->update(['state' => 'invoking', 'claim_token' => $token, 'updated_at' => now()]) === 1;
        });
        if (! $claimed) {
            return false;
        }
        try {
            $invoke();
            // Existing handlers may catch provider errors. "invoked" means the
            // handler returned; existing integration/email events record delivery.
            $state = 'invoked';
            $exceptionType = null;
        } catch (Throwable $exception) {
            $state = 'ambiguous';
            $exceptionType = get_class($exception);
        }
        DB::table('submission_effects')->where('operation_id', $operationId)
            ->where('integration_id', $integrationId)->where('claim_token', $token)->where('state', 'invoking')
            ->update(['state' => $state, 'exception_type' => $exceptionType, 'updated_at' => now()]);

        return true;
    }

    public function cleanupTemporaryFiles(int $id): void
    {
        $operation = SubmissionOperation::find($id);
        if (! $operation?->completed_at || ! $operation->temporary_paths) {
            return;
        }
        $remaining = [];
        foreach ($operation->temporary_paths as $path) {
            try {
                if (! preg_match('~^tmp/[0-9a-f-]{36}$~i', $path) || ! Storage::delete($path)) {
                    $remaining[] = $path;
                }
            } catch (Throwable $exception) {
                $remaining[] = $path;
                Log::warning('Submission temporary-file cleanup deferred', ['operation_id' => $id, 'exception_type' => get_class($exception)]);
            }
        }
        $operation->forceFill(['temporary_paths' => $remaining, 'updated_at' => now()])->save();
    }
}

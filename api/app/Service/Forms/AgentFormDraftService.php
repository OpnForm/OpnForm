<?php

namespace App\Service\Forms;

use App\Models\Forms\AgentFormDraft;
use App\Models\Forms\AgentFormDraftHandoff;
use App\Models\Forms\Form;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;

class AgentFormDraftService
{
    public const EXPIRY_DAYS = 7;

    public const PREVIEW_URL_TTL_MINUTES = 60;

    public function __construct(
        private readonly AgentFormDefinition $formDefinition,
        private readonly AgentFormDraftPatcher $patcher,
        private readonly FormCreationService $formCreation,
    ) {
    }

    /**
     * @return array{draft: AgentFormDraft, token: string}
     */
    public function create(array $definition): array
    {
        $definition['visibility'] = 'draft';
        $definition = $this->formDefinition->normalizeAndValidate($definition);
        $token = $this->generateToken();

        $draft = AgentFormDraft::query()->create([
            'token_hash' => $this->hashToken($token),
            'definition' => $definition,
            'schema_version' => AgentFormDefinition::SCHEMA_VERSION,
            'version' => 1,
            'status' => AgentFormDraft::STATUS_ACTIVE,
            'expires_at' => now()->addDays(self::EXPIRY_DAYS),
        ]);

        return ['draft' => $draft, 'token' => $token];
    }

    public function get(string $token): AgentFormDraft
    {
        return $this->resolveActive($token);
    }

    public function patch(string $token, int $expectedVersion, array $operations): AgentFormDraft
    {
        return DB::transaction(function () use ($token, $expectedVersion, $operations) {
            $draft = $this->resolveActive($token, lock: true);

            if ($draft->version !== $expectedVersion) {
                throw ValidationException::withMessages([
                    'expected_version' => ["Draft version conflict. Current version is {$draft->version}. Fetch the draft and retry."],
                ]);
            }

            $definition = $this->patcher->apply($draft->definition, $operations);
            $definition['visibility'] = 'draft';
            $definition = $this->formDefinition->normalizeAndValidate($definition);

            $draft->forceFill([
                'definition' => $definition,
                'schema_version' => AgentFormDefinition::SCHEMA_VERSION,
                'version' => $draft->version + 1,
            ])->save();

            return $draft->refresh();
        });
    }

    public function serialize(AgentFormDraft $draft): array
    {
        return [
            'version' => $draft->version,
            'schema_version' => $draft->schema_version,
            'status' => $draft->status,
            'expires_at' => $draft->expires_at->toIso8601String(),
            'definition' => $draft->definition,
        ];
    }

    /**
     * @return array{handoff_token: string, editor_url: string, expires_at: string}
     */
    public function issueEditorHandoff(string $draftToken): array
    {
        return DB::transaction(function () use ($draftToken) {
            $draft = $this->resolveActive($draftToken, lock: true);
            $handoffToken = $this->generateToken();
            $expiresAt = $draft->expires_at->copy();

            $draft->editorHandoffs()->create([
                'token_hash' => $this->hashToken($handoffToken),
                'expires_at' => $expiresAt,
            ]);

            return [
                'handoff_token' => $handoffToken,
                'editor_url' => front_url('/agent-drafts/edit#handoff='.$handoffToken),
                'expires_at' => $expiresAt->toIso8601String(),
            ];
        });
    }

    /**
     * @return array{draft: AgentFormDraft, editor_session: string}
     */
    public function consumeEditorHandoff(string $handoffToken): array
    {
        return DB::transaction(function () use ($handoffToken) {
            $this->assertTokenShape($handoffToken, 'handoff_token');

            $handoff = AgentFormDraftHandoff::query()
                ->where('token_hash', $this->hashToken($handoffToken))
                ->where('expires_at', '>', now())
                ->lockForUpdate()
                ->first();

            if (! $handoff) {
                throw ValidationException::withMessages([
                    'handoff_token' => ['This editor link is invalid or expired.'],
                ]);
            }

            $draft = AgentFormDraft::query()
                ->whereKey($handoff->agent_form_draft_id)
                ->active()
                ->lockForUpdate()
                ->first();

            if (! $draft) {
                throw ValidationException::withMessages([
                    'handoff_token' => ['This editor link is invalid or expired.'],
                ]);
            }

            $editorSession = $this->editorSessionToken($draft);
            $handoff->forceFill(['last_used_at' => now()])->save();
            $draft->forceFill([
                'editor_session_hash' => $this->hashToken($editorSession),
                'editor_session_expires_at' => $draft->expires_at,
            ])->save();

            return ['draft' => $draft->refresh(), 'editor_session' => $editorSession];
        });
    }

    public function getForEditor(string $editorSession): AgentFormDraft
    {
        return $this->resolveEditorSession($editorSession);
    }

    public function replaceFromEditor(string $editorSession, int $expectedVersion, array $definition): AgentFormDraft
    {
        return DB::transaction(function () use ($editorSession, $expectedVersion, $definition) {
            $draft = $this->resolveEditorSession($editorSession, lock: true);
            $this->assertActiveVersion($draft, $expectedVersion);

            $definition['visibility'] = 'draft';
            $definition = $this->formDefinition->normalizeAndValidate($definition);
            $draft->forceFill([
                'definition' => $definition,
                'schema_version' => AgentFormDefinition::SCHEMA_VERSION,
                'version' => $draft->version + 1,
            ])->save();

            return $draft->refresh();
        });
    }

    /**
     * @return array{form: Form, cleanings: array, already_claimed: bool}
     */
    public function claim(string $editorSession, int $expectedVersion, Workspace $workspace, User $user): array
    {
        return DB::transaction(function () use ($editorSession, $expectedVersion, $workspace, $user) {
            $draft = $this->resolveEditorSession($editorSession, lock: true, activeOnly: false);

            if ($draft->status === AgentFormDraft::STATUS_CLAIMED && $draft->claimed_form_id) {
                $form = Form::query()->findOrFail($draft->claimed_form_id);
                Gate::forUser($user)->authorize('view', $form);

                return ['form' => $form, 'cleanings' => [], 'already_claimed' => true];
            }

            $this->assertActiveVersion($draft, $expectedVersion);
            Gate::forUser($user)->authorize('ownsWorkspace', $workspace);
            Gate::forUser($user)->authorize('create', [Form::class, $workspace]);

            $definition = $draft->definition;
            $definition['visibility'] = 'draft';
            $definition = $this->formDefinition->normalizeAndValidate($definition, $workspace);
            $created = $this->formCreation->create($definition, $user, $workspace);

            $draft->forceFill([
                'status' => AgentFormDraft::STATUS_CLAIMED,
                'claimed_form_id' => $created['form']->id,
                'claimed_at' => now(),
            ])->save();

            return [
                'form' => $created['form'],
                'cleanings' => $created['cleanings'],
                'already_claimed' => false,
            ];
        });
    }

    public function previewUrl(AgentFormDraft $draft): string
    {
        $sourceUrl = URL::temporarySignedRoute(
            'agent-drafts.preview',
            now()->addMinutes(self::PREVIEW_URL_TTL_MINUTES),
            ['draft' => $draft->id],
        );

        return front_url('/agent-drafts/preview?source='.rawurlencode($sourceUrl));
    }

    private function resolveActive(string $token, bool $lock = false): AgentFormDraft
    {
        $this->assertTokenShape($token, 'draft_token');

        $query = AgentFormDraft::query()
            ->where('token_hash', $this->hashToken($token))
            ->active();

        if ($lock) {
            $query->lockForUpdate();
        }

        $draft = $query->first();

        if (! $draft) {
            throw $this->unavailable();
        }

        return $draft;
    }

    private function resolveEditorSession(string $token, bool $lock = false, bool $activeOnly = true): AgentFormDraft
    {
        $this->assertTokenShape($token, 'editor_session');
        $query = AgentFormDraft::query()
            ->where('editor_session_hash', $this->hashToken($token))
            ->where('editor_session_expires_at', '>', now())
            ->where('expires_at', '>', now());

        if ($activeOnly) {
            $query->where('status', AgentFormDraft::STATUS_ACTIVE);
        }
        if ($lock) {
            $query->lockForUpdate();
        }

        $draft = $query->first();
        if (! $draft) {
            throw ValidationException::withMessages([
                'editor_session' => ['Editor session not found or expired.'],
            ]);
        }

        return $draft;
    }

    private function assertActiveVersion(AgentFormDraft $draft, int $expectedVersion): void
    {
        if (! $draft->isAvailable()) {
            throw $this->unavailable();
        }
        if ($draft->version !== $expectedVersion) {
            throw ValidationException::withMessages([
                'expected_version' => ["Draft version conflict. Current version is {$draft->version}. Fetch the draft and retry."],
            ]);
        }
    }

    private function assertTokenShape(string $token, string $field): void
    {
        if (! preg_match('/^[A-Za-z0-9_-]{43}$/', $token)) {
            throw ValidationException::withMessages([
                $field => ['Invalid or unavailable capability token.'],
            ]);
        }
    }

    private function generateToken(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }

    private function editorSessionToken(AgentFormDraft $draft): string
    {
        $token = hash_hmac(
            'sha256',
            'agent-form-draft-editor:'.$draft->getKey(),
            (string) config('app.key'),
            true,
        );

        return rtrim(strtr(base64_encode($token), '+/', '-_'), '=');
    }

    private function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    private function unavailable(): ValidationException
    {
        return ValidationException::withMessages([
            'draft_token' => ['Draft not found, expired, or already claimed.'],
        ]);
    }
}

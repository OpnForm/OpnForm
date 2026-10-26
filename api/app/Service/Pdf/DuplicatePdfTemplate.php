<?php

namespace App\Service\Pdf;

use App\Models\Forms\Form;
use App\Models\PdfTemplate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class DuplicatePdfTemplate
{
    private const SPECIAL_FIELDS = ['submission_id', 'submission_date', 'form_name'];

    public function duplicate(PdfTemplate $source, Form $target, array $data): PdfTemplate
    {
        $zones = $source->zone_mappings ?? [];
        if ($source->form_id !== $target->id) {
            $zones = $this->mapFields($zones, $target, $data['field_mappings'] ?? []);
        }

        abort_unless(Storage::exists($source->file_path), 404, 'PDF template file not found.');
        $filename = Str::uuid() . '.pdf';
        $path = "pdf-templates/{$target->id}/{$filename}";

        try {
            if (!Storage::copy($source->file_path, $path)) {
                throw new \RuntimeException('Failed to copy PDF template file.');
            }

            return DB::transaction(function () use ($source, $target, $data, $zones, $filename, $path) {
                $copy = $source->replicate();
                $copy->fill([
                    'form_id' => $target->id,
                    'name' => $data['name'],
                    'filename' => $filename,
                    'file_path' => $path,
                    'zone_mappings' => $zones,
                ]);
                $copy->save();

                return $copy;
            });
        } catch (\Throwable $exception) {
            Storage::delete($path);
            throw $exception;
        }
    }

    private function mapFields(array $zones, Form $target, array $mappings): array
    {
        $fields = collect($target->properties ?? [])
            ->reject(fn ($field) => str_starts_with($field['type'] ?? '', 'nf-'))
            ->concat($target->computed_variables ?? [])
            ->keyBy('id');

        return collect($zones)->map(function ($zone) use ($fields, $mappings) {
            $fieldId = $zone['field_id'] ?? null;
            if (array_key_exists('static_text', $zone) || array_key_exists('static_image', $zone)
                || !$fieldId || in_array($fieldId, self::SPECIAL_FIELDS, true)) {
                return $zone;
            }

            // Missing mappings must never silently discard a customer's layout.
            if (!array_key_exists($fieldId, $mappings)) {
                throw ValidationException::withMessages([
                    'field_mappings' => 'Choose a destination or explicitly skip each mapped field.',
                ]);
            }
            $destination = $mappings[$fieldId];
            if ($destination === null) {
                return null;
            }
            if (!$fields->has($destination)) {
                throw ValidationException::withMessages([
                    'field_mappings' => 'Mapped fields must belong to the destination form.',
                ]);
            }

            $zone['field_id'] = $destination;
            $zone['field_name'] = $fields[$destination]['name'];

            return $zone;
        })->filter(fn ($zone) => $zone !== null)->values()->all();
    }
}

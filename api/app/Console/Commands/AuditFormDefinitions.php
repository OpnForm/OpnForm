<?php

namespace App\Console\Commands;

use App\Models\Forms\Form;
use App\Service\Forms\FormDataNormalizer;
use App\Service\Forms\FormStructureValidator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class AuditFormDefinitions extends Command
{
    protected $signature = 'forms:audit-definitions
        {--limit= : Maximum number of forms to inspect}
        {--with-trashed : Include soft-deleted forms}
        {--json : Emit the complete report as JSON}';

    protected $description = 'Audit persisted form fields, computed variables and logic without modifying data';

    public function __construct(
        private readonly FormDataNormalizer $formDataNormalizer,
        private readonly FormStructureValidator $formStructureValidator,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $report = DB::transaction(function (): array {
            $readOnly = $this->enableAndVerifyReadOnlyTransaction();
            $limit = $this->validatedLimit();
            $query = Form::query()
                ->select(['id', 'workspace_id', 'slug', 'visibility', 'properties', 'computed_variables'])
                ->orderBy('id');

            if ($this->option('with-trashed')) {
                $query->withTrashed();
            }

            if ($limit !== null) {
                $query->limit($limit);
            }

            $forms = 0;
            $validForms = 0;
            $autoRepairedForms = 0;
            $invalidFormsCount = 0;
            $invalidForms = [];
            $issuesByCode = [];
            $includeInvalidFormDetails = (bool) $this->option('json');

            foreach ($query->cursor() as $form) {
                $forms++;
                $definition = [
                    'properties' => $form->properties ?? [],
                    'computed_variables' => $form->computed_variables ?? [],
                ];
                $normalizedDefinition = $this->formDataNormalizer->normalize($definition);
                if ($normalizedDefinition !== $definition) {
                    $autoRepairedForms++;
                }
                $workspace = collect($normalizedDefinition['properties'])
                    ->contains(fn ($property) => is_array($property) && ($property['type'] ?? null) === 'payment')
                        ? $form->workspace()->first()
                        : null;
                $issues = $this->formStructureValidator->issues($normalizedDefinition, $workspace);

                if ($issues === []) {
                    $validForms++;

                    continue;
                }

                foreach ($issues as $issue) {
                    $issuesByCode[$issue['code']] = ($issuesByCode[$issue['code']] ?? 0) + 1;
                }

                $invalidFormsCount++;
                if ($includeInvalidFormDetails) {
                    $invalidForms[] = [
                        'form_id' => $form->id,
                        'workspace_id' => $form->workspace_id,
                        'slug' => $form->slug,
                        'visibility' => $form->visibility,
                        'issues' => $issues,
                    ];
                }
            }

            ksort($issuesByCode);

            return [
                'read_only' => $readOnly,
                'forms_checked' => $forms,
                'valid_forms' => $validForms,
                'auto_repaired_forms' => $autoRepairedForms,
                'invalid_forms_count' => $invalidFormsCount,
                'issues_by_code' => $issuesByCode,
                'invalid_forms' => $invalidForms,
            ];
        });

        if ($this->option('json')) {
            $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

            return Command::SUCCESS;
        }

        $this->info('Read-only form definition audit completed.');
        $this->table(
            ['Read only', 'Checked', 'Valid', 'Auto-repaired', 'Invalid'],
            [[
                $report['read_only'],
                $report['forms_checked'],
                $report['valid_forms'],
                $report['auto_repaired_forms'],
                $report['invalid_forms_count'],
            ]],
        );

        if ($report['issues_by_code'] !== []) {
            $this->table(
                ['Issue code', 'Occurrences'],
                collect($report['issues_by_code'])->map(fn (int $count, string $code) => [$code, $count])->values()->all(),
            );
            $this->warn('Run again with --json to inspect the affected form IDs and exact paths.');
        }

        return Command::SUCCESS;
    }

    private function enableAndVerifyReadOnlyTransaction(): string
    {
        $connection = DB::connection();
        if ($connection->getDriverName() !== 'pgsql') {
            return 'query-only command (database does not support PostgreSQL transaction_read_only)';
        }

        $connection->statement('SET TRANSACTION READ ONLY');
        $state = $connection->selectOne('SHOW transaction_read_only');
        $readOnly = $state->transaction_read_only ?? null;

        if (! in_array($readOnly, ['on', true, 1, '1'], true)) {
            throw new RuntimeException('The database transaction could not be verified as read-only.');
        }

        return 'on';
    }

    private function validatedLimit(): ?int
    {
        $limit = $this->option('limit');
        if ($limit === null) {
            return null;
        }

        if (! ctype_digit((string) $limit) || (int) $limit < 1) {
            throw new RuntimeException('--limit must be a positive integer.');
        }

        return (int) $limit;
    }
}

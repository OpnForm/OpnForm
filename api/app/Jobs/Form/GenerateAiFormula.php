<?php

namespace App\Jobs\Form;

use App\Models\Forms\AI\AiFormCompletion;
use App\Service\AI\Prompts\Form\GenerateFormulaPrompt;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GenerateAiFormula implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(public AiFormCompletion $completion)
    {
    }

    public function handle(): void
    {
        $this->completion->update([
            'status' => AiFormCompletion::STATUS_PROCESSING,
        ]);

        try {
            $context = $this->completion->context ?? [];
            $fields = $context['fields'] ?? [];
            $computedVariables = $context['computed_variables'] ?? [];
            $currentFormula = $context['current_formula'] ?? null;
            $currentVariable = $context['current_variable'] ?? [];
            $currentVariableName = is_array($currentVariable) ? ($currentVariable['name'] ?? null) : null;

            $result = GenerateFormulaPrompt::run(
                $this->completion->form_prompt,
                $fields,
                $computedVariables,
                $currentFormula,
                $currentVariableName,
            );

            $this->completion->update([
                'status' => AiFormCompletion::STATUS_COMPLETED,
                'result' => json_encode($result),
            ]);
        } catch (\Exception $e) {
            $this->onError($e);
        }
    }

    public function failed(\Throwable $exception): void
    {
        $this->onError($exception);
    }

    private function onError(\Throwable $e): void
    {
        $this->completion->update([
            'status' => AiFormCompletion::STATUS_FAILED,
            'error' => $e->getMessage(),
        ]);
    }
}

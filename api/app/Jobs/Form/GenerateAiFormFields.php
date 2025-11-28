<?php

namespace App\Jobs\Form;

use App\Models\Forms\AI\AiFormCompletion;
use App\Service\AI\Prompts\Form\GenerateFormFieldsPrompt;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GenerateAiFormFields implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(public AiFormCompletion $completion)
    {
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $this->completion->update([
            'status' => AIFormCompletion::STATUS_PROCESSING,
        ]);

        try {
            // Extract form context from the completion
            $context = $this->completion->context ?? [];
            $formTitle = $context['title'] ?? '';
            $existingFields = $context['properties'] ?? [];

            // Use the static run method to execute the prompt with params
            $params = $this->completion->generation_params ?? [];
            $fieldsData = GenerateFormFieldsPrompt::run(
                $this->completion->form_prompt,
                $formTitle,
                $existingFields,
                $params
            );

            $this->completion->update([
                'status' => AIFormCompletion::STATUS_COMPLETED,
                'result' => $fieldsData
            ]);
        } catch (\Exception $e) {
            $this->onError($e);
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        $this->onError($exception);
    }

    private function onError(\Throwable $e)
    {
        $this->completion->update([
            'status' => AIFormCompletion::STATUS_FAILED,
            'error' => $e->getMessage(),
        ]);
    }
}

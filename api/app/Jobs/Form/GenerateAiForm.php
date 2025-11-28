<?php

namespace App\Jobs\Form;

use App\Models\Forms\AI\AiFormCompletion;
use App\Service\AI\Prompts\Form\GenerateFormPrompt;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GenerateAiForm implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    protected string $model = 'o4-mini';

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
            'status' => AiFormCompletion::STATUS_PROCESSING,
        ]);

        try {
            // Use the static run method to execute the prompt with params
            $params = $this->completion->generation_params ?? [];
            $formData = GenerateFormPrompt::run($this->completion->form_prompt, $params);

            $this->completion->update([
                'status' => AiFormCompletion::STATUS_COMPLETED,
                'result' => $formData
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
            'status' => AiFormCompletion::STATUS_FAILED,
            'error' => $e->getMessage(),
        ]);
    }
}

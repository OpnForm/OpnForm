<?php

namespace App\Http\Controllers\Forms;

use App\Http\Controllers\Controller;
use App\Http\Requests\AiGenerateFieldsRequest;
use App\Http\Requests\AiGenerateFormRequest;
use App\Http\Requests\AiGenerateFormulaRequest;
use App\Models\Forms\AI\AiFormCompletion;
use Illuminate\Http\Request;

class AiFormController extends Controller
{
    public function generateForm(AiGenerateFormRequest $request)
    {
        $this->middleware('throttle:4,1');

        return $this->success([
            'message' => 'We\'re working on your form, please wait ~1 min.',
            'ai_form_completion_id' => AiFormCompletion::create([
                'form_prompt' => $request->input('form_prompt'),
                'generation_params' => $request->input('generation_params', []),
                'ip' => $request->ip(),
            ])->id,
        ]);
    }

    public function show(AiFormCompletion $aiFormCompletion)
    {
        if ($aiFormCompletion->type === AiFormCompletion::TYPE_FORMULA) {
            abort(404);
        }

        if ($aiFormCompletion->ip != request()->ip()) {
            return $this->error('You are not authorized to view this AI completion.', 403);
        }

        return $this->showCompletion($aiFormCompletion);
    }

    public function showFormula(Request $request, AiFormCompletion $aiFormCompletion)
    {
        if (
            $aiFormCompletion->type !== AiFormCompletion::TYPE_FORMULA
            || $aiFormCompletion->user_id === null
            || (int) $aiFormCompletion->user_id !== (int) $request->user()->id
        ) {
            abort(404);
        }

        return $this->showCompletion($aiFormCompletion);
    }

    public function generateFields(AiGenerateFieldsRequest $request)
    {
        $this->middleware('throttle:4,1');

        return $this->success([
            'message' => 'Generating your fields, please wait...',
            'ai_form_completion_id' => AiFormCompletion::create([
                'type' => AiFormCompletion::TYPE_FIELDS,
                'form_prompt' => $request->input('fields_prompt'),
                'context' => $request->input('current_form_structure'),
                'generation_params' => $request->input('generation_params', []),
                'ip' => $request->ip(),
            ])->id,
        ]);
    }

    public function generateFormula(AiGenerateFormulaRequest $request)
    {
        return $this->success([
            'message' => 'Generating your formula, please wait...',
            'ai_form_completion_id' => AiFormCompletion::create([
                'type' => AiFormCompletion::TYPE_FORMULA,
                'form_prompt' => $request->input('formula_prompt'),
                'context' => $request->input('context', []),
                'user_id' => $request->user()->id,
                'ip' => $request->ip(),
            ])->id,
        ]);
    }

    private function showCompletion(AiFormCompletion $aiFormCompletion)
    {
        return $this->success([
            'message' => 'Your data is ready! Feel free to customize it to your needs before publishing.',
            'ai_form_completion' => $aiFormCompletion,
        ]);
    }
}

<?php

use App\Service\AI\Prompts\Form\GenerateFormFieldsPrompt;
use App\Service\AI\Prompts\Form\GenerateFormPrompt;
use App\Service\Forms\AgentFormAuthoringGuide;

uses(Tests\TestCase::class);

it('provides one shared authoring baseline to MCP resources and native prompts', function () {
    $reference = AgentFormAuthoringGuide::reference();
    $completePrompt = AgentFormAuthoringGuide::completeFormPrompt();
    $fieldsPrompt = AgentFormAuthoringGuide::fieldsPrompt();

    expect($reference)
        ->toHaveKeys(['objective', 'rules', 'validation'])
        ->and($reference['rules'])->toHaveKeys(['context', 'copy', 'fields', 'layout', 'completion', 'integrity'])
        ->and($completePrompt)->toContain(
            'human-facing labels in sentence case',
            'Use placeholders only for a useful example or expected format',
            'Use a contextual submit label',
            'Do not invent legal consent',
        )
        ->and($fieldsPrompt)->toContain('pair at most two related short fields on a row')
        ->and($fieldsPrompt)->not->toContain('Use a contextual submit label');
});

it('injects the shared authoring baseline into native form prompts', function () {
    $formPrompt = new class ('Create a contact form') extends GenerateFormPrompt {
        public function compiled(): string
        {
            return $this->buildPrompt();
        }
    };

    $fieldsPrompt = new class ('Add contact details', 'Contact form') extends GenerateFormFieldsPrompt {
        public function compiled(): string
        {
            return $this->buildPrompt();
        }
    };

    expect($formPrompt->compiled())
        ->toContain('Form authoring quality baseline')
        ->toContain('Use a contextual submit label')
        ->not->toContain('{authoringGuidelines}')
        ->and($fieldsPrompt->compiled())
        ->toContain('Use human-facing labels in sentence case')
        ->not->toContain('{authoringGuidelines}');
});

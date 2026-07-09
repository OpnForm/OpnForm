<?php

use App\Jobs\Form\GenerateAiFormula;
use App\Models\Forms\AI\AiFormCompletion;
use App\Service\AI\Prompts\Form\GenerateFormulaPrompt;
use App\Service\OpenAi\GptCompleter;
use Illuminate\Support\Facades\Queue;

$testFields = [
    ['id' => 'uuid-price', 'name' => 'Price', 'type' => 'number'],
    ['id' => 'uuid-quantity', 'name' => 'Quantity', 'type' => 'number'],
    ['id' => 'uuid-name', 'name' => 'Full Name', 'type' => 'text'],
];

$testVariables = [
    ['id' => 'uuid-subtotal', 'name' => 'Subtotal'],
];

beforeEach(function () {
    config()->set('services.openai.api_key', 'test-fake-key');
});

describe('AI Formula Generation', function () use ($testFields, $testVariables) {

    describe('API endpoint POST /forms/ai/generate-formula', function () {
        beforeEach(function () {
            Queue::fake();
        });

        it('creates an ai_form_completion with formula type', function () {
            $this->actingAsUser();

            $response = $this->postJson(route('forms.ai.generate-formula'), [
                'formula_prompt' => 'Multiply price by quantity',
                'context' => [
                    'fields' => [
                        ['id' => 'uuid-1', 'name' => 'Price', 'type' => 'number'],
                        ['id' => 'uuid-2', 'name' => 'Quantity', 'type' => 'number'],
                    ],
                    'computed_variables' => [],
                ],
            ]);

            $response->assertSuccessful()
                ->assertJsonStructure(['type', 'message', 'ai_form_completion_id']);

            $completionId = $response->json('ai_form_completion_id');
            $this->assertDatabaseHas('ai_form_completions', [
                'id' => $completionId,
                'type' => 'formula',
                'form_prompt' => 'Multiply price by quantity',
            ]);
        });

        it('validates formula_prompt is required', function () {
            $this->actingAsUser();

            $response = $this->postJson(route('forms.ai.generate-formula'), [
                'context' => ['fields' => []],
            ]);

            $response->assertStatus(422)
                ->assertJsonValidationErrors(['formula_prompt']);
        });

        it('validates formula_prompt max length', function () {
            $this->actingAsUser();

            $response = $this->postJson(route('forms.ai.generate-formula'), [
                'formula_prompt' => str_repeat('a', 10001),
                'context' => ['fields' => []],
            ]);

            $response->assertStatus(422)
                ->assertJsonValidationErrors(['formula_prompt']);
        });

        it('stores context with field and variable data', function () {
            $this->actingAsUser();

            $context = [
                'fields' => [
                    ['id' => 'f1', 'name' => 'Name', 'type' => 'text'],
                    ['id' => 'f2', 'name' => 'Email', 'type' => 'email'],
                ],
                'computed_variables' => [
                    ['id' => 'v1', 'name' => 'Full Info'],
                ],
            ];

            $response = $this->postJson(route('forms.ai.generate-formula'), [
                'formula_prompt' => 'Concatenate name and email',
                'context' => $context,
            ]);

            $response->assertSuccessful();

            $completion = AiFormCompletion::find($response->json('ai_form_completion_id'));
            expect($completion->context)->toBe($context);
            expect($completion->type)->toBe('formula');
        });

        it('accepts request without context', function () {
            $this->actingAsUser();

            $response = $this->postJson(route('forms.ai.generate-formula'), [
                'formula_prompt' => 'Add 1 + 1',
            ]);

            $response->assertSuccessful();
        });
    });

    describe('Job dispatch', function () {
        it('dispatches GenerateAiFormula when formula completion is created', function () {
            Queue::fake();

            AiFormCompletion::create([
                'type' => AiFormCompletion::TYPE_FORMULA,
                'form_prompt' => 'Sum two numbers',
                'context' => ['fields' => [], 'computed_variables' => []],
                'ip' => '127.0.0.1',
            ]);

            Queue::assertPushed(GenerateAiFormula::class);
        });

        it('does not dispatch GenerateAiFormula for form type', function () {
            Queue::fake();

            AiFormCompletion::create([
                'type' => AiFormCompletion::TYPE_FORM,
                'form_prompt' => 'Create a contact form',
                'ip' => '127.0.0.1',
            ]);

            Queue::assertNotPushed(GenerateAiFormula::class);
        });

        it('does not dispatch GenerateAiFormula for fields type', function () {
            Queue::fake();

            AiFormCompletion::create([
                'type' => AiFormCompletion::TYPE_FIELDS,
                'form_prompt' => 'Add an email field',
                'ip' => '127.0.0.1',
            ]);

            Queue::assertNotPushed(GenerateAiFormula::class);
        });
    });

    describe('GenerateFormulaPrompt - mention conversion', function () use ($testFields, $testVariables) {
        it('converts field name mentions to IDs', function () use ($testFields, $testVariables) {
            $prompt = new GenerateFormulaPrompt('test', $testFields, $testVariables);

            $method = new ReflectionMethod($prompt, 'convertMentionsToIds');
            $method->setAccessible(true);

            $result = $method->invoke($prompt, '{Price} * {Quantity}');
            expect($result)->toBe('{uuid-price} * {uuid-quantity}');
        });

        it('converts case-insensitively', function () use ($testFields, $testVariables) {
            $prompt = new GenerateFormulaPrompt('test', $testFields, $testVariables);

            $method = new ReflectionMethod($prompt, 'convertMentionsToIds');
            $method->setAccessible(true);

            $result = $method->invoke($prompt, '{price} * {QUANTITY}');
            expect($result)->toBe('{uuid-price} * {uuid-quantity}');
        });

        it('converts variable mentions to IDs', function () use ($testFields, $testVariables) {
            $prompt = new GenerateFormulaPrompt('test', $testFields, $testVariables);

            $method = new ReflectionMethod($prompt, 'convertMentionsToIds');
            $method->setAccessible(true);

            $result = $method->invoke($prompt, '{Subtotal} * 1.1');
            expect($result)->toBe('{uuid-subtotal} * 1.1');
        });

        it('leaves unknown mentions unchanged', function () use ($testFields, $testVariables) {
            $prompt = new GenerateFormulaPrompt('test', $testFields, $testVariables);

            $method = new ReflectionMethod($prompt, 'convertMentionsToIds');
            $method->setAccessible(true);

            $result = $method->invoke($prompt, '{Unknown} + {Price}');
            expect($result)->toBe('{Unknown} + {uuid-price}');
        });

        it('handles formula with no mentions', function () use ($testFields, $testVariables) {
            $prompt = new GenerateFormulaPrompt('test', $testFields, $testVariables);

            $method = new ReflectionMethod($prompt, 'convertMentionsToIds');
            $method->setAccessible(true);

            $result = $method->invoke($prompt, '1 + 2 * 3');
            expect($result)->toBe('1 + 2 * 3');
        });

        it('handles field names with spaces', function () use ($testFields, $testVariables) {
            $prompt = new GenerateFormulaPrompt('test', $testFields, $testVariables);

            $method = new ReflectionMethod($prompt, 'convertMentionsToIds');
            $method->setAccessible(true);

            $result = $method->invoke($prompt, 'UPPER({Full Name})');
            expect($result)->toBe('UPPER({uuid-name})');
        });
    });

    describe('GenerateFormulaPrompt - prompt formatting', function () use ($testFields, $testVariables) {
        it('formats fields as mentions with types', function () use ($testFields, $testVariables) {
            $prompt = new GenerateFormulaPrompt('test', $testFields, $testVariables);

            $method = new ReflectionMethod($prompt, 'formatFields');
            $method->setAccessible(true);

            $result = $method->invoke($prompt);
            expect($result)->toContain('{Price} (type: number)');
            expect($result)->toContain('{Quantity} (type: number)');
            expect($result)->toContain('{Full Name} (type: text)');
            expect($result)->toContain('Use these exact mentions');
        });

        it('formats computed variables as mentions', function () use ($testFields, $testVariables) {
            $prompt = new GenerateFormulaPrompt('test', $testFields, $testVariables);

            $method = new ReflectionMethod($prompt, 'formatComputedVariables');
            $method->setAccessible(true);

            $result = $method->invoke($prompt);
            expect($result)->toContain('{Subtotal}');
            expect($result)->toContain('Use these exact mentions');
        });

        it('returns fallback text for empty fields', function () use ($testVariables) {
            $prompt = new GenerateFormulaPrompt('test', [], $testVariables);

            $method = new ReflectionMethod($prompt, 'formatFields');
            $method->setAccessible(true);

            expect($method->invoke($prompt))->toBe('No form fields available');
        });

        it('returns fallback text for empty variables', function () use ($testFields) {
            $prompt = new GenerateFormulaPrompt('test', $testFields, []);

            $method = new ReflectionMethod($prompt, 'formatComputedVariables');
            $method->setAccessible(true);

            expect($method->invoke($prompt))->toBe('No other computed variables available');
        });

        it('lists available functions', function () use ($testFields, $testVariables) {
            $prompt = new GenerateFormulaPrompt('test', $testFields, $testVariables);

            $method = new ReflectionMethod($prompt, 'formatFunctions');
            $method->setAccessible(true);

            $result = $method->invoke($prompt);
            expect($result)->toContain('IF(condition, trueValue, falseValue)');
            expect($result)->toContain('SUM(value1, value2, ...)');
            expect($result)->toContain('CONCAT(text1, text2, ...)');
        });
    });

    describe('GenerateFormulaPrompt - execute with mocked AI', function () use ($testFields, $testVariables) {
        it('returns valid formula converted to field IDs', function () use ($testFields, $testVariables) {
            $prompt = new GenerateFormulaPrompt('multiply price by quantity', $testFields, $testVariables);

            $mockCompleter = Mockery::mock(GptCompleter::class);
            $mockCompleter->shouldReceive('setJsonSchema')->andReturnSelf();
            $mockCompleter->shouldReceive('setSystemMessage')->andReturnSelf();
            $mockCompleter->shouldReceive('completeChat')->andReturnSelf();
            $mockCompleter->shouldReceive('getArray')->andReturn([
                'formula' => '{Price} * {Quantity}',
            ]);

            $prompt->setGptCompleter($mockCompleter);
            $result = $prompt->execute();

            expect($result['formula'])->toBe('{uuid-price} * {uuid-quantity}');
        });

        it('retries on syntax error then returns valid formula', function () use ($testFields, $testVariables) {
            $prompt = new GenerateFormulaPrompt('discount formula', $testFields, $testVariables);

            $mockCompleter = Mockery::mock(GptCompleter::class);
            $mockCompleter->shouldReceive('setJsonSchema')->andReturnSelf();
            $mockCompleter->shouldReceive('setSystemMessage')->andReturnSelf();
            $mockCompleter->shouldReceive('completeChat')->andReturnSelf();
            $mockCompleter->shouldReceive('getArray')
                ->andReturn(
                    ['formula' => 'IF({Price} > 100, {Price} * 0.9, {Price}'],
                    ['formula' => 'IF({Price} > 100, {Price} * 0.9, {Price})'],
                );

            $prompt->setGptCompleter($mockCompleter);
            $result = $prompt->execute();

            expect($result['formula'])->toBe('IF({uuid-price} > 100, {uuid-price} * 0.9, {uuid-price})');
        });

        it('retries on unknown field reference then returns valid formula', function () use ($testFields, $testVariables) {
            $prompt = new GenerateFormulaPrompt('test', $testFields, $testVariables);

            $mockCompleter = Mockery::mock(GptCompleter::class);
            $mockCompleter->shouldReceive('setJsonSchema')->andReturnSelf();
            $mockCompleter->shouldReceive('setSystemMessage')->andReturnSelf();
            $mockCompleter->shouldReceive('completeChat')->andReturnSelf();
            $mockCompleter->shouldReceive('getArray')
                ->andReturn(
                    ['formula' => '{Nonexistent} + 1'],
                    ['formula' => '{Price} + 1'],
                );

            $prompt->setGptCompleter($mockCompleter);
            $result = $prompt->execute();

            expect($result['formula'])->toBe('{uuid-price} + 1');
        });

        it('throws RuntimeException after max retries with invalid formulas', function () use ($testFields, $testVariables) {
            $prompt = new GenerateFormulaPrompt('test', $testFields, $testVariables);

            $mockCompleter = Mockery::mock(GptCompleter::class);
            $mockCompleter->shouldReceive('setJsonSchema')->andReturnSelf();
            $mockCompleter->shouldReceive('setSystemMessage')->andReturnSelf();
            $mockCompleter->shouldReceive('completeChat')->andReturnSelf();
            $mockCompleter->shouldReceive('getArray')
                ->andReturn(['formula' => 'IF({Price} > 100, {Price}']);

            $prompt->setGptCompleter($mockCompleter);

            expect(fn () => $prompt->execute())
                ->toThrow(\RuntimeException::class, 'Failed to generate a valid formula after 3 attempts');
        });

        it('strips markdown code fences from AI response', function () use ($testFields, $testVariables) {
            $prompt = new GenerateFormulaPrompt('test', $testFields, $testVariables);

            $mockCompleter = Mockery::mock(GptCompleter::class);
            $mockCompleter->shouldReceive('setJsonSchema')->andReturnSelf();
            $mockCompleter->shouldReceive('setSystemMessage')->andReturnSelf();
            $mockCompleter->shouldReceive('completeChat')->andReturnSelf();
            $mockCompleter->shouldReceive('getArray')->andReturn([
                'formula' => "```\n{Price} * {Quantity}\n```",
            ]);

            $prompt->setGptCompleter($mockCompleter);
            $result = $prompt->execute();

            expect($result['formula'])->toBe('{uuid-price} * {uuid-quantity}');
        });

        it('throws on empty formula after all retries', function () use ($testFields, $testVariables) {
            $prompt = new GenerateFormulaPrompt('test', $testFields, $testVariables);

            $mockCompleter = Mockery::mock(GptCompleter::class);
            $mockCompleter->shouldReceive('setJsonSchema')->andReturnSelf();
            $mockCompleter->shouldReceive('setSystemMessage')->andReturnSelf();
            $mockCompleter->shouldReceive('completeChat')->andReturnSelf();
            $mockCompleter->shouldReceive('getArray')
                ->andReturn(['formula' => '']);

            $prompt->setGptCompleter($mockCompleter);

            expect(fn () => $prompt->execute())
                ->toThrow(\RuntimeException::class, 'AI returned an empty formula');
        });

        it('accepts formulas using only available computed variables', function () use ($testFields, $testVariables) {
            $prompt = new GenerateFormulaPrompt('add tax', $testFields, $testVariables);

            $mockCompleter = Mockery::mock(GptCompleter::class);
            $mockCompleter->shouldReceive('setJsonSchema')->andReturnSelf();
            $mockCompleter->shouldReceive('setSystemMessage')->andReturnSelf();
            $mockCompleter->shouldReceive('completeChat')->andReturnSelf();
            $mockCompleter->shouldReceive('getArray')->andReturn([
                'formula' => '{Subtotal} * 1.1',
            ]);

            $prompt->setGptCompleter($mockCompleter);
            $result = $prompt->execute();

            expect($result['formula'])->toBe('{uuid-subtotal} * 1.1');
        });

        it('handles complex nested formulas correctly', function () use ($testFields, $testVariables) {
            $prompt = new GenerateFormulaPrompt('complex calc', $testFields, $testVariables);

            $mockCompleter = Mockery::mock(GptCompleter::class);
            $mockCompleter->shouldReceive('setJsonSchema')->andReturnSelf();
            $mockCompleter->shouldReceive('setSystemMessage')->andReturnSelf();
            $mockCompleter->shouldReceive('completeChat')->andReturnSelf();
            $mockCompleter->shouldReceive('getArray')->andReturn([
                'formula' => 'IF({Quantity} > 100, {Price} * {Quantity} * 0.9, {Price} * {Quantity})',
            ]);

            $prompt->setGptCompleter($mockCompleter);
            $result = $prompt->execute();

            expect($result['formula'])->toBe('IF({uuid-quantity} > 100, {uuid-price} * {uuid-quantity} * 0.9, {uuid-price} * {uuid-quantity})');
        });
    });

    describe('Polling GET /forms/ai/{completion}', function () {
        beforeEach(function () {
            Queue::fake();
        });

        it('returns completed formula result', function () {
            $this->actingAsUser();

            $completion = AiFormCompletion::create([
                'type' => AiFormCompletion::TYPE_FORMULA,
                'form_prompt' => 'Sum fields',
                'context' => ['fields' => [['id' => 'f1', 'name' => 'A', 'type' => 'number']]],
                'ip' => request()->ip(),
            ]);
            $completion->update([
                'status' => AiFormCompletion::STATUS_COMPLETED,
                'result' => json_encode(['formula' => '{f1} + 1']),
            ]);

            $response = $this->getJson(route('forms.ai.show', $completion->id));

            $response->assertSuccessful()
                ->assertJsonPath('ai_form_completion.status', 'completed')
                ->assertJsonPath('ai_form_completion.result', json_encode(['formula' => '{f1} + 1']));
        });

        it('returns processing status while in progress', function () {
            $this->actingAsUser();

            $completion = AiFormCompletion::create([
                'type' => AiFormCompletion::TYPE_FORMULA,
                'form_prompt' => 'Something',
                'context' => ['fields' => []],
                'ip' => request()->ip(),
            ]);
            $completion->update(['status' => AiFormCompletion::STATUS_PROCESSING]);

            $response = $this->getJson(route('forms.ai.show', $completion->id));

            $response->assertSuccessful()
                ->assertJsonPath('ai_form_completion.status', 'processing');
        });

        it('returns failed status with error', function () {
            $this->actingAsUser();

            $completion = AiFormCompletion::create([
                'type' => AiFormCompletion::TYPE_FORMULA,
                'form_prompt' => 'Invalid thing',
                'context' => ['fields' => []],
                'ip' => request()->ip(),
            ]);
            $completion->update([
                'status' => AiFormCompletion::STATUS_FAILED,
                'error' => 'Failed to generate a valid formula after 3 attempts.',
            ]);

            $response = $this->getJson(route('forms.ai.show', $completion->id));

            $response->assertSuccessful()
                ->assertJsonPath('ai_form_completion.status', 'failed');
        });

        it('rejects access from a different IP', function () {
            $completion = AiFormCompletion::create([
                'type' => AiFormCompletion::TYPE_FORMULA,
                'form_prompt' => 'test',
                'context' => ['fields' => []],
                'ip' => '10.0.0.99',
            ]);
            $completion->update([
                'status' => AiFormCompletion::STATUS_COMPLETED,
                'result' => json_encode(['formula' => '1 + 1']),
            ]);

            $response = $this->getJson(route('forms.ai.show', $completion->id));

            // Controller returns non-200 for IP mismatch
            expect($response->status())->not->toBe(200);
        });
    });
});

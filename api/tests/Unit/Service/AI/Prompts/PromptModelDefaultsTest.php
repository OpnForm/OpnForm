<?php

use App\Service\AI\Prompts\Prompt;
use App\Service\OpenAi\GptCompleter;

it('uses the current mini model as the base prompt model', function () {
    $defaults = (new ReflectionClass(Prompt::class))->getDefaultProperties();

    expect($defaults['model'])->toBe('gpt-5.4-mini');
});

it('does not keep legacy OpenAI model ids in active app code', function () {
    $apiPath = dirname(__DIR__, 5);
    $roots = [
        $apiPath . '/app',
        $apiPath . '/config',
    ];
    $legacyModels = [
        'gpt-4.1',
        'gpt-4o',
        'o4-mini',
    ];
    $offenders = [];

    foreach ($roots as $root) {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $content = file_get_contents($file->getPathname());

            foreach ($legacyModels as $legacyModel) {
                if (str_contains($content, $legacyModel)) {
                    $offenders[] = str_replace($apiPath . '/', '', $file->getPathname()) . " contains {$legacyModel}";
                }
            }
        }
    }

    expect($offenders)->toBe([]);
});

it('builds a chat completions payload for the default model with existing parameters', function () {
    $completer = new GptCompleter('gpt-5.4-mini', 'test-key');
    $completer
        ->setSystemMessage('Return a compact JSON response.')
        ->setJsonSchema([
            'type' => 'object',
            'required' => ['ok'],
            'additionalProperties' => false,
            'properties' => [
                'ok' => ['type' => 'boolean'],
            ],
        ]);

    $method = new ReflectionMethod(GptCompleter::class, 'computeChatCompletion');
    $method->setAccessible(true);
    $method->invoke(
        $completer,
        [['role' => 'user', 'content' => 'Say ok.']],
        128,
        0.2
    );

    $property = new ReflectionProperty(GptCompleter::class, 'completionInput');
    $property->setAccessible(true);
    $payload = $property->getValue($completer);

    expect($payload['model'])->toBe('gpt-5.4-mini')
        ->and($payload['max_completion_tokens'])->toBe(128)
        ->and($payload)->not->toHaveKey('max_tokens')
        ->and($payload['temperature'])->toBe(0.2)
        ->and($payload['messages'][0])->toBe([
            'role' => 'system',
            'content' => 'Return a compact JSON response.',
        ])
        ->and($payload['messages'][1])->toBe([
            'role' => 'user',
            'content' => 'Say ok.',
        ])
        ->and($payload['response_format']['type'])->toBe('json_schema')
        ->and($payload['response_format']['json_schema']['strict'])->toBeTrue();
});

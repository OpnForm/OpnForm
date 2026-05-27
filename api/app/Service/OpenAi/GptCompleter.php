<?php

namespace App\Service\OpenAi;

use App\Service\OpenAi\Utils\JsonFixer;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use OpenAI\Client;
use OpenAI\Exceptions\ErrorException;

/**
 * Handles a GPT completion prompt with or without insert tag.
 * Also parses output.
 */
class GptCompleter
{
    protected Client $openAi;

    protected mixed $result;

    protected array $completionInput;

    protected ?string $systemMessage;

    protected bool $expectsJson = false;

    protected int $inputTokens = 0;
    protected int $outputTokens = 0;

    protected bool $useStreaming = false;

    public function __construct(protected string $model, ?string $apiKey = null, protected int $retries = 2)
    {
        $this->openAi = \OpenAI::client($apiKey ?? config('services.openai.api_key'));
    }

    public function setAiModel(string $model)
    {
        $this->model = $model;

        return $this;
    }

    public function setSystemMessage(string $systemMessage): self
    {
        $this->systemMessage = $systemMessage;

        return $this;
    }

    public function useStreaming(): self
    {
        $this->useStreaming = true;

        return $this;
    }

    public function expectsJson(): self
    {
        $this->expectsJson = true;

        return $this;
    }

    public function doesNotExpectJson(): self
    {
        $this->expectsJson = false;

        return $this;
    }

    public function setJsonSchema(array $schema): self
    {
        $this->completionInput['response_format'] = [
            'type' => 'json_schema',
            'json_schema' => [
                'name' => 'response_schema',
                'strict' => true,
                'schema' => $schema
            ]
        ];

        return $this;
    }

    public function completeChat(array $messages, ?int $maxTokens = 4096, ?float $temperature = 0.81, ?bool $exceptJson = null): self
    {
        if (! is_null($exceptJson)) {
            $this->expectsJson = $exceptJson;
        }
        $this->computeChatCompletion($messages, $maxTokens, $temperature)
            ->queryCompletion();

        return $this;
    }

    public function getBool(): bool
    {
        switch (strtolower($this->result)) {
            case 'true':
                return true;
            case 'false':
                return false;
            default:
                throw new \InvalidArgumentException("Expected a boolean value, got {$this->result}");
        }
    }

    public function getArray(): array
    {
        for ($i = 0; $i < $this->retries; $i++) {
            $payload = Str::of($this->result)->trim();
            if ($payload->contains('```json')) {
                $payload = $payload->after('```json')->before('```');
            } elseif ($payload->contains('```')) {
                $payload = $payload->after('```')->before('```');
            }
            $payload = $payload->toString();
            $exception = null;

            try {
                $newPayload = (new JsonFixer())->fix($payload);

                return json_decode($newPayload, true);
            } catch (\Aws\Exception\InvalidJsonException $e) {
                $exception = $e;
                Log::warning('Invalid JSON, retrying:');
                Log::warning($payload);
                $this->queryCompletion();
            }
        }
        throw $exception;
    }

    public function getHtml(): string
    {
        $payload = Str::of($this->result)->trim();
        if ($payload->contains('```html')) {
            $payload = $payload->after('```html')->before('```');
        } elseif ($payload->contains('```')) {
            $payload = $payload->after('```')->before('```');
        }

        return $payload->toString();
    }

    public function getString(): string
    {
        return trim($this->result);
    }

    public function getInputTokens(): int
    {
        return $this->inputTokens;
    }

    public function getOutputTokens(): int
    {
        return $this->outputTokens;
    }

    protected function computeChatCompletion(array $messages, ?int $maxTokens = 4096, ?float $temperature = 0.81): self
    {
        if (isset($this->systemMessage) && $messages[0]['role'] !== 'system') {
            $messages = array_merge([[
                'role' => 'system',
                'content' => $this->systemMessage,
            ]], $messages);
        }

        $completionInput = [
            'model' => $this->model,
            'messages' => $messages,
        ];

        if (!is_null($maxTokens)) {
            $completionInput[$this->maxTokensParameter()] = $maxTokens;
        }

        if (!is_null($temperature)) {
            $completionInput['temperature'] = $temperature;
        }

        if ($this->expectsJson && !isset($this->completionInput['response_format'])) {
            $completionInput['response_format'] = [
                'type' => 'json_object',
            ];
        } elseif (isset($this->completionInput['response_format'])) {
            $completionInput['response_format'] = $this->completionInput['response_format'];
        }

        $this->completionInput = $completionInput;

        return $this;
    }

    private function maxTokensParameter(): string
    {
        return str_starts_with($this->model, 'gpt-5') ? 'max_completion_tokens' : 'max_tokens';
    }

    protected function queryCompletion(): self
    {
        if ($this->useStreaming) {
            return $this->queryStreamedCompletion();
        }

        $attempt = 1;
        $lastError = null;

        while ($attempt <= $this->retries) {
            try {
                $response = $this->openAi->chat()->create($this->completionInput);
                $this->inputTokens = $response->usage->promptTokens;
                $this->outputTokens = $response->usage->completionTokens;
                $this->result = $response->choices[0]->message->content;
                return $this;
            } catch (ErrorException $errorException) {
                $lastError = $errorException;
                Log::warning("Open AI error, retrying: {$errorException->getMessage()}");
                $attempt++;
            }
        }

        throw $lastError ?? new \Exception('Failed to complete OpenAI request after multiple attempts');
    }

    protected function queryStreamedCompletion(): self
    {
        Log::debug('Open AI query: ' . json_encode($this->completionInput));
        $this->result = '';
        $response = $this->openAi->chat()->createStreamed($this->completionInput);
        foreach ($response as $chunk) {
            $choice = $chunk->choices[0];
            if (is_null($choice->delta->role)) {
                $this->result .= $choice->delta->content;
            }
        }

        return $this;
    }
}

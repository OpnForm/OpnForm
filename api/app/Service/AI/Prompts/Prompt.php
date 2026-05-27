<?php

namespace App\Service\AI\Prompts;

use App\Service\OpenAi\GptCompleter;
use Illuminate\Support\Facades\Log;
use ReflectionClass;
use ReflectionProperty;

abstract class Prompt
{
    protected GptCompleter $completer;

    protected ?array $jsonSchema = null;

    protected ?float $temperature = 0.7;

    protected ?int $maxTokens = 4096;

    protected string $model = 'gpt-5.4-mini';

    protected bool $useStreaming = false;

    /**
     * Static method to create and execute a prompt in one step
     *
     * @param array $args Arguments to pass to the prompt constructor
     * @return mixed The result of executing the prompt
     */
    public static function run(...$args): mixed
    {
        $reflection = new ReflectionClass(static::class);
        $instance = $reflection->newInstanceArgs($args);
        return $instance->execute();
    }

    public function __construct()
    {
        $this->completer = new GptCompleter($this->model);
    }

    protected function initialize(): void
    {
        if ($this->jsonSchema) {
            $this->completer->setJsonSchema($this->jsonSchema);
        }

        if ($this->getSystemMessage()) {
            $this->completer->setSystemMessage($this->getSystemMessage());
        }

        if ($this->useStreaming) {
            $this->completer->useStreaming();
        }
    }

    /**
     * Override this method to set a custom system message
     */
    protected function getSystemMessage(): ?string
    {
        return null;
    }

    /**
     * Must return the prompt template with placeholders
     */
    abstract protected function getPromptTemplate(): string;

    public function getCompleter(): GptCompleter
    {
        return $this->completer;
    }

    public function execute(): mixed
    {
        $this->initialize();
        $prompt = $this->buildPrompt();

        try {
            $this->completer->completeChat(
                [['role' => 'user', 'content' => $prompt]],
                $this->maxTokens,
                $this->temperature
            );

            return $this->jsonSchema ? $this->completer->getArray() : $this->completer->getString();
        } catch (\Exception $e) {
            Log::error('Error while executing prompt', [
                'exception' => $e,
                'prompt' => $prompt,
                'json_schema' => $this->jsonSchema ?? null
            ]);
            throw $e;
        }
    }

    protected function buildPrompt(): string
    {
        $template = $this->getPromptTemplate();
        $variables = $this->getPromptVariables();

        return strtr($template, $variables);
    }

    protected function getPromptVariables(): array
    {
        $variables = [];
        $reflection = new ReflectionClass($this);

        foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            $name = $property->getName();
            $value = $property->getValue($this);

            if (is_scalar($value)) {
                $variables['{' . $name . '}'] = (string) $value;
            }
        }

        return $variables;
    }

    public function setTemperature(float $temperature): self
    {
        $this->temperature = $temperature;
        return $this;
    }

    public function setMaxTokens(int $maxTokens): self
    {
        $this->maxTokens = $maxTokens;
        return $this;
    }

    public function setGptCompleter(GptCompleter $completer): self
    {
        $this->completer = $completer;
        return $this;
    }

    public function useStreaming(): self
    {
        $this->useStreaming = true;
        return $this;
    }
}

<?php

namespace App\Service\AI\Mcp\Tools;

abstract class McpTool
{
    abstract public function name(): string;

    public function title(): string
    {
        return ucwords(str_replace(['_', '-'], ' ', $this->name()));
    }

    abstract public function description(): string;

    abstract public function inputSchema(): array;

    abstract public function execute(array $arguments): array;

    public function response(array $arguments): array
    {
        $structuredContent = $this->execute($arguments);
        $contentItem = [
            'type' => 'text',
            'text' => json_encode($structuredContent),
        ];
        $response = [
            'content' => [$contentItem],
            'structuredContent' => $structuredContent,
        ];

        $meta = $this->meta($arguments, $structuredContent);
        if (! empty($meta)) {
            $response['_meta'] = $meta;
            $response['content'][0]['_meta'] = $meta;
        }

        return $response;
    }

    protected function meta(array $arguments, array $structuredContent): array
    {
        return [];
    }

    public function spec(): array
    {
        $spec = [
            'name' => $this->name(),
            'title' => $this->title(),
            'description' => $this->description(),
            'inputSchema' => $this->inputSchema(),
        ];

        if ($this->isReadOnly()) {
            $spec['annotations'] = [
                'readOnlyHint' => true,
            ];
        }

        $toolMeta = $this->toolMeta();
        if (! empty($toolMeta)) {
            $spec['_meta'] = $toolMeta;
        }

        return $spec;
    }

    protected function toolMeta(): array
    {
        return [];
    }

    protected function isReadOnly(): bool
    {
        return false;
    }
}

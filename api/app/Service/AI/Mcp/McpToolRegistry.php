<?php

namespace App\Service\AI\Mcp;

use App\Service\AI\Mcp\Tools\CreateDraftTool;
use App\Service\AI\Mcp\Tools\GetDraftTool;
use App\Service\AI\Mcp\Tools\GetFormGenerationGuideTool;
use App\Service\AI\Mcp\Tools\HandoffDraftTool;
use App\Service\AI\Mcp\Tools\McpTool;
use App\Service\AI\Mcp\Tools\PatchDraftTool;
use App\Service\AI\Mcp\Tools\RenderDraftPreviewTool;
use App\Service\AI\Mcp\Tools\UpdateDraftTool;
use RuntimeException;

class McpToolRegistry
{
    /**
     * @var McpTool[]
     */
    private array $tools;

    private ?array $toolMap = null;

    public function __construct(
        GetFormGenerationGuideTool $getFormGenerationGuideTool,
        CreateDraftTool $createDraftTool,
        GetDraftTool $getDraftTool,
        PatchDraftTool $patchDraftTool,
        UpdateDraftTool $updateDraftTool,
        RenderDraftPreviewTool $renderDraftPreviewTool,
        HandoffDraftTool $handoffDraftTool
    ) {
        $this->tools = [
            $getFormGenerationGuideTool,
            $createDraftTool,
            $getDraftTool,
            $patchDraftTool,
            $updateDraftTool,
            $renderDraftPreviewTool,
            $handoffDraftTool,
        ];
    }

    public function list(): array
    {
        return array_values(array_map(
            static fn (array $entry) => $entry['spec'],
            $this->toolMap()
        ));
    }

    public function call(string $toolName, array $arguments): array
    {
        $entry = $this->toolMap()[$toolName] ?? null;
        if (! $entry) {
            throw new RuntimeException('Unknown tool: ' . $toolName);
        }

        /** @var McpTool $tool */
        $tool = $entry['tool'];
        return $tool->response($arguments);
    }

    private function toolMap(): array
    {
        if ($this->toolMap !== null) {
            return $this->toolMap;
        }

        $tools = [];
        foreach ($this->tools as $tool) {
            $toolName = $tool->name();

            if (isset($tools[$toolName])) {
                throw new RuntimeException('Duplicate MCP tool name detected: ' . $toolName);
            }

            $tools[$toolName] = [
                'tool' => $tool,
                'spec' => $tool->spec(),
            ];
        }

        $this->toolMap = $tools;
        return $tools;
    }
}

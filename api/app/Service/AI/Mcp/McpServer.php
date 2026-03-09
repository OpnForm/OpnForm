<?php

namespace App\Service\AI\Mcp;

use RuntimeException;

class McpServer
{
    private const PROTOCOL_VERSION = '2024-11-05';
    private const SERVER_NAME = 'opnform-chatgpt-mcp';
    private const SERVER_VERSION = '0.1.0';

    public function __construct(
        private readonly McpToolRegistry $toolRegistry,
        private readonly ChatGptUiResourceService $uiResourceService
    ) {
    }

    public function health(): array
    {
        return [
            'name' => self::SERVER_NAME,
            'status' => 'ok',
            'endpoint' => '/api/mcp',
        ];
    }

    public function execute(string $method, array $params = []): array
    {
        $handler = $this->resolveRpcHandler($method);
        if (! $handler) {
            throw new RuntimeException('Method not found');
        }

        return $this->{$handler}($params);
    }

    private function resolveRpcHandler(string $method): ?string
    {
        if ($method === '') {
            return null;
        }

        $parts = preg_split('/[^a-zA-Z0-9]+/', $method) ?: [];
        $suffix = implode('', array_map(static fn ($part) => ucfirst((string) $part), array_filter($parts)));
        if ($suffix === '') {
            return null;
        }

        $handler = 'rpc' . $suffix;
        return method_exists($this, $handler) ? $handler : null;
    }

    private function rpcInitialize(): array
    {
        return [
            'protocolVersion' => self::PROTOCOL_VERSION,
            'capabilities' => [
                'tools' => [
                    'listChanged' => false,
                ],
                'resources' => [
                    'listChanged' => false,
                ],
            ],
            'serverInfo' => [
                'name' => self::SERVER_NAME,
                'version' => self::SERVER_VERSION,
            ],
        ];
    }

    private function rpcToolsList(): array
    {
        return [
            'tools' => $this->toolRegistry->list(),
        ];
    }

    private function rpcToolsCall(array $params): array
    {
        $toolName = (string) ($params['name'] ?? '');
        $arguments = is_array($params['arguments'] ?? null) ? $params['arguments'] : [];
        return $this->toolRegistry->call($toolName, $arguments);
    }

    private function rpcResourcesList(): array
    {
        return [
            'resources' => $this->uiResourceService->list(),
        ];
    }

    private function rpcResourcesRead(array $params): array
    {
        $uri = (string) ($params['uri'] ?? '');

        return [
            'contents' => $this->uiResourceService->read($uri),
        ];
    }

    private function rpcPing(): array
    {
        return ['pong' => true];
    }
}

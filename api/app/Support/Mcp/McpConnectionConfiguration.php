<?php

namespace App\Support\Mcp;

final class McpConnectionConfiguration
{
    /**
     * @return array{server_url: string, settings_url: string, snippets: array{native: string, portable: string, codex_cli: string}}
     */
    public function forSelfHostedInstance(): array
    {
        $appUrl = rtrim((string) config('app.url'), '/');
        $serverUrl = $appUrl.'/mcp';

        return [
            'server_url' => $serverUrl,
            'settings_url' => front_url('/?user-settings=mcp'),
            'snippets' => [
                'native' => $this->json([
                    'mcpServers' => [
                        'opnform' => [
                            'type' => 'http',
                            'url' => $serverUrl,
                            'auth' => 'oauth',
                        ],
                    ],
                ]),
                'portable' => $this->json([
                    '$schema' => 'https://agent-plugins.org/schemas/1.0.0/mcp.schema.json',
                    'mcpServers' => [
                        'opnform' => [
                            'type' => 'streamable-http',
                            'url' => $serverUrl,
                        ],
                    ],
                ]),
                'codex_cli' => 'codex mcp add opnform --url '.escapeshellarg($serverUrl),
            ],
        ];
    }

    private function json(array $value): string
    {
        return json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }
}

<?php

namespace App\Support\Mcp;

final class McpConnectionConfiguration
{
    /**
     * @return array{
     *     server_url: string,
     *     settings_url: string,
     *     snippets: array{cursor: string, claude_code: string, chatgpt: string, codex: string, other: string, portable: string},
     *     install_urls: array{cursor: string}
     * }
     */
    public function forInstance(): array
    {
        $appUrl = rtrim((string) config('app.url'), '/');
        $serverUrl = $appUrl.'/mcp';

        return [
            'server_url' => $serverUrl,
            'settings_url' => front_url('/?user-settings=mcp'),
            'snippets' => [
                'cursor' => $this->json([
                    'mcpServers' => [
                        'opnform' => [
                            'url' => $serverUrl,
                        ],
                    ],
                ]),
                'claude_code' => 'claude mcp add --transport http opnform '.escapeshellarg($serverUrl),
                'chatgpt' => "Server URL: {$serverUrl}\nAuthentication: OAuth",
                'codex' => 'codex mcp add opnform --url '.escapeshellarg($serverUrl),
                'other' => $this->json([
                    'mcpServers' => [
                        'opnform' => [
                            'type' => 'http',
                            'url' => $serverUrl,
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
            ],
            'install_urls' => [
                'cursor' => 'cursor://anysphere.cursor-deeplink/mcp/install?name=opnform&config='.
                    rawurlencode(base64_encode($this->json(['url' => $serverUrl]))),
            ],
        ];
    }

    private function json(array $value): string
    {
        return json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }
}

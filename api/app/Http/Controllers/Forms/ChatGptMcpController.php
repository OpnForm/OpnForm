<?php

namespace App\Http\Controllers\Forms;

use App\Http\Controllers\Controller;
use App\Service\AI\Mcp\McpServer;
use Illuminate\Http\Request;
use RuntimeException;

class ChatGptMcpController extends Controller
{
    public function __construct(
        private readonly McpServer $mcpServer
    ) {
    }

    public function handle(Request $request)
    {
        if ($request->isMethod('get')) {
            return response()->json($this->mcpServer->health());
        }

        $payload = $request->json()->all();
        $id = $payload['id'] ?? null;
        $method = (string) ($payload['method'] ?? '');
        $params = is_array($payload['params'] ?? null) ? $payload['params'] : [];

        try {
            $result = $this->mcpServer->execute($method, $params);

            return response()->json([
                'jsonrpc' => '2.0',
                'id' => $id,
                'result' => $result,
            ]);
        } catch (RuntimeException $e) {
            $code = $e->getMessage() === 'Method not found' ? -32601 : -32000;

            return response()->json([
                'jsonrpc' => '2.0',
                'id' => $id,
                'error' => [
                    'code' => $code,
                    'message' => $e->getMessage(),
                ],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'jsonrpc' => '2.0',
                'id' => $id,
                'error' => [
                    'code' => -32000,
                    'message' => $e->getMessage(),
                ],
            ]);
        }
    }
}

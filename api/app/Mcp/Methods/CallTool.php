<?php

namespace App\Mcp\Methods;

use App\Mcp\ToolInvoker;
use Generator;
use Laravel\Mcp\Exceptions\JsonRpcException;
use Laravel\Mcp\Server\ServerContext;
use Laravel\Mcp\Transport\JsonRpcRequest;
use Laravel\Mcp\Transport\JsonRpcResponse;

class CallTool extends \Laravel\Mcp\Server\Methods\CallTool
{
    public function handle(JsonRpcRequest $request, ServerContext $context): Generator|JsonRpcResponse
    {
        if (is_null($request->get('name'))) {
            throw new JsonRpcException('Missing [name] parameter.', -32602, $request->id);
        }

        $tool = $context->tools()->first(
            fn ($tool): bool => $tool->name() === $request->params['name'],
            fn () => throw new JsonRpcException("Tool [{$request->params['name']}] not found.", -32602, $request->id),
        );

        // MCP 1.x invokes tools in ToolInvoker, outside CallTool's response handling.
        return (new ToolInvoker())->invoke($tool, $request);
    }
}

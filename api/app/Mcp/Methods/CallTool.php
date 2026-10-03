<?php

namespace App\Mcp\Methods;

use Illuminate\Auth\AuthenticationException;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Transport\JsonRpcRequest;
use Throwable;

class CallTool extends \Laravel\Mcp\Server\Methods\CallTool
{
    protected function callHandler(callable $handler, JsonRpcRequest $request): mixed
    {
        try {
            return $handler();
        } catch (AuthenticationException $exception) {
            return $this->authenticationRequired($exception);
        } catch (Throwable $throwable) {
            return $this->toErrorResponse($throwable);
        }
    }

    protected function authenticationRequired(AuthenticationException $exception): ResponseFactory
    {
        $resourceMetadata = route('mcp.oauth.protected-resource.nested', ['path' => 'mcp']);
        $challenge = sprintf(
            'Bearer resource_metadata="%s", scope="mcp:use", error="insufficient_scope", error_description="%s"',
            $resourceMetadata,
            'Connect your OpnForm account to continue',
        );

        return Response::make(Response::error($exception->getMessage()))
            ->withMeta('mcp/www_authenticate', [$challenge]);
    }
}

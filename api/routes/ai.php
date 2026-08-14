<?php

use App\Mcp\Servers\OpnFormServer;
use Laravel\Mcp\Facades\Mcp;

$mcpEnabled = ! config('app.self_hosted') || config('opnform.mcp.enabled', false);

if ($mcpEnabled) {
    Mcp::web('/mcp', OpnFormServer::class)->middleware('throttle:mcp');
    Mcp::local('opnform', OpnFormServer::class);
}

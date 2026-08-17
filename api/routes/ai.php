<?php

use App\Mcp\Servers\OpnFormServer;
use App\Support\Mcp\McpAvailability;
use Laravel\Mcp\Facades\Mcp;

if (app(McpAvailability::class)->enabled()) {
    Mcp::web('/mcp', OpnFormServer::class)->middleware('throttle:mcp');
    Mcp::local('opnform', OpnFormServer::class);
}

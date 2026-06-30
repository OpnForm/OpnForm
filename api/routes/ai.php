<?php

use App\Mcp\Servers\OpnFormGuestServer;
use App\Mcp\Servers\OpnFormServer;
use Laravel\Mcp\Facades\Mcp;

Mcp::web('/mcp/guest', OpnFormGuestServer::class);

Mcp::web('/mcp', OpnFormServer::class)
    ->middleware('auth:sanctum');

Mcp::local('opnform', OpnFormGuestServer::class);

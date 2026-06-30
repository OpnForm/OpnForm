<?php

use App\Http\Middleware\OptionalSanctumAuth;
use App\Mcp\Servers\OpnFormServer;
use Laravel\Mcp\Facades\Mcp;

Mcp::web('/mcp', OpnFormServer::class)
    ->middleware(OptionalSanctumAuth::class);

Mcp::local('opnform', OpnFormServer::class);

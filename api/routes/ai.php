<?php

use App\Mcp\Servers\OpnFormServer;
use Laravel\Mcp\Facades\Mcp;

Mcp::web('/mcp', OpnFormServer::class);
Mcp::local('opnform', OpnFormServer::class);

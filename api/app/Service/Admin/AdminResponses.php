<?php

namespace App\Service\Admin;

use Illuminate\Http\JsonResponse;

trait AdminResponses
{
    protected function success(array $data = []): JsonResponse
    {
        return response()->json(array_merge(['type' => 'success'], $data));
    }

    protected function error(array $data = [], int $statusCode = 400): JsonResponse
    {
        return response()->json(array_merge(['type' => 'error'], $data), $statusCode);
    }
}

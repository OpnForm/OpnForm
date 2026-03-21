<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;

class Controller extends BaseController
{
    use AuthorizesRequests;
    use DispatchesJobs;
    use ValidatesRequests;

    public function success($data = [])
    {
        return response()->json(array_merge([
            'type' => 'success',
        ], $data));
    }

    public function error($data = [], $statusCode = 400)
    {
        return response()->json(array_merge([
            'type' => 'error',
        ], $data), $statusCode);
    }
}

<?php

namespace App\Http\Controllers\Content;

use App\Http\Controllers\Controller;
use App\Http\Requests\UploadAssetRequest;
use App\Service\Storage\FileUploadPathService;
use App\Service\Storage\UploadSecurityService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class FileUploadController extends Controller
{
    /**
     * Upload file to local temp
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function upload(Request $request, UploadSecurityService $uploadSecurityService)
    {
        $request->validate([
            'file' => 'required|file|max:' . (int) ceil(UploadAssetRequest::FORM_ASSET_MAX_SIZE / 1024),
        ]);

        try {
            $inspection = $uploadSecurityService->inspectUploadedFile($request->file('file'));
        } catch (\App\Exceptions\UploadSecurityException $exception) {
            throw ValidationException::withMessages([
                'file' => [$exception->getMessage()],
            ]);
        }

        $uuid = (string) Str::uuid();
        $path = FileUploadPathService::getTmpFileUploadPath($uuid);

        if ($inspection->isSvg) {
            Storage::put($path, $inspection->sanitizedContents);
        } else {
            Storage::putFileAs(FileUploadPathService::getTmpFileUploadPath(), $request->file('file'), $uuid);
        }

        return response()->json([
            'uuid' => $uuid,
            'key' => $path,
        ], 201);
    }
}

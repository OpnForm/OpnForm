<?php

namespace App\Http\Controllers\Content;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Laravel\Vapor\Http\Controllers\SignedStorageUrlController as Controller;

class SignedStorageUrlController extends Controller
{
    /**
     * Create a new signed URL.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        $bucket = $_ENV['AWS_BUCKET'] ?? null;
        abort_unless(is_string($bucket) && $bucket !== '', 503, 'Upload storage is not configured.');
        $this->ensureEnvironmentVariablesAreAvailable($request);
        $request->validate(['bucket' => ['nullable', 'string', \Illuminate\Validation\Rule::in([$bucket])]]);

        $client = $this->storageClient();

        $uuid = (string) Str::uuid();

        $expiresAfter = config('vapor.signed_storage_url_expires_after', 5);

        $signedRequest = $client->createPresignedRequest(
            $this->createCommand($request, $client, $bucket, $key = ('tmp/'.$uuid)),
            sprintf('+%s minutes', $expiresAfter)
        );

        $uri = $signedRequest->getUri();

        return response()->json([
            'uuid' => $uuid,
            'bucket' => $bucket,
            'key' => $key,
            'url' => $uri->getScheme().'://'.$uri->getAuthority().$uri->getPath().'?'.$uri->getQuery(),
            'headers' => $this->headers($request, $signedRequest),
        ], 201);
    }
}

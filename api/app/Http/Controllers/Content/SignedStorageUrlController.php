<?php

namespace App\Http\Controllers\Content;

use Aws\Middleware;
use Aws\S3\S3Client;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use App\Http\Controllers\Controller;
use Psr\Http\Message\RequestInterface;

class SignedStorageUrlController extends Controller
{
    /**
     * Create a new signed URL.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        $bucket = config('filesystems.disks.s3.bucket');
        abort_unless(is_string($bucket) && $bucket !== '', 503, 'Upload storage is not configured.');
        $request->validate(['bucket' => ['nullable', 'string', Rule::in([$bucket])]]);

        $client = $this->storageClient();

        $uuid = (string) Str::uuid();

        $expiresAfter = config('filesystems.signed_storage_url_expires_after', 5);

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

    protected function storageClient()
    {
        // Keep signed uploads on the same endpoint/credentials as Flysystem.
        // AWS credentials may remain dedicated to SES and DynamoDB.
        return Storage::disk('s3')->getClient();
    }

    protected function createCommand(Request $request, S3Client $client, $bucket, $key)
    {
        $command = $client->getCommand('putObject', array_filter([
            'Bucket' => $bucket,
            'Key' => $key,
            'ACL' => $request->input('visibility') ?: 'private',
            'ContentType' => $request->input('content_type') ?: 'application/octet-stream',
            'CacheControl' => $request->input('cache_control') ?: null,
            'Expires' => $request->input('expires') ?: null,
        ]));

        // The locked SDK builds SignedHeaders in request order but sorts the
        // canonical request. Sort this command only, before it is presigned.
        $command->getHandlerList()->appendBuild(Middleware::mapRequest(function (RequestInterface $signedRequest) {
            $headers = $signedRequest->getHeaders();
            uksort($headers, fn ($left, $right) => strcasecmp($left, $right));

            foreach (array_keys($headers) as $name) {
                $signedRequest = $signedRequest->withoutHeader($name);
            }

            foreach ($headers as $name => $values) {
                $signedRequest = $signedRequest->withHeader($name, $values);
            }

            return $signedRequest;
        }), 'sort-signed-storage-headers');

        return $command;
    }

    protected function headers(Request $request, $signedRequest)
    {
        $headers = array_merge($signedRequest->getHeaders(), [
            'Content-Type' => $request->input('content_type') ?: 'application/octet-stream',
        ]);

        // Cache-Control is deliberately not signed by the SDK and is stripped
        // from its request. The browser must still send the requested metadata.
        if ($cacheControl = $request->input('cache_control')) {
            $headers['Cache-Control'] = $cacheControl;
        }

        return $headers;
    }
}

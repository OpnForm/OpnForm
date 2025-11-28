<?php

namespace App\Exceptions;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Support\Facades\Log;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * A list of the exception types that are not reported.
     *
     * @var array
     */
    protected $dontReport = [
        //
    ];

    /**
     * A list of the exception types that are not reported to Sentry.
     *
     * @var array
     */
    protected $sentryDontReport = [
        //
    ];

    /**
     * A list of the inputs that are never flashed for validation exceptions.
     *
     * @var array
     */
    protected $dontFlash = [
        'password',
        'password_confirmation',
    ];

    /**
     * Convert an authentication exception into a response.
     */
    protected function unauthenticated($request, AuthenticationException $exception)
    {
        return response()->json(['message' => $exception->getMessage()], 401);
    }

    public function report(Throwable $exception)
    {
        if ($this->shouldReport($exception)) {
            if (app()->bound('sentry') && $this->sentryShouldReport($exception)) {
                app('sentry')->captureException($exception);
                Log::debug('Un-handled Exception: ' . $exception->getMessage(), [
                    'exception' => $exception,
                    'file' => $exception->getFile(),
                    'line' => $exception->getLine(),
                    'trace' => $exception->getTrace(),
                ]);
            }
        }

        parent::report($exception);
    }

    public function render($request, Throwable $e)
    {
        if ($this->shouldReport($e) && ! in_array(\App::environment(), ['testing'])) {
            Log::channel('slack_errors')->error('Exception', [
                'exception' => $e,
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTrace()
            ]);
        }

        return parent::render($request, $e);
    }

    private function sentryShouldReport(Throwable $e)
    {
        foreach ($this->sentryDontReport as $exceptionType) {
            if ($e instanceof $exceptionType) {
                return false;
            }
        }

        return true;
    }
}

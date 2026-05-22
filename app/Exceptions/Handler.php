<?php

namespace App\Exceptions;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException as LaravelValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * The list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * A list of exception types with their corresponding custom log levels.
     *
     * @var array<class-string<\Throwable>, \Psr\Log\LogLevel::*>
     */
    protected $levels = [
        MikrotikConnectionException::class => 'warning',
        ServiceUnavailableException::class => 'warning',
        ResourceNotFoundException::class => 'info',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        // Handle Database Exceptions
        $this->reportable(function (QueryException $e) {
            \Illuminate\Support\Facades\Log::error('Database Query Error', [
                'message' => $e->getMessage(),
                'sql' => $e->getSql(),
                'bindings' => $e->getBindings(),
                'code' => $e->getCode(),
            ]);
        });

        // Handle Model Not Found
        $this->reportable(function (ModelNotFoundException $e) {
            \Illuminate\Support\Facades\Log::info('Model Not Found', [
                'model' => $e->getModel(),
                'ids' => $e->getIds(),
            ]);
        });

        // Handle all custom exceptions that extend BaseException
        $this->reportable(function (BaseException $e) {
            $e->report();
        })->stop();

        // Let custom exceptions use their own render() methods
        $this->renderable(function (BaseException $e, $request) {
            return $e->render($request);
        });

        $this->renderable(function (QueryException $e, $request) {
            return $this->handleQueryException($e, $request);
        });

        $this->renderable(function (ModelNotFoundException $e, $request) {
            return $this->handleModelNotFoundException($e, $request);
        });

        $this->renderable(function (NotFoundHttpException $e, $request) {
            return $this->handleNotFoundHttpException($e, $request);
        });
    }

    /**
     * Handle Query Exception
     */
    protected function handleQueryException(QueryException $e, $request): JsonResponse|RedirectResponse
    {
        $message = config('app.debug') 
            ? $e->getMessage() 
            : 'Terjadi kesalahan pada database. Silakan coba lagi.';

        if ($request->expectsJson()) {
            return response()->json([
                'success' => false,
                'message' => $message,
                'error' => 'database_query_error',
            ], 500);
        }

        return redirect()->back()->with('error', $message);
    }

    /**
     * Handle Model Not Found Exception
     */
    protected function handleModelNotFoundException(ModelNotFoundException $e, $request): JsonResponse|RedirectResponse|Response
    {
        $modelName = class_basename($e->getModel());
        $message = "Data {$modelName} tidak ditemukan";

        if ($request->expectsJson()) {
            return response()->json([
                'success' => false,
                'message' => $message,
                'model' => $modelName,
                'error' => 'model_not_found',
            ], 404);
        }

        return response()->view('errors.404', [
            'message' => $message,
        ], 404);
    }

    /**
     * Handle Not Found HTTP Exception
     */
    protected function handleNotFoundHttpException(NotFoundHttpException $e, $request): JsonResponse|Response
    {
        if ($request->expectsJson()) {
            return response()->json([
                'success' => false,
                'message' => 'Halaman tidak ditemukan',
                'error' => 'not_found',
            ], 404);
        }

        return response()->view('errors.404', [
            'message' => 'Halaman yang Anda cari tidak ditemukan',
        ], 404);
    }

    /**
     * Convert an authentication exception into a response.
     */
    protected function unauthenticated($request, AuthenticationException $exception): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return response()->json([
                'success' => false,
                'message' => 'Anda harus login terlebih dahulu',
                'error' => 'unauthenticated',
            ], 401);
        }

        return redirect()->guest(route('login'));
    }
}

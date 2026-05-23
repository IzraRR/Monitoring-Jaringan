<?php

namespace App\Traits;

use App\Exceptions\DatabaseException;
use App\Exceptions\MikrotikConnectionException;
use App\Exceptions\ResourceNotFoundException;
use App\Exceptions\ServiceUnavailableException;
use App\Exceptions\UnauthorizedException;
use App\Exceptions\ValidationException;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Throwable;

trait HandlesErrors
{
    /**
     * Handle exception and return appropriate response
     */
    protected function handleException(
        Throwable $e,
        string $defaultMessage = 'Terjadi kesalahan',
        string $redirectRoute = null
    ): JsonResponse|RedirectResponse {
        Log::error('Exception in ' . static::class, [
            'exception' => get_class($e),
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
        ]);

        $message = config('app.debug') ? $e->getMessage() : $defaultMessage;

        if (request()->expectsJson()) {
            return response()->json([
                'success' => false,
                'message' => $message,
                'error' => class_basename($e),
            ], $this->getStatusCode($e));
        }

        $redirect = $redirectRoute ? redirect()->route($redirectRoute) : redirect()->back();
        
        return $redirect->withInput()->with('error', $message);
    }

    /**
     * Get HTTP status code from exception
     */
    protected function getStatusCode(Throwable $e): int
    {
        if (method_exists($e, 'getStatusCode')) {
            return $e->getStatusCode();
        }

        if (method_exists($e, 'getCode') && $e->getCode() >= 400 && $e->getCode() < 600) {
            return $e->getCode();
        }

        return match (true) {
            $e instanceof MikrotikConnectionException => 503,
            $e instanceof ServiceUnavailableException => 503,
            $e instanceof ResourceNotFoundException => 404,
            $e instanceof ValidationException => 422,
            $e instanceof UnauthorizedException => 403,
            $e instanceof DatabaseException => 500,
            $e instanceof QueryException => 500,
            default => 500,
        };
    }

    /**
     * Return success response
     */
    protected function successResponse(
        string $message,
        array $data = [],
        int $statusCode = 200
    ): JsonResponse|RedirectResponse {
        if (request()->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => $data,
            ], $statusCode);
        }

        return redirect()->back()->with('success', $message);
    }

    /**
     * Return error response
     */
    protected function errorResponse(
        string $message,
        int $statusCode = 400,
        array $errors = []
    ): JsonResponse|RedirectResponse {
        if (request()->expectsJson()) {
            $response = [
                'success' => false,
                'message' => $message,
            ];

            if (!empty($errors)) {
                $response['errors'] = $errors;
            }

            return response()->json($response, $statusCode);
        }

        $redirect = redirect()->back()->withInput()->with('error', $message);

        if (!empty($errors)) {
            $redirect = $redirect->withErrors($errors);
        }

        return $redirect;
    }

    /**
     * Wrap operation in try-catch and handle errors
     */
    protected function tryOperation(
        callable $operation,
        string $successMessage,
        string $errorMessage = 'Operasi gagal',
        string $redirectRoute = null
    ): JsonResponse|RedirectResponse {
        try {
            $result = $operation();

            if (request()->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => $successMessage,
                    'data' => $result,
                ]);
            }

            $redirect = $redirectRoute ? redirect()->route($redirectRoute) : redirect()->back();
            return $redirect->with('success', $successMessage);
        } catch (Throwable $e) {
            return $this->handleException($e, $errorMessage, $redirectRoute);
        }
    }

    /**
     * Log and throw database exception
     */
    protected function throwDatabaseException(string $message, Throwable $previous = null, array $context = []): never
    {
        throw new DatabaseException($message, 500, $previous, $context);
    }

    /**
     * Log and throw resource not found exception
     */
    protected function throwResourceNotFoundException(
        string $resourceType,
        $resourceId = null,
        string $message = ''
    ): never {
        throw new ResourceNotFoundException($resourceType, $resourceId, $message);
    }

    /**
     * Log and throw service unavailable exception
     */
    protected function throwServiceUnavailableException(
        string $serviceName,
        string $message = 'Layanan tidak tersedia',
        array $context = []
    ): never {
        throw new ServiceUnavailableException($serviceName, $message, 503, null, $context);
    }

    /**
     * Log and throw unauthorized exception
     */
    protected function throwUnauthorizedException(string $message = 'Akses ditolak', array $context = []): never
    {
        throw new UnauthorizedException($message, 403, null, $context);
    }

    /**
     * Log and throw validation exception
     */
    protected function throwValidationException(array $errors, string $message = 'Data validasi gagal'): never
    {
        throw new ValidationException($errors, $message);
    }
}

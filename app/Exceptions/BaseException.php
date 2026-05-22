<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Support\Facades\Log;

/**
 * Base exception class untuk mengurangi duplikasi kode
 * Semua custom exception harus extend class ini
 */
abstract class BaseException extends Exception
{
    protected $context = [];
    protected $logLevel = 'error';
    protected $httpStatusCode = 500;

    public function __construct(string $message = "", array $context = [], ?\Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
        $this->context = $context;
    }

    /**
     * Get exception context
     */
    public function getContext(): array
    {
        return $this->context;
    }

    /**
     * Get HTTP status code
     */
    public function getHttpStatusCode(): int
    {
        return $this->httpStatusCode;
    }

    /**
     * Report the exception (logging)
     */
    public function report(): void
    {
        $logData = [
            'message' => $this->getMessage(),
            'context' => $this->context,
            'exception' => get_class($this),
            'file' => $this->getFile(),
            'line' => $this->getLine(),
        ];

        match ($this->logLevel) {
            'emergency' => Log::emergency($this->getMessage(), $logData),
            'alert' => Log::alert($this->getMessage(), $logData),
            'critical' => Log::critical($this->getMessage(), $logData),
            'error' => Log::error($this->getMessage(), $logData),
            'warning' => Log::warning($this->getMessage(), $logData),
            'notice' => Log::notice($this->getMessage(), $logData),
            'info' => Log::info($this->getMessage(), $logData),
            default => Log::error($this->getMessage(), $logData),
        };
    }

    /**
     * Render the exception into an HTTP response
     */
    public function render($request)
    {
        if ($request->expectsJson()) {
            return response()->json([
                'success' => false,
                'message' => $this->getMessage(),
                'error' => get_class($this),
            ], $this->httpStatusCode);
        }

        return redirect()->back()
            ->withInput()
            ->with('error', $this->getMessage());
    }
}

<?php

namespace App\Exceptions;

class ValidationException extends BaseException
{
    protected $httpStatusCode = 422;
    protected $logLevel = 'info';
    protected $errors;

    public function __construct(
        array $errors = [],
        string $message = 'Data validasi gagal',
        array $context = [],
        ?\Throwable $previous = null
    ) {
        $this->errors = $errors;
        $context['validation_errors'] = $errors;
        
        parent::__construct($message, $context, $previous);
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function render($request)
    {
        if ($request->expectsJson()) {
            return response()->json([
                'success' => false,
                'message' => $this->getMessage(),
                'errors' => $this->errors,
            ], $this->httpStatusCode);
        }

        return redirect()->back()
            ->withInput()
            ->withErrors($this->errors)
            ->with('error', $this->getMessage());
    }
}

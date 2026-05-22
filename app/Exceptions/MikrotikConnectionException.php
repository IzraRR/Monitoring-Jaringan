<?php

namespace App\Exceptions;

class MikrotikConnectionException extends BaseException
{
    protected $httpStatusCode = 503;
    protected $logLevel = 'error';

    public function __construct(
        string $message = 'Koneksi ke MikroTik gagal',
        array $context = [],
        ?\Throwable $previous = null
    ) {
        // Add MikroTik host to context
        $context['mikrotik_host'] = config('services.mikrotik.host');
        parent::__construct($message, $context, $previous);
    }

    public function render($request)
    {
        if ($request->expectsJson()) {
            return response()->json([
                'success' => false,
                'message' => $this->getMessage(),
                'error' => 'mikrotik_connection_failed',
            ], $this->httpStatusCode);
        }

        return redirect()->back()->with('error', $this->getMessage());
    }
}

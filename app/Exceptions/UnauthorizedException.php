<?php

namespace App\Exceptions;

class UnauthorizedException extends BaseException
{
    protected $httpStatusCode = 403;
    protected $logLevel = 'warning';

    public function __construct(
        string $message = 'Anda tidak memiliki akses untuk melakukan aksi ini',
        array $context = [],
        ?\Throwable $previous = null
    ) {
        // Add user info to context
        $context['user_id'] = auth()->id();
        parent::__construct($message, $context, $previous);
    }
}

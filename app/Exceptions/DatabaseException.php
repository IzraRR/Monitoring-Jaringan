<?php

namespace App\Exceptions;

class DatabaseException extends BaseException
{
    protected $httpStatusCode = 500;
    protected $logLevel = 'error';

    public function __construct(
        string $message = 'Terjadi kesalahan pada database',
        array $context = [],
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $context, $previous);
    }
}

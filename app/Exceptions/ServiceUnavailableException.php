<?php

namespace App\Exceptions;

class ServiceUnavailableException extends BaseException
{
    protected $httpStatusCode = 503;
    protected $logLevel = 'warning';
    protected $serviceName;

    public function __construct(
        string $serviceName = 'Service',
        string $message = 'Layanan tidak tersedia',
        array $context = [],
        ?\Throwable $previous = null
    ) {
        $this->serviceName = $serviceName;
        $context['service_name'] = $serviceName;
        
        $fullMessage = "{$serviceName}: {$message}";
        parent::__construct($fullMessage, $context, $previous);
    }

    public function getServiceName(): string
    {
        return $this->serviceName;
    }
}

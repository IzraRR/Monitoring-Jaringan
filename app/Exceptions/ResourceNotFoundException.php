<?php

namespace App\Exceptions;

class ResourceNotFoundException extends BaseException
{
    protected $httpStatusCode = 404;
    protected $logLevel = 'info';
    protected $resourceType;
    protected $resourceId;

    public function __construct(
        string $resourceType = 'Resource',
        $resourceId = null,
        string $message = '',
        ?\Throwable $previous = null
    ) {
        $this->resourceType = $resourceType;
        $this->resourceId = $resourceId;

        if (empty($message)) {
            $message = $resourceId 
                ? "{$resourceType} dengan ID '{$resourceId}' tidak ditemukan"
                : "{$resourceType} tidak ditemukan";
        }

        $context = [
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
        ];

        parent::__construct($message, $context, $previous);
    }

    public function getResourceType(): string
    {
        return $this->resourceType;
    }

    public function getResourceId()
    {
        return $this->resourceId;
    }
}

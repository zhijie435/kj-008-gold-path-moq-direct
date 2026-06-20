<?php

namespace App\Exceptions\Moq;

class MoqDirectShipException extends \InvalidArgumentException
{
    protected int $errorCode = 42200;

    protected int $httpStatus = 422;

    public function __construct(string $message = '', ?\Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }

    public function getErrorCode(): int
    {
        return $this->errorCode;
    }

    public function getHttpStatus(): int
    {
        return $this->httpStatus;
    }
}

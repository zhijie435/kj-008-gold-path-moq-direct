<?php

namespace App\Exceptions\Moq;

class InvalidRefundException extends MoqDirectShipException
{
    protected int $errorCode = 42206;
}

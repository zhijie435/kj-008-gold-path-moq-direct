<?php

namespace App\Exceptions\Moq;

class InvalidPaymentException extends MoqDirectShipException
{
    protected int $errorCode = 42205;
}

<?php

namespace App\Exceptions\Moq;

class InsufficientMoqException extends MoqDirectShipException
{
    protected int $errorCode = 42203;
}

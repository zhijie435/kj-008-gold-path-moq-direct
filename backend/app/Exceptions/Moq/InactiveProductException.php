<?php

namespace App\Exceptions\Moq;

class InactiveProductException extends MoqDirectShipException
{
    protected int $errorCode = 42204;
}

<?php

namespace App\Exceptions\Moq;

class InsufficientStockException extends MoqDirectShipException
{
    protected int $errorCode = 42202;
}

<?php

namespace App\Exceptions\Moq;

class ProductNotFoundException extends MoqDirectShipException
{
    protected int $errorCode = 40401;

    protected int $httpStatus = 404;
}

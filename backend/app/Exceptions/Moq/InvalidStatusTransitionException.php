<?php

namespace App\Exceptions\Moq;

class InvalidStatusTransitionException extends MoqDirectShipException
{
    protected int $errorCode = 42201;

    public static function for(string $current, string $target, string $entity = '订单'): static
    {
        return new static("当前{$entity}状态【{$current}】不允许变更为【{$target}】");
    }
}

<?php

namespace Tests\Unit;

use App\Exceptions\Moq\MoqDirectShipException;
use App\Exceptions\Moq\InvalidStatusTransitionException;
use App\Exceptions\Moq\ProductNotFoundException;
use App\Exceptions\Moq\InsufficientMoqException;
use App\Exceptions\Moq\InsufficientStockException;
use App\Exceptions\Moq\InactiveProductException;
use App\Exceptions\Moq\InvalidPaymentException;
use App\Exceptions\Moq\InvalidRefundException;
use Tests\TestCase;

class MoqExceptionsTest extends TestCase
{
    public function test_moq_direct_ship_exception_has_default_error_code_and_http_status()
    {
        $exception = new MoqDirectShipException('测试异常');

        $this->assertEquals(42200, $exception->getErrorCode());
        $this->assertEquals(422, $exception->getHttpStatus());
        $this->assertEquals('测试异常', $exception->getMessage());
    }

    public function test_invalid_status_transition_exception_for_order()
    {
        $exception = InvalidStatusTransitionException::for('pending', 'completed', '订单');

        $this->assertEquals(42201, $exception->getErrorCode());
        $this->assertEquals(422, $exception->getHttpStatus());
        $this->assertEquals('当前订单状态【pending】不允许变更为【completed】', $exception->getMessage());
    }

    public function test_invalid_status_transition_exception_for_shipment()
    {
        $exception = InvalidStatusTransitionException::for('delivered', 'shipped', '运单');

        $this->assertEquals(42201, $exception->getErrorCode());
        $this->assertEquals('当前运单状态【delivered】不允许变更为【shipped】', $exception->getMessage());
    }

    public function test_invalid_status_transition_exception_default_entity()
    {
        $exception = InvalidStatusTransitionException::for('shipped', 'cancelled');

        $this->assertEquals('当前订单状态【shipped】不允许变更为【cancelled】', $exception->getMessage());
    }

    public function test_product_not_found_exception()
    {
        $exception = new ProductNotFoundException('产品不存在: 123');

        $this->assertEquals(40401, $exception->getErrorCode());
        $this->assertEquals(404, $exception->getHttpStatus());
    }

    public function test_insufficient_moq_exception()
    {
        $exception = new InsufficientMoqException('产品最小起订量不足');

        $this->assertEquals(42203, $exception->getErrorCode());
        $this->assertEquals(422, $exception->getHttpStatus());
    }

    public function test_insufficient_stock_exception()
    {
        $exception = new InsufficientStockException('产品库存不足');

        $this->assertEquals(42202, $exception->getErrorCode());
        $this->assertEquals(422, $exception->getHttpStatus());
    }

    public function test_inactive_product_exception()
    {
        $exception = new InactiveProductException('产品已下架');

        $this->assertEquals(42204, $exception->getErrorCode());
        $this->assertEquals(422, $exception->getHttpStatus());
    }

    public function test_invalid_payment_exception()
    {
        $exception = new InvalidPaymentException('支付金额无效');

        $this->assertEquals(42205, $exception->getErrorCode());
        $this->assertEquals(422, $exception->getHttpStatus());
    }

    public function test_invalid_refund_exception()
    {
        $exception = new InvalidRefundException('退款金额无效');

        $this->assertEquals(42206, $exception->getErrorCode());
        $this->assertEquals(422, $exception->getHttpStatus());
    }

    public function test_all_exceptions_extend_moq_direct_ship_exception()
    {
        $exceptions = [
            new InvalidStatusTransitionException(),
            new ProductNotFoundException(),
            new InsufficientMoqException(),
            new InsufficientStockException(),
            new InactiveProductException(),
            new InvalidPaymentException(),
            new InvalidRefundException(),
        ];

        foreach ($exceptions as $exception) {
            $this->assertInstanceOf(MoqDirectShipException::class, $exception);
            $this->assertInstanceOf(\InvalidArgumentException::class, $exception);
        }
    }

    public function test_exception_can_be_thrown_and_caught()
    {
        $caught = false;

        try {
            throw new InsufficientStockException('库存不足');
        } catch (MoqDirectShipException $e) {
            $caught = true;
            $this->assertEquals(42202, $e->getErrorCode());
        }

        $this->assertTrue($caught);
    }
}

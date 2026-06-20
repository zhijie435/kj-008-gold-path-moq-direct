<?php

namespace Tests\Unit;

use App\Exceptions\Moq\MoqDirectShipException;
use App\Exceptions\Moq\ProductNotFoundException;
use App\Exceptions\Moq\InsufficientMoqException;
use App\Exceptions\Moq\InsufficientStockException;
use App\Exceptions\Moq\InactiveProductException;
use App\Exceptions\Moq\InvalidPaymentException;
use App\Exceptions\Moq\InvalidRefundException;
use App\Exceptions\Moq\InvalidStatusTransitionException;
use App\Models\Supplier;
use App\Models\Product;
use App\Models\MoqOrder;
use App\Models\MoqOrderItem;
use App\Models\Shipment;
use App\Services\MoqDirectShipService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MoqDirectShipServiceExtendedTest extends TestCase
{
    use RefreshDatabase;

    protected MoqDirectShipService $moqService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->moqService = app(MoqDirectShipService::class);
    }

    public function test_validate_moq_items_fails_without_product_id()
    {
        $this->expectException(MoqDirectShipException::class);
        $this->expectExceptionMessage('产品ID和数量不能为空');

        $items = [
            ['quantity' => 10],
        ];

        $this->moqService->validateMoqItems($items);
    }

    public function test_validate_moq_items_fails_without_quantity()
    {
        $this->expectException(MoqDirectShipException::class);
        $this->expectExceptionMessage('产品ID和数量不能为空');

        $supplier = Supplier::factory()->create();
        $product = Product::factory()->create(['supplier_id' => $supplier->id]);

        $items = [
            ['product_id' => $product->id],
        ];

        $this->moqService->validateMoqItems($items);
    }

    public function test_validate_moq_items_fails_when_product_not_found()
    {
        $this->expectException(ProductNotFoundException::class);

        $items = [
            ['product_id' => 99999, 'quantity' => 10],
        ];

        $this->moqService->validateMoqItems($items);
    }

    public function test_validate_moq_items_with_multiple_products()
    {
        $supplier = Supplier::factory()->create();
        $product1 = Product::factory()->create([
            'supplier_id' => $supplier->id,
            'moq' => 10,
            'stock_quantity' => 100,
            'is_active' => true,
        ]);
        $product2 = Product::factory()->create([
            'supplier_id' => $supplier->id,
            'moq' => 5,
            'stock_quantity' => 50,
            'is_active' => true,
        ]);

        $items = [
            ['product_id' => $product1->id, 'quantity' => 20],
            ['product_id' => $product2->id, 'quantity' => 10],
        ];

        $this->moqService->validateMoqItems($items);
        $this->assertTrue(true);
    }

    public function test_validate_moq_items_throws_specific_exception_types()
    {
        $supplier = Supplier::factory()->create();

        $inactiveProduct = Product::factory()->create([
            'supplier_id' => $supplier->id,
            'is_active' => false,
            'moq' => 10,
            'stock_quantity' => 100,
        ]);

        try {
            $this->moqService->validateMoqItems([
                ['product_id' => $inactiveProduct->id, 'quantity' => 20],
            ]);
            $this->fail('Expected InactiveProductException was not thrown');
        } catch (InactiveProductException $e) {
            $this->assertEquals(42204, $e->getErrorCode());
        }

        $activeProduct = Product::factory()->create([
            'supplier_id' => $supplier->id,
            'is_active' => true,
            'moq' => 50,
            'stock_quantity' => 100,
        ]);

        try {
            $this->moqService->validateMoqItems([
                ['product_id' => $activeProduct->id, 'quantity' => 10],
            ]);
            $this->fail('Expected InsufficientMoqException was not thrown');
        } catch (InsufficientMoqException $e) {
            $this->assertEquals(42203, $e->getErrorCode());
        }

        $stockProduct = Product::factory()->create([
            'supplier_id' => $supplier->id,
            'is_active' => true,
            'moq' => 10,
            'stock_quantity' => 5,
        ]);

        try {
            $this->moqService->validateMoqItems([
                ['product_id' => $stockProduct->id, 'quantity' => 20],
            ]);
            $this->fail('Expected InsufficientStockException was not thrown');
        } catch (InsufficientStockException $e) {
            $this->assertEquals(42202, $e->getErrorCode());
        }
    }

    public function test_create_order_with_custom_order_no()
    {
        $supplier = Supplier::factory()->create();
        $product = Product::factory()->create([
            'supplier_id' => $supplier->id,
            'moq' => 10,
            'price' => 100.00,
            'stock_quantity' => 100,
        ]);

        $orderData = [
            'order_no' => 'CUSTOM-ORDER-001',
            'supplier_id' => $supplier->id,
            'customer_name' => '张三',
            'customer_phone' => '13800138000',
            'province' => '广东省',
            'city' => '深圳市',
            'district' => '南山区',
            'address' => '科技园路1号',
            'items' => [
                ['product_id' => $product->id, 'quantity' => 20],
            ],
        ];

        $order = $this->moqService->createOrder($orderData);

        $this->assertEquals('CUSTOM-ORDER-001', $order->order_no);
    }

    public function test_create_order_auto_calculates_payable_amount()
    {
        $supplier = Supplier::factory()->create();
        $product = Product::factory()->create([
            'supplier_id' => $supplier->id,
            'moq' => 10,
            'price' => 100.00,
            'stock_quantity' => 100,
        ]);

        $orderData = [
            'supplier_id' => $supplier->id,
            'customer_name' => '张三',
            'customer_phone' => '13800138000',
            'province' => '广东省',
            'city' => '深圳市',
            'district' => '南山区',
            'address' => '科技园路1号',
            'shipping_fee' => 15.00,
            'discount_amount' => 50.00,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 10, 'unit_price' => 100.00],
            ],
        ];

        $order = $this->moqService->createOrder($orderData);

        $this->assertEquals(1000.00, $order->total_amount);
        $this->assertEquals(965.00, $order->payable_amount);
    }

    public function test_create_order_sets_default_status_and_source()
    {
        $supplier = Supplier::factory()->create();
        $product = Product::factory()->create([
            'supplier_id' => $supplier->id,
            'moq' => 10,
            'stock_quantity' => 100,
        ]);

        $orderData = [
            'supplier_id' => $supplier->id,
            'customer_name' => '张三',
            'customer_phone' => '13800138000',
            'province' => '广东省',
            'city' => '深圳市',
            'district' => '南山区',
            'address' => '科技园路1号',
            'items' => [
                ['product_id' => $product->id, 'quantity' => 20],
            ],
        ];

        $order = $this->moqService->createOrder($orderData);

        $this->assertEquals(MoqOrder::STATUS_PENDING, $order->status);
        $this->assertEquals(MoqOrder::SOURCE_MANUAL, $order->source);
    }

    public function test_process_order()
    {
        $supplier = Supplier::factory()->create();
        $product = Product::factory()->create(['supplier_id' => $supplier->id]);
        $order = MoqOrder::factory()->confirmed()->create(['supplier_id' => $supplier->id]);

        MoqOrderItem::factory()->create([
            'moq_order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 20,
        ]);

        $processedOrder = $this->moqService->processOrder($order);

        $this->assertEquals(MoqOrder::STATUS_PROCESSING, $processedOrder->status);
        $this->assertTrue($processedOrder->relationLoaded('items'));
        $this->assertTrue($processedOrder->relationLoaded('supplier'));
    }

    public function test_process_order_fails_when_invalid_status()
    {
        $this->expectException(InvalidStatusTransitionException::class);

        $order = MoqOrder::factory()->create(['status' => MoqOrder::STATUS_SHIPPED]);

        $this->moqService->processOrder($order);
    }

    public function test_ship_order_fails_when_not_shippable()
    {
        $this->expectException(InvalidStatusTransitionException::class);

        $order = MoqOrder::factory()->create(['status' => MoqOrder::STATUS_PENDING]);

        $shipmentData = [
            'carrier_code' => 'sf',
            'tracking_no' => 'SF1234567890',
            'items' => [],
        ];

        $this->moqService->shipOrder($order, $shipmentData);
    }

    public function test_ship_order_fails_when_ship_quantity_exceeds_order_quantity()
    {
        $this->expectException(MoqDirectShipException::class);
        $this->expectExceptionMessage('发货数量不能超过订购数量');

        $supplier = Supplier::factory()->create();
        $product = Product::factory()->create([
            'supplier_id' => $supplier->id,
            'moq' => 10,
            'stock_quantity' => 100,
        ]);

        $order = MoqOrder::factory()->confirmed()->create(['supplier_id' => $supplier->id]);

        $orderItem = MoqOrderItem::factory()->create([
            'moq_order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 20,
            'shipped_quantity' => 0,
        ]);

        $shipmentData = [
            'carrier_code' => 'sf',
            'tracking_no' => 'SF1234567890',
            'items' => [
                ['order_item_id' => $orderItem->id, 'quantity' => 30],
            ],
        ];

        $this->moqService->shipOrder($order, $shipmentData);
    }

    public function test_ship_order_with_carrier_name_mapping()
    {
        $supplier = Supplier::factory()->create();
        $product = Product::factory()->create([
            'supplier_id' => $supplier->id,
            'moq' => 10,
            'stock_quantity' => 100,
        ]);

        $order = MoqOrder::factory()->confirmed()->create(['supplier_id' => $supplier->id]);

        $orderItem = MoqOrderItem::factory()->create([
            'moq_order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 20,
            'shipped_quantity' => 0,
        ]);

        $shipmentData = [
            'carrier_code' => 'yto',
            'tracking_no' => 'YTO1234567890',
            'items' => [
                ['order_item_id' => $orderItem->id, 'quantity' => 20],
            ],
        ];

        $shipment = $this->moqService->shipOrder($order, $shipmentData);

        $this->assertEquals('圆通速递', $shipment->carrier_name);
        $this->assertNotNull($shipment->shipped_at);
    }

    public function test_update_order_adjusts_payable_amount()
    {
        $supplier = Supplier::factory()->create();
        $product = Product::factory()->create(['supplier_id' => $supplier->id]);
        $order = MoqOrder::factory()->create([
            'supplier_id' => $supplier->id,
            'shipping_fee' => 10.00,
            'discount_amount' => 0,
        ]);

        MoqOrderItem::factory()->create([
            'moq_order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 2,
            'unit_price' => 100.00,
            'total_price' => 200.00,
        ]);

        $updatedOrder = $this->moqService->updateOrder($order, [
            'shipping_fee' => 20.00,
            'discount_amount' => 30.00,
        ]);

        $this->assertEquals(200.00, $updatedOrder->total_amount);
        $this->assertEquals(20.00, $updatedOrder->shipping_fee);
        $this->assertEquals(30.00, $updatedOrder->discount_amount);
        $this->assertEquals(190.00, $updatedOrder->payable_amount);
    }

    public function test_delete_order_pending_status()
    {
        $order = MoqOrder::factory()->create(['status' => MoqOrder::STATUS_PENDING]);

        $this->moqService->deleteOrder($order);

        $this->assertSoftDeleted($order);
    }

    public function test_delete_order_cancelled_status()
    {
        $order = MoqOrder::factory()->cancelled()->create();

        $this->moqService->deleteOrder($order);

        $this->assertSoftDeleted($order);
    }

    public function test_delete_order_fails_when_confirmed()
    {
        $this->expectException(MoqDirectShipException::class);
        $this->expectExceptionMessage('只有待确认或已取消的订单才能删除');

        $order = MoqOrder::factory()->confirmed()->create();

        $this->moqService->deleteOrder($order);
    }

    public function test_delete_order_fails_when_shipped()
    {
        $this->expectException(MoqDirectShipException::class);

        $order = MoqOrder::factory()->shipped()->create();

        $this->moqService->deleteOrder($order);
    }

    public function test_pay_order_fails_when_cancelled()
    {
        $this->expectException(InvalidPaymentException::class);
        $this->expectExceptionMessage('当前订单状态不支持支付');

        $order = MoqOrder::factory()->cancelled()->create();

        $this->moqService->payOrder($order, 100.00, 'wechat');
    }

    public function test_pay_order_fails_when_refunded()
    {
        $this->expectException(InvalidPaymentException::class);

        $order = MoqOrder::factory()->create(['status' => MoqOrder::STATUS_REFUNDED]);

        $this->moqService->payOrder($order, 100.00, 'wechat');
    }

    public function test_refund_order_fails_when_pending()
    {
        $this->expectException(InvalidRefundException::class);
        $this->expectExceptionMessage('当前订单状态不支持退款');

        $order = MoqOrder::factory()->create([
            'status' => MoqOrder::STATUS_PENDING,
            'paid_amount' => 1000.00,
        ]);

        $this->moqService->refundOrder($order, 100.00, '测试退款');
    }

    public function test_refund_order_fails_with_zero_amount()
    {
        $this->expectException(InvalidRefundException::class);
        $this->expectExceptionMessage('退款金额无效');

        $order = MoqOrder::factory()->shipped()->create(['paid_amount' => 1000.00]);

        $this->moqService->refundOrder($order, 0, '测试退款');
    }

    public function test_refund_order_fails_with_negative_amount()
    {
        $this->expectException(InvalidRefundException::class);

        $order = MoqOrder::factory()->shipped()->create(['paid_amount' => 1000.00]);

        $this->moqService->refundOrder($order, -100.00, '测试退款');
    }

    public function test_deduct_stock_fails_when_insufficient()
    {
        $this->expectException(InsufficientStockException::class);

        $supplier = Supplier::factory()->create();
        $product = Product::factory()->create([
            'supplier_id' => $supplier->id,
            'stock_quantity' => 5,
        ]);

        $order = MoqOrder::factory()->create(['supplier_id' => $supplier->id]);

        MoqOrderItem::factory()->create([
            'moq_order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 20,
        ]);

        $this->moqService->confirmOrder($order);
    }

    public function test_get_order_list_with_filters()
    {
        $supplier1 = Supplier::factory()->create();
        $supplier2 = Supplier::factory()->create();

        MoqOrder::factory()->count(3)->create([
            'supplier_id' => $supplier1->id,
            'status' => MoqOrder::STATUS_PENDING,
            'customer_name' => '张三',
            'created_at' => now()->subDays(5),
        ]);

        MoqOrder::factory()->count(2)->create([
            'supplier_id' => $supplier2->id,
            'status' => MoqOrder::STATUS_COMPLETED,
            'customer_name' => '李四',
            'created_at' => now()->subDays(10),
        ]);

        $result = $this->moqService->getOrderList([
            'status' => MoqOrder::STATUS_PENDING,
            'per_page' => 10,
        ]);

        $this->assertEquals(3, $result->total());

        $result = $this->moqService->getOrderList([
            'keyword' => '张三',
            'per_page' => 10,
        ]);

        $this->assertEquals(3, $result->total());

        $result = $this->moqService->getOrderList([
            'supplier_id' => $supplier2->id,
            'per_page' => 10,
        ]);

        $this->assertEquals(2, $result->total());

        $result = $this->moqService->getOrderList([
            'start_date' => now()->subDays(7)->toDateString(),
            'per_page' => 10,
        ]);

        $this->assertEquals(3, $result->total());

        $result = $this->moqService->getOrderList([
            'source' => MoqOrder::SOURCE_MANUAL,
            'per_page' => 10,
        ]);

        $this->assertEquals(5, $result->total());
    }

    public function test_get_product_list_with_filters()
    {
        $supplier1 = Supplier::factory()->create();
        $supplier2 = Supplier::factory()->create();

        Product::factory()->count(3)->create([
            'supplier_id' => $supplier1->id,
            'name' => '测试产品A',
            'category' => '分类1',
            'is_active' => true,
            'moq' => 10,
            'stock_quantity' => 100,
            'safety_stock' => 10,
        ]);

        Product::factory()->count(2)->create([
            'supplier_id' => $supplier2->id,
            'name' => '测试产品B',
            'category' => '分类2',
            'is_active' => false,
            'moq' => 100,
            'stock_quantity' => 5,
            'safety_stock' => 10,
        ]);

        $result = $this->moqService->getProductList([
            'keyword' => '产品A',
            'per_page' => 10,
        ]);
        $this->assertEquals(3, $result->total());

        $result = $this->moqService->getProductList([
            'is_active' => true,
            'per_page' => 10,
        ]);
        $this->assertEquals(3, $result->total());

        $result = $this->moqService->getProductList([
            'is_low_stock' => true,
            'per_page' => 10,
        ]);
        $this->assertEquals(2, $result->total());

        $result = $this->moqService->getProductList([
            'moq_min' => 50,
            'per_page' => 10,
        ]);
        $this->assertEquals(2, $result->total());

        $result = $this->moqService->getProductList([
            'moq_max' => 50,
            'per_page' => 10,
        ]);
        $this->assertEquals(3, $result->total());

        $result = $this->moqService->getProductList([
            'category' => '分类2',
            'per_page' => 10,
        ]);
        $this->assertEquals(2, $result->total());
    }

    public function test_get_supplier_list_with_filters()
    {
        Supplier::factory()->count(3)->create([
            'name' => '供应商A',
            'province' => '广东省',
            'is_active' => true,
        ]);

        Supplier::factory()->count(2)->create([
            'name' => '供应商B',
            'province' => '浙江省',
            'is_active' => false,
        ]);

        $result = $this->moqService->getSupplierList([
            'keyword' => '供应商A',
            'per_page' => 10,
        ]);
        $this->assertEquals(3, $result->total());

        $result = $this->moqService->getSupplierList([
            'is_active' => true,
            'per_page' => 10,
        ]);
        $this->assertEquals(3, $result->total());

        $result = $this->moqService->getSupplierList([
            'province' => '广东省',
            'per_page' => 10,
        ]);
        $this->assertEquals(3, $result->total());
    }

    public function test_get_shipment_list_with_filters()
    {
        $supplier = Supplier::factory()->create();
        $order1 = MoqOrder::factory()->create(['supplier_id' => $supplier->id]);
        $order2 = MoqOrder::factory()->create(['supplier_id' => $supplier->id]);

        Shipment::factory()->count(3)->create([
            'moq_order_id' => $order1->id,
            'carrier_code' => 'sf',
            'status' => Shipment::STATUS_DELIVERED,
            'shipped_at' => now()->subDays(5),
        ]);

        Shipment::factory()->count(2)->create([
            'moq_order_id' => $order2->id,
            'carrier_code' => 'yto',
            'status' => Shipment::STATUS_IN_TRANSIT,
            'shipped_at' => now()->subDays(10),
        ]);

        $result = $this->moqService->getShipmentList([
            'status' => Shipment::STATUS_DELIVERED,
            'per_page' => 10,
        ]);
        $this->assertEquals(3, $result->total());

        $result = $this->moqService->getShipmentList([
            'carrier_code' => 'yto',
            'per_page' => 10,
        ]);
        $this->assertEquals(2, $result->total());

        $result = $this->moqService->getShipmentList([
            'moq_order_id' => $order1->id,
            'per_page' => 10,
        ]);
        $this->assertEquals(3, $result->total());
    }

    public function test_transition_shipment_pending_to_shipped()
    {
        $supplier = Supplier::factory()->create();
        $product = Product::factory()->create(['supplier_id' => $supplier->id]);
        $order = MoqOrder::factory()->confirmed()->create(['supplier_id' => $supplier->id]);

        MoqOrderItem::factory()->create([
            'moq_order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 20,
            'shipped_quantity' => 20,
        ]);

        $shipment = Shipment::factory()->create([
            'moq_order_id' => $order->id,
            'status' => Shipment::STATUS_PENDING,
        ]);

        $updatedShipment = $this->moqService->transitionShipment(
            $shipment,
            Shipment::STATUS_SHIPPED
        );

        $this->assertEquals(Shipment::STATUS_SHIPPED, $updatedShipment->status);
        $this->assertNotNull($updatedShipment->shipped_at);
        $this->assertEquals(MoqOrder::STATUS_SHIPPED, $order->fresh()->status);
    }

    public function test_transition_shipment_to_delivered_completes_order()
    {
        $supplier = Supplier::factory()->create();
        $product = Product::factory()->create(['supplier_id' => $supplier->id]);
        $order = MoqOrder::factory()->shipped()->create(['supplier_id' => $supplier->id]);

        MoqOrderItem::factory()->create([
            'moq_order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 20,
            'shipped_quantity' => 20,
        ]);

        $shipment = Shipment::factory()->shipped()->create([
            'moq_order_id' => $order->id,
        ]);

        $updatedShipment = $this->moqService->transitionShipment(
            $shipment,
            Shipment::STATUS_DELIVERED
        );

        $this->assertEquals(Shipment::STATUS_DELIVERED, $updatedShipment->status);
        $this->assertNotNull($updatedShipment->delivered_at);
        $this->assertEquals(MoqOrder::STATUS_COMPLETED, $order->fresh()->status);
    }

    public function test_transition_shipment_to_failed_with_reason()
    {
        $shipment = Shipment::factory()->shipped()->create();

        $updatedShipment = $this->moqService->transitionShipment(
            $shipment,
            Shipment::STATUS_FAILED,
            '客户拒收'
        );

        $this->assertEquals(Shipment::STATUS_FAILED, $updatedShipment->status);
        $this->assertStringContainsString('客户拒收', $updatedShipment->remark);
        $this->assertStringContainsString('派送失败原因', $updatedShipment->remark);
    }

    public function test_transition_shipment_to_returned_with_reason()
    {
        $shipment = Shipment::factory()->delivered()->create();

        $updatedShipment = $this->moqService->transitionShipment(
            $shipment,
            Shipment::STATUS_RETURNED,
            '商品损坏'
        );

        $this->assertEquals(Shipment::STATUS_RETURNED, $updatedShipment->status);
        $this->assertStringContainsString('商品损坏', $updatedShipment->remark);
        $this->assertStringContainsString('退回原因', $updatedShipment->remark);
    }

    public function test_create_shipment_partial()
    {
        $supplier = Supplier::factory()->create();
        $product = Product::factory()->create([
            'supplier_id' => $supplier->id,
            'moq' => 10,
            'stock_quantity' => 100,
        ]);

        $order = MoqOrder::factory()->confirmed()->create(['supplier_id' => $supplier->id]);

        $orderItem = MoqOrderItem::factory()->create([
            'moq_order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 20,
            'shipped_quantity' => 0,
        ]);

        $shipmentData = [
            'moq_order_id' => $order->id,
            'carrier_code' => 'sf',
            'tracking_no' => 'SF1234567890',
            'ship_items' => [
                ['order_item_id' => $orderItem->id, 'quantity' => 10],
            ],
        ];

        $shipment = $this->moqService->createShipment($shipmentData);

        $this->assertEquals(Shipment::STATUS_PENDING, $shipment->status);
        $this->assertEquals(10, $orderItem->fresh()->shipped_quantity);
        $this->assertEquals(MoqOrder::STATUS_PROCESSING, $order->fresh()->status);
    }

    public function test_create_shipment_full()
    {
        $supplier = Supplier::factory()->create();
        $product = Product::factory()->create([
            'supplier_id' => $supplier->id,
            'moq' => 10,
            'stock_quantity' => 100,
        ]);

        $order = MoqOrder::factory()->confirmed()->create(['supplier_id' => $supplier->id]);

        $orderItem = MoqOrderItem::factory()->create([
            'moq_order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 20,
            'shipped_quantity' => 0,
        ]);

        $shipmentData = [
            'moq_order_id' => $order->id,
            'carrier_code' => 'sf',
            'tracking_no' => 'SF1234567890',
            'ship_items' => [
                ['order_item_id' => $orderItem->id, 'quantity' => 20],
            ],
        ];

        $shipment = $this->moqService->createShipment($shipmentData);

        $this->assertEquals(MoqOrder::STATUS_SHIPPED, $order->fresh()->status);
        $this->assertNotNull($order->fresh()->shipped_at);
    }

    public function test_update_shipment_only_pending()
    {
        $shipment = Shipment::factory()->create(['status' => Shipment::STATUS_PENDING]);

        $updatedShipment = $this->moqService->updateShipment($shipment, [
            'tracking_no' => 'NEW-TRACKING-123',
            'remark' => '测试备注',
        ]);

        $this->assertEquals('NEW-TRACKING-123', $updatedShipment->tracking_no);
        $this->assertEquals('测试备注', $updatedShipment->remark);
    }

    public function test_update_shipment_fails_when_not_pending()
    {
        $this->expectException(MoqDirectShipException::class);
        $this->expectExceptionMessage('只有待发货状态的发货单才能编辑');

        $shipment = Shipment::factory()->shipped()->create();

        $this->moqService->updateShipment($shipment, ['tracking_no' => 'NEW-123']);
    }

    public function test_delete_shipment_only_pending()
    {
        $supplier = Supplier::factory()->create();
        $order = MoqOrder::factory()->shipped()->create(['supplier_id' => $supplier->id]);

        Shipment::factory()->create([
            'moq_order_id' => $order->id,
            'status' => Shipment::STATUS_SHIPPED,
        ]);

        $shipment = Shipment::factory()->create([
            'moq_order_id' => $order->id,
            'status' => Shipment::STATUS_PENDING,
        ]);

        $this->moqService->deleteShipment($shipment);

        $this->assertDatabaseMissing('shipments', ['id' => $shipment->id]);
    }

    public function test_delete_shipment_fails_when_not_pending()
    {
        $this->expectException(MoqDirectShipException::class);
        $this->expectExceptionMessage('只有待发货状态的发货单才能删除');

        $shipment = Shipment::factory()->shipped()->create();

        $this->moqService->deleteShipment($shipment);
    }

    public function test_delete_shipment_reverts_order_status_when_no_other_shipments()
    {
        $supplier = Supplier::factory()->create();
        $product = Product::factory()->create(['supplier_id' => $supplier->id]);
        $order = MoqOrder::factory()->confirmed()->create(['supplier_id' => $supplier->id]);

        MoqOrderItem::factory()->create([
            'moq_order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 20,
            'shipped_quantity' => 20,
        ]);

        $shipment = Shipment::factory()->create([
            'moq_order_id' => $order->id,
            'status' => Shipment::STATUS_PENDING,
        ]);

        $this->moqService->transitionShipment($shipment, Shipment::STATUS_SHIPPED);
        $this->assertEquals(MoqOrder::STATUS_SHIPPED, $order->fresh()->status);

        $shipment2 = Shipment::factory()->create([
            'moq_order_id' => $order->id,
            'status' => Shipment::STATUS_PENDING,
        ]);

        $this->moqService->deleteShipment($shipment2);

        $this->assertEquals(MoqOrder::STATUS_SHIPPED, $order->fresh()->status);
    }

    public function test_get_shipment_stats()
    {
        Shipment::factory()->count(2)->create(['status' => Shipment::STATUS_PENDING]);
        Shipment::factory()->count(3)->create(['status' => Shipment::STATUS_SHIPPED]);
        Shipment::factory()->count(2)->create(['status' => Shipment::STATUS_IN_TRANSIT]);
        Shipment::factory()->count(4)->create(['status' => Shipment::STATUS_DELIVERED]);
        Shipment::factory()->count(1)->create(['status' => Shipment::STATUS_FAILED]);
        Shipment::factory()->count(1)->create(['status' => Shipment::STATUS_RETURNED]);

        $stats = $this->moqService->getShipmentStats();

        $this->assertEquals(13, $stats['total']);
        $this->assertEquals(2, $stats['pending']);
        $this->assertEquals(5, $stats['shipped']);
        $this->assertEquals(4, $stats['delivered']);
        $this->assertEquals(2, $stats['exception']);
    }

    public function test_update_tracking_status_mappings()
    {
        $statusMap = [
            ['status_text' => '已签收', 'target_status' => Shipment::STATUS_DELIVERED, 'start_status' => Shipment::STATUS_SHIPPED],
            ['status_text' => '签收', 'target_status' => Shipment::STATUS_DELIVERED, 'start_status' => Shipment::STATUS_IN_TRANSIT],
            ['status_text' => '派送中', 'target_status' => Shipment::STATUS_IN_TRANSIT, 'start_status' => Shipment::STATUS_PICKED],
            ['status_text' => '运输中', 'target_status' => Shipment::STATUS_IN_TRANSIT, 'start_status' => Shipment::STATUS_SHIPPED],
            ['status_text' => '在途中', 'target_status' => Shipment::STATUS_IN_TRANSIT, 'start_status' => Shipment::STATUS_PICKED],
            ['status_text' => '已发出', 'target_status' => Shipment::STATUS_SHIPPED, 'start_status' => Shipment::STATUS_PENDING],
            ['status_text' => '已揽收', 'target_status' => Shipment::STATUS_PICKED, 'start_status' => Shipment::STATUS_PENDING],
            ['status_text' => '失败', 'target_status' => Shipment::STATUS_FAILED, 'start_status' => Shipment::STATUS_IN_TRANSIT],
            ['status_text' => '退回', 'target_status' => Shipment::STATUS_RETURNED, 'start_status' => Shipment::STATUS_DELIVERED],
        ];

        foreach ($statusMap as $mapping) {
            $shipment = Shipment::factory()->create(['status' => $mapping['start_status']]);

            $updatedShipment = $this->moqService->updateTracking($shipment, [
                'status' => $mapping['status_text'],
                'list' => [['time' => now()->toString(), 'content' => '测试']],
            ]);

            $this->assertEquals(
                $mapping['target_status'],
                $updatedShipment->status,
                "Status mapping failed for: {$mapping['status_text']}, expected {$mapping['target_status']}, got {$updatedShipment->status}"
            );
        }
    }

    public function test_calculate_order_totals_uses_product_price_when_no_unit_price()
    {
        $supplier = Supplier::factory()->create();
        $product = Product::factory()->create([
            'supplier_id' => $supplier->id,
            'price' => 150.00,
        ]);

        $items = [
            ['product_id' => $product->id, 'quantity' => 2],
        ];

        $totals = $this->moqService->calculateOrderTotals($items);

        $this->assertEquals(300.00, $totals['total_amount']);
    }

    public function test_calculate_order_totals_handles_missing_product()
    {
        $items = [
            ['product_id' => 99999, 'quantity' => 2],
        ];

        $totals = $this->moqService->calculateOrderTotals($items);

        $this->assertEquals(0, $totals['total_amount']);
    }

    public function test_ship_order_with_custom_shipment_no()
    {
        $supplier = Supplier::factory()->create();
        $product = Product::factory()->create([
            'supplier_id' => $supplier->id,
            'moq' => 10,
            'stock_quantity' => 100,
        ]);

        $order = MoqOrder::factory()->confirmed()->create(['supplier_id' => $supplier->id]);

        $orderItem = MoqOrderItem::factory()->create([
            'moq_order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 20,
            'shipped_quantity' => 0,
        ]);

        $shipmentData = [
            'shipment_no' => 'CUSTOM-SH-001',
            'carrier_code' => 'sf',
            'tracking_no' => 'SF1234567890',
            'items' => [
                ['order_item_id' => $orderItem->id, 'quantity' => 20],
            ],
        ];

        $shipment = $this->moqService->shipOrder($order, $shipmentData);

        $this->assertEquals('CUSTOM-SH-001', $shipment->shipment_no);
    }
}

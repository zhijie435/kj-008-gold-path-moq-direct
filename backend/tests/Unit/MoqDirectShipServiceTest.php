<?php

namespace Tests\Unit;

use App\Models\Supplier;
use App\Models\Product;
use App\Models\MoqOrder;
use App\Models\MoqOrderItem;
use App\Models\Shipment;
use App\Services\MoqDirectShipService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MoqDirectShipServiceTest extends TestCase
{
    use RefreshDatabase;

    protected MoqDirectShipService $moqService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->moqService = app(MoqDirectShipService::class);
    }

    public function test_generate_order_no()
    {
        $orderNo = $this->moqService->generateOrderNo();

        $this->assertNotEmpty($orderNo);
        $this->assertStringStartsWith('MOQ', $orderNo);
        $this->assertEquals(20, strlen($orderNo));
    }

    public function test_generate_shipment_no()
    {
        $shipmentNo = $this->moqService->generateShipmentNo();

        $this->assertNotEmpty($shipmentNo);
        $this->assertStringStartsWith('SH', $shipmentNo);
        $this->assertEquals(18, strlen($shipmentNo));
    }

    public function test_create_order_success()
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
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 20,
                    'unit_price' => 95.00,
                ],
            ],
        ];

        $order = $this->moqService->createOrder($orderData);

        $this->assertInstanceOf(MoqOrder::class, $order);
        $this->assertEquals('pending', $order->status);
        $this->assertEquals(1900.00, $order->total_amount);
        $this->assertEquals($supplier->id, $order->supplier_id);
        $this->assertCount(1, $order->items);
        $this->assertEquals(20, $order->items->first()->quantity);
        $this->assertEquals(95.00, $order->items->first()->unit_price);
    }

    public function test_create_order_fails_when_quantity_below_moq()
    {
        $this->expectException(\InvalidArgumentException::class);

        $supplier = Supplier::factory()->create();
        $product = Product::factory()->create([
            'supplier_id' => $supplier->id,
            'moq' => 100,
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
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 50,
                ],
            ],
        ];

        $this->moqService->createOrder($orderData);
    }

    public function test_create_order_fails_when_stock_insufficient()
    {
        $this->expectException(\InvalidArgumentException::class);

        $supplier = Supplier::factory()->create();
        $product = Product::factory()->create([
            'supplier_id' => $supplier->id,
            'moq' => 10,
            'price' => 100.00,
            'stock_quantity' => 50,
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
                [
                    'product_id' => $product->id,
                    'quantity' => 100,
                ],
            ],
        ];

        $this->moqService->createOrder($orderData);
    }

    public function test_create_order_fails_when_product_inactive()
    {
        $this->expectException(\InvalidArgumentException::class);

        $supplier = Supplier::factory()->create();
        $product = Product::factory()->create([
            'supplier_id' => $supplier->id,
            'moq' => 10,
            'price' => 100.00,
            'stock_quantity' => 100,
            'is_active' => false,
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
                [
                    'product_id' => $product->id,
                    'quantity' => 20,
                ],
            ],
        ];

        $this->moqService->createOrder($orderData);
    }

    public function test_confirm_order()
    {
        $supplier = Supplier::factory()->create();
        $product = Product::factory()->create([
            'supplier_id' => $supplier->id,
            'moq' => 10,
            'price' => 100.00,
            'stock_quantity' => 100,
        ]);

        $order = MoqOrder::factory()->create([
            'supplier_id' => $supplier->id,
            'status' => 'pending',
        ]);

        MoqOrderItem::factory()->create([
            'moq_order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 20,
            'unit_price' => 100.00,
            'total_price' => 2000.00,
        ]);

        $confirmedOrder = $this->moqService->confirmOrder($order);

        $this->assertEquals('confirmed', $confirmedOrder->status);
        $this->assertNotNull($confirmedOrder->confirmed_at);
        $this->assertEquals(80, $product->fresh()->stock_quantity);
    }

    public function test_confirm_order_fails_when_not_pending()
    {
        $this->expectException(\InvalidArgumentException::class);

        $order = MoqOrder::factory()->create(['status' => 'confirmed']);

        $this->moqService->confirmOrder($order);
    }

    public function test_ship_order()
    {
        $supplier = Supplier::factory()->create();
        $product = Product::factory()->create([
            'supplier_id' => $supplier->id,
            'moq' => 10,
            'price' => 100.00,
            'stock_quantity' => 100,
        ]);

        $order = MoqOrder::factory()->create([
            'supplier_id' => $supplier->id,
            'status' => 'confirmed',
        ]);

        $orderItem = MoqOrderItem::factory()->create([
            'moq_order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 20,
            'unit_price' => 100.00,
            'total_price' => 2000.00,
            'shipped_quantity' => 0,
        ]);

        $shipmentData = [
            'carrier_code' => 'sf',
            'tracking_no' => 'SF1234567890',
            'shipping_cost' => 15.00,
            'weight' => 5.5,
            'items' => [
                [
                    'order_item_id' => $orderItem->id,
                    'quantity' => 20,
                ],
            ],
        ];

        $shipment = $this->moqService->shipOrder($order, $shipmentData);

        $this->assertInstanceOf(Shipment::class, $shipment);
        $this->assertEquals('shipped', $shipment->status);
        $this->assertEquals('sf', $shipment->carrier_code);
        $this->assertEquals('SF1234567890', $shipment->tracking_no);
        $this->assertNotNull($shipment->shipped_at);
        $this->assertEquals('shipped', $order->fresh()->status);
        $this->assertEquals(20, $orderItem->fresh()->shipped_quantity);
    }

    public function test_complete_order()
    {
        $supplier = Supplier::factory()->create();
        $product = Product::factory()->create([
            'supplier_id' => $supplier->id,
            'moq' => 10,
        ]);

        $order = MoqOrder::factory()->create([
            'supplier_id' => $supplier->id,
            'status' => 'shipped',
        ]);

        MoqOrderItem::factory()->create([
            'moq_order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 20,
            'shipped_quantity' => 20,
        ]);

        Shipment::factory()->create([
            'moq_order_id' => $order->id,
            'status' => 'in_transit',
        ]);

        $completedOrder = $this->moqService->completeOrder($order);

        $this->assertEquals('completed', $completedOrder->status);
        $this->assertNotNull($completedOrder->completed_at);
    }

    public function test_complete_order_fails_when_not_fully_shipped()
    {
        $this->expectException(\InvalidArgumentException::class);

        $supplier = Supplier::factory()->create();
        $product = Product::factory()->create(['supplier_id' => $supplier->id]);

        $order = MoqOrder::factory()->create([
            'supplier_id' => $supplier->id,
            'status' => 'shipped',
        ]);

        MoqOrderItem::factory()->create([
            'moq_order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 20,
            'shipped_quantity' => 10,
        ]);

        $this->moqService->completeOrder($order);
    }

    public function test_cancel_order()
    {
        $supplier = Supplier::factory()->create();
        $product = Product::factory()->create([
            'supplier_id' => $supplier->id,
            'stock_quantity' => 100,
        ]);

        $order = MoqOrder::factory()->create([
            'supplier_id' => $supplier->id,
            'status' => 'pending',
        ]);

        MoqOrderItem::factory()->create([
            'moq_order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 20,
            'shipped_quantity' => 0,
        ]);

        $cancelledOrder = $this->moqService->cancelOrder($order, '客户取消');

        $this->assertEquals('cancelled', $cancelledOrder->status);
        $this->assertStringContainsString('客户取消', $cancelledOrder->internal_note);
    }

    public function test_cancel_confirmed_order_restores_stock()
    {
        $supplier = Supplier::factory()->create();
        $product = Product::factory()->create([
            'supplier_id' => $supplier->id,
            'stock_quantity' => 80,
        ]);

        $order = MoqOrder::factory()->create([
            'supplier_id' => $supplier->id,
            'status' => 'confirmed',
        ]);

        MoqOrderItem::factory()->create([
            'moq_order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 20,
            'shipped_quantity' => 0,
        ]);

        $this->moqService->cancelOrder($order, '客户取消');

        $this->assertEquals(100, $product->fresh()->stock_quantity);
    }

    public function test_cancel_order_fails_when_shipped()
    {
        $this->expectException(\InvalidArgumentException::class);

        $order = MoqOrder::factory()->create(['status' => 'shipped']);

        $this->moqService->cancelOrder($order, '测试取消');
    }

    public function test_pay_order()
    {
        $order = MoqOrder::factory()->create([
            'payable_amount' => 1000.00,
            'paid_amount' => 0,
            'status' => 'pending',
        ]);

        $paidOrder = $this->moqService->payOrder($order, 500.00, 'wechat');

        $this->assertEquals(500.00, $paidOrder->paid_amount);
        $this->assertEquals('wechat', $paidOrder->payment_method);
        $this->assertNull($paidOrder->paid_at);
    }

    public function test_pay_order_full_payment_sets_paid_at()
    {
        $order = MoqOrder::factory()->create([
            'payable_amount' => 1000.00,
            'paid_amount' => 0,
            'status' => 'pending',
        ]);

        $paidOrder = $this->moqService->payOrder($order, 1000.00, 'alipay');

        $this->assertEquals(1000.00, $paidOrder->paid_amount);
        $this->assertNotNull($paidOrder->paid_at);
    }

    public function test_pay_order_fails_when_over_pay()
    {
        $this->expectException(\InvalidArgumentException::class);

        $order = MoqOrder::factory()->create([
            'payable_amount' => 1000.00,
            'paid_amount' => 0,
        ]);

        $this->moqService->payOrder($order, 1500.00, 'wechat');
    }

    public function test_refund_order()
    {
        $order = MoqOrder::factory()->create([
            'payable_amount' => 1000.00,
            'paid_amount' => 1000.00,
            'status' => 'shipped',
        ]);

        $refundedOrder = $this->moqService->refundOrder($order, 300.00, '部分退款');

        $this->assertEquals(700.00, $refundedOrder->paid_amount);
        $this->assertStringContainsString('部分退款', $refundedOrder->internal_note);
    }

    public function test_refund_order_fails_when_invalid_amount()
    {
        $this->expectException(\InvalidArgumentException::class);

        $order = MoqOrder::factory()->create([
            'paid_amount' => 500.00,
            'status' => 'shipped',
        ]);

        $this->moqService->refundOrder($order, 1000.00, '测试');
    }

    public function test_get_statistics()
    {
        $supplier = Supplier::factory()->create();
        $product = Product::factory()->create(['supplier_id' => $supplier->id]);

        MoqOrder::factory()->count(3)->create([
            'supplier_id' => $supplier->id,
            'status' => 'completed',
            'payable_amount' => 1000.00,
            'paid_amount' => 1000.00,
            'created_at' => now()->subDays(5),
        ]);

        MoqOrder::factory()->count(2)->create([
            'supplier_id' => $supplier->id,
            'status' => 'pending',
            'payable_amount' => 500.00,
            'created_at' => now()->subDays(2),
        ]);

        $stats = $this->moqService->getStatistics([
            'date_start' => now()->subMonth()->toDateString(),
            'date_end' => now()->toDateString(),
        ]);

        $this->assertArrayHasKey('orders', $stats);
        $this->assertEquals(5, $stats['orders']['total']);
        $this->assertEquals(4000.00, $stats['orders']['total_amount']);
        $this->assertEquals(2, $stats['orders']['pending']);
        $this->assertEquals(3, $stats['orders']['completed']);
        $this->assertArrayHasKey('products', $stats);
        $this->assertArrayHasKey('suppliers', $stats);
        $this->assertArrayHasKey('shipments', $stats);
    }

    public function test_order_is_fully_shipped_attribute()
    {
        $supplier = Supplier::factory()->create();
        $product = Product::factory()->create(['supplier_id' => $supplier->id]);

        $order = MoqOrder::factory()->create(['supplier_id' => $supplier->id]);

        MoqOrderItem::factory()->create([
            'moq_order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 20,
            'shipped_quantity' => 20,
        ]);

        $this->assertTrue($order->is_fully_shipped);
        $this->assertEquals(20, $order->total_quantity);
        $this->assertEquals(20, $order->shipped_quantity);
    }

    public function test_order_is_fully_paid_attribute()
    {
        $order = MoqOrder::factory()->create([
            'payable_amount' => 1000.00,
            'paid_amount' => 1000.00,
        ]);

        $this->assertTrue($order->is_fully_paid);
        $this->assertEquals(0, $order->unpaid_amount);
    }

    public function test_product_is_low_stock_attribute()
    {
        $product = Product::factory()->create([
            'stock_quantity' => 5,
            'safety_stock' => 10,
        ]);

        $this->assertTrue($product->is_low_stock);

        $product->stock_quantity = 20;
        $this->assertFalse($product->is_low_stock);
    }

    public function test_supplier_full_address_attribute()
    {
        $supplier = Supplier::factory()->create([
            'province' => '广东省',
            'city' => '深圳市',
            'district' => '南山区',
            'address' => '科技园路1号',
        ]);

        $this->assertEquals('广东省深圳市南山区科技园路1号', $supplier->full_address);
    }

    public function test_shipment_carrier_label_attribute()
    {
        $shipment = Shipment::factory()->create([
            'carrier_code' => 'sf',
            'carrier_name' => '顺丰速运',
        ]);

        $this->assertEquals('顺丰速运', $shipment->carrier_label);
    }

    public function test_update_tracking_updates_status()
    {
        $shipment = Shipment::factory()->create([
            'status' => 'shipped',
        ]);

        $trackingData = [
            'status' => '已签收',
            'list' => [
                ['time' => '2024-01-01 10:00:00', 'content' => '快件已签收'],
            ],
        ];

        $updatedShipment = $this->moqService->updateTracking($shipment, $trackingData);

        $this->assertEquals('delivered', $updatedShipment->status);
        $this->assertNotNull($updatedShipment->delivered_at);
    }

    public function test_calculate_order_totals()
    {
        $supplier = Supplier::factory()->create();
        $product = Product::factory()->create([
            'supplier_id' => $supplier->id,
            'price' => 100.00,
        ]);

        $items = [
            ['product_id' => $product->id, 'quantity' => 2, 'unit_price' => 95.00],
            ['product_id' => $product->id, 'quantity' => 3, 'unit_price' => 90.00],
        ];

        $totals = $this->moqService->calculateOrderTotals($items);

        $this->assertEquals(460.00, $totals['total_amount']);
    }

    public function test_partial_ship_order_keeps_processing_status()
    {
        $supplier = Supplier::factory()->create();
        $product = Product::factory()->create([
            'supplier_id' => $supplier->id,
            'moq' => 10,
            'price' => 100.00,
            'stock_quantity' => 100,
        ]);

        $order = MoqOrder::factory()->create([
            'supplier_id' => $supplier->id,
            'status' => 'confirmed',
        ]);

        $orderItem = MoqOrderItem::factory()->create([
            'moq_order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 20,
            'unit_price' => 100.00,
            'total_price' => 2000.00,
            'shipped_quantity' => 0,
        ]);

        $shipmentData = [
            'carrier_code' => 'sf',
            'tracking_no' => 'SF1234567890',
            'shipping_cost' => 15.00,
            'weight' => 5.5,
            'items' => [
                [
                    'order_item_id' => $orderItem->id,
                    'quantity' => 10,
                ],
            ],
        ];

        $shipment = $this->moqService->shipOrder($order, $shipmentData);

        $this->assertInstanceOf(Shipment::class, $shipment);
        $this->assertEquals('processing', $order->fresh()->status);
        $this->assertEquals(10, $orderItem->fresh()->shipped_quantity);
        $this->assertFalse($order->fresh()->is_fully_shipped);
    }

    public function test_full_ship_order_updates_to_shipped_status()
    {
        $supplier = Supplier::factory()->create();
        $product = Product::factory()->create([
            'supplier_id' => $supplier->id,
            'moq' => 10,
            'price' => 100.00,
            'stock_quantity' => 100,
        ]);

        $order = MoqOrder::factory()->create([
            'supplier_id' => $supplier->id,
            'status' => 'confirmed',
        ]);

        $orderItem = MoqOrderItem::factory()->create([
            'moq_order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 20,
            'unit_price' => 100.00,
            'total_price' => 2000.00,
            'shipped_quantity' => 0,
        ]);

        $shipmentData = [
            'carrier_code' => 'sf',
            'tracking_no' => 'SF1234567890',
            'shipping_cost' => 15.00,
            'weight' => 5.5,
            'items' => [
                [
                    'order_item_id' => $orderItem->id,
                    'quantity' => 20,
                ],
            ],
        ];

        $shipment = $this->moqService->shipOrder($order, $shipmentData);

        $this->assertInstanceOf(Shipment::class, $shipment);
        $this->assertEquals('shipped', $order->fresh()->status);
        $this->assertEquals(20, $orderItem->fresh()->shipped_quantity);
        $this->assertTrue($order->fresh()->is_fully_shipped);
        $this->assertNotNull($order->fresh()->shipped_at);
    }

    public function test_cancel_processing_order()
    {
        $supplier = Supplier::factory()->create();
        $product = Product::factory()->create([
            'supplier_id' => $supplier->id,
            'stock_quantity' => 80,
        ]);

        $order = MoqOrder::factory()->create([
            'supplier_id' => $supplier->id,
            'status' => 'processing',
        ]);

        MoqOrderItem::factory()->create([
            'moq_order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 20,
            'shipped_quantity' => 0,
        ]);

        $cancelledOrder = $this->moqService->cancelOrder($order, '客户取消');

        $this->assertEquals('cancelled', $cancelledOrder->status);
        $this->assertStringContainsString('客户取消', $cancelledOrder->internal_note);
        $this->assertEquals(100, $product->fresh()->stock_quantity);
    }

    public function test_update_tracking_auto_completes_order_when_all_delivered()
    {
        $supplier = Supplier::factory()->create();
        $product = Product::factory()->create([
            'supplier_id' => $supplier->id,
        ]);

        $order = MoqOrder::factory()->create([
            'supplier_id' => $supplier->id,
            'status' => 'shipped',
        ]);

        MoqOrderItem::factory()->create([
            'moq_order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 20,
            'shipped_quantity' => 20,
        ]);

        $shipment = Shipment::factory()->create([
            'moq_order_id' => $order->id,
            'status' => 'in_transit',
        ]);

        $trackingData = [
            'status' => '已签收',
            'list' => [
                ['time' => '2024-01-01 10:00:00', 'content' => '快件已签收'],
            ],
        ];

        $updatedShipment = $this->moqService->updateTracking($shipment, $trackingData);

        $this->assertEquals('delivered', $updatedShipment->status);
        $this->assertNotNull($updatedShipment->delivered_at);
        $this->assertEquals('completed', $order->fresh()->status);
        $this->assertNotNull($order->fresh()->completed_at);
    }

    public function test_refund_shipped_order_sets_refunded_status()
    {
        $order = MoqOrder::factory()->create([
            'payable_amount' => 1000.00,
            'paid_amount' => 1000.00,
            'status' => 'shipped',
        ]);

        $refundedOrder = $this->moqService->refundOrder($order, 1000.00, '全额退款');

        $this->assertEquals(0, $refundedOrder->paid_amount);
        $this->assertEquals('refunded', $refundedOrder->status);
    }

    public function test_order_operations_return_loaded_relations()
    {
        $supplier = Supplier::factory()->create();
        $product = Product::factory()->create([
            'supplier_id' => $supplier->id,
            'moq' => 10,
            'price' => 100.00,
            'stock_quantity' => 100,
        ]);

        $order = MoqOrder::factory()->create([
            'supplier_id' => $supplier->id,
            'status' => 'pending',
        ]);

        $orderItem = MoqOrderItem::factory()->create([
            'moq_order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 20,
            'shipped_quantity' => 0,
        ]);

        $confirmedOrder = $this->moqService->confirmOrder($order);

        $this->assertTrue($confirmedOrder->relationLoaded('items'));
        $this->assertTrue($confirmedOrder->relationLoaded('supplier'));
        $this->assertTrue($confirmedOrder->relationLoaded('shipments'));
        $this->assertEquals('confirmed', $confirmedOrder->status);
    }
}

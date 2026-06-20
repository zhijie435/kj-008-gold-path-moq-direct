<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Supplier;
use App\Models\Product;
use App\Models\MoqOrder;
use App\Models\MoqOrderItem;
use App\Models\Shipment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MoqOrderApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
    }

    protected function authenticateAsAdmin()
    {
        $user = User::factory()->admin()->create();
        Sanctum::actingAs($user);
        return $user;
    }

    protected function authenticateAsOperator()
    {
        $user = User::factory()->operator()->create();
        Sanctum::actingAs($user);
        return $user;
    }

    public function test_index_returns_paginated_orders()
    {
        $this->authenticateAsAdmin();

        $supplier = Supplier::factory()->create();
        MoqOrder::factory()->count(15)->create(['supplier_id' => $supplier->id]);

        $response = $this->getJson('/api/v1/moq-direct-ship/orders?per_page=10');

        $response->assertOk();
        $response->assertJsonStructure([
            'code',
            'message',
            'data' => [
                'list',
                'total',
                'current_page',
                'per_page',
            ],
        ]);
        $response->assertJsonPath('data.total', 15);
        $response->assertJsonPath('data.per_page', 10);
        $response->assertJsonCount(10, 'data.list');
    }

    public function test_index_filters_by_status()
    {
        $this->authenticateAsAdmin();

        $supplier = Supplier::factory()->create();
        MoqOrder::factory()->count(5)->create([
            'supplier_id' => $supplier->id,
            'status' => MoqOrder::STATUS_PENDING,
        ]);
        MoqOrder::factory()->count(3)->create([
            'supplier_id' => $supplier->id,
            'status' => MoqOrder::STATUS_COMPLETED,
        ]);

        $response = $this->getJson('/api/v1/moq-direct-ship/orders?status=pending');

        $response->assertOk();
        $response->assertJsonPath('data.total', 5);
    }

    public function test_index_filters_by_keyword()
    {
        $this->authenticateAsAdmin();

        $supplier = Supplier::factory()->create();
        MoqOrder::factory()->create([
            'supplier_id' => $supplier->id,
            'customer_name' => '张三',
            'customer_phone' => '13800138000',
        ]);
        MoqOrder::factory()->count(3)->create(['supplier_id' => $supplier->id]);

        $response = $this->getJson('/api/v1/moq-direct-ship/orders?keyword=张三');

        $response->assertOk();
        $response->assertJsonPath('data.total', 1);
    }

    public function test_show_returns_order_details()
    {
        $this->authenticateAsAdmin();

        $supplier = Supplier::factory()->create();
        $product = Product::factory()->create(['supplier_id' => $supplier->id]);
        $order = MoqOrder::factory()->create(['supplier_id' => $supplier->id]);

        MoqOrderItem::factory()->create([
            'moq_order_id' => $order->id,
            'product_id' => $product->id,
        ]);

        Shipment::factory()->create(['moq_order_id' => $order->id]);

        $response = $this->getJson("/api/v1/moq-direct-ship/orders/{$order->id}");

        $response->assertOk();
        $response->assertJsonStructure([
            'code',
            'message',
            'data' => [
                'id',
                'order_no',
                'supplier',
                'items',
                'shipments',
            ],
        ]);
        $this->assertNotEmpty($response->json('data.supplier'));
        $this->assertNotEmpty($response->json('data.items'));
        $this->assertNotEmpty($response->json('data.shipments'));
    }

    public function test_store_creates_new_order()
    {
        $this->authenticateAsAdmin();

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
                [
                    'product_id' => $product->id,
                    'quantity' => 20,
                    'unit_price' => 95.00,
                    'remark' => '测试备注',
                ],
            ],
        ];

        $response = $this->postJson('/api/v1/moq-direct-ship/orders', $orderData);

        $response->assertCreated();
        $response->assertJsonPath('code', 0);
        $response->assertJsonPath('message', '订单创建成功');
        $response->assertJsonPath('data.status', MoqOrder::STATUS_PENDING);
        $response->assertJsonPath('data.total_amount', 1900.00);
        $response->assertJsonPath('data.payable_amount', 1865.00);
        $this->assertCount(1, $response->json('data.items'));
    }

    public function test_store_validates_required_fields()
    {
        $this->authenticateAsAdmin();

        $response = $this->postJson('/api/v1/moq-direct-ship/orders', [
            'items' => [],
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors([
            'customer_name',
            'customer_phone',
            'province',
            'city',
            'district',
            'address',
            'items',
        ]);
    }

    public function test_store_validates_items_minimum()
    {
        $this->authenticateAsAdmin();

        $supplier = Supplier::factory()->create();

        $orderData = [
            'supplier_id' => $supplier->id,
            'customer_name' => '张三',
            'customer_phone' => '13800138000',
            'province' => '广东省',
            'city' => '深圳市',
            'district' => '南山区',
            'address' => '科技园路1号',
            'items' => [],
        ];

        $response = $this->postJson('/api/v1/moq-direct-ship/orders', $orderData);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['items']);
    }

    public function test_store_fails_when_product_not_found()
    {
        $this->authenticateAsAdmin();

        $supplier = Supplier::factory()->create();

        $orderData = [
            'supplier_id' => $supplier->id,
            'customer_name' => '张三',
            'customer_phone' => '13800138000',
            'province' => '广东省',
            'city' => '深圳市',
            'district' => '南山区',
            'address' => '科技园路1号',
            'items' => [
                ['product_id' => 99999, 'quantity' => 10],
            ],
        ];

        $response = $this->postJson('/api/v1/moq-direct-ship/orders', $orderData);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['items.0.product_id']);
    }

    public function test_store_fails_when_moq_insufficient()
    {
        $this->authenticateAsAdmin();

        $supplier = Supplier::factory()->create();
        $product = Product::factory()->create([
            'supplier_id' => $supplier->id,
            'moq' => 100,
            'stock_quantity' => 200,
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
                ['product_id' => $product->id, 'quantity' => 50],
            ],
        ];

        $response = $this->postJson('/api/v1/moq-direct-ship/orders', $orderData);

        $response->assertUnprocessable();
        $response->assertJsonPath('message', '产品 ' . $product->name . ' 最小起订量为 100 ' . $product->unit . '，当前订购 50 ' . $product->unit);
    }

    public function test_update_modifies_order()
    {
        $this->authenticateAsAdmin();

        $supplier = Supplier::factory()->create();
        $order = MoqOrder::factory()->create(['supplier_id' => $supplier->id]);

        $updateData = [
            'customer_name' => '李四',
            'customer_phone' => '13900139000',
            'shipping_fee' => 25.00,
            'discount_amount' => 100.00,
        ];

        $response = $this->putJson("/api/v1/moq-direct-ship/orders/{$order->id}", $updateData);

        $response->assertOk();
        $response->assertJsonPath('message', '订单更新成功');
        $response->assertJsonPath('data.customer_name', '李四');
        $response->assertJsonPath('data.customer_phone', '13900139000');
    }

    public function test_destroy_deletes_order()
    {
        $this->authenticateAsAdmin();

        $supplier = Supplier::factory()->create();
        $order = MoqOrder::factory()->create([
            'supplier_id' => $supplier->id,
            'status' => MoqOrder::STATUS_PENDING,
        ]);

        $response = $this->deleteJson("/api/v1/moq-direct-ship/orders/{$order->id}");

        $response->assertOk();
        $response->assertJsonPath('message', '订单删除成功');
        $this->assertSoftDeleted($order);
    }

    public function test_destroy_fails_when_order_not_pending_or_cancelled()
    {
        $this->authenticateAsAdmin();

        $supplier = Supplier::factory()->create();
        $order = MoqOrder::factory()->confirmed()->create(['supplier_id' => $supplier->id]);

        $response = $this->deleteJson("/api/v1/moq-direct-ship/orders/{$order->id}");

        $response->assertUnprocessable();
        $response->assertJsonPath('message', '只有待确认或已取消的订单才能删除');
    }

    public function test_confirm_confirms_order()
    {
        $this->authenticateAsAdmin();

        $supplier = Supplier::factory()->create();
        $product = Product::factory()->create([
            'supplier_id' => $supplier->id,
            'stock_quantity' => 100,
        ]);
        $order = MoqOrder::factory()->create([
            'supplier_id' => $supplier->id,
            'status' => MoqOrder::STATUS_PENDING,
        ]);

        MoqOrderItem::factory()->create([
            'moq_order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 20,
        ]);

        $response = $this->postJson("/api/v1/moq-direct-ship/orders/{$order->id}/confirm");

        $response->assertOk();
        $response->assertJsonPath('message', '订单确认成功');
        $response->assertJsonPath('data.status', MoqOrder::STATUS_CONFIRMED);
        $response->assertJsonPath('data.confirmed_at', function ($value) {
            return !empty($value);
        });
        $this->assertEquals(80, $product->fresh()->stock_quantity);
    }

    public function test_confirm_fails_when_not_pending()
    {
        $this->authenticateAsAdmin();

        $supplier = Supplier::factory()->create();
        $order = MoqOrder::factory()->confirmed()->create(['supplier_id' => $supplier->id]);

        $response = $this->postJson("/api/v1/moq-direct-ship/orders/{$order->id}/confirm");

        $response->assertUnprocessable();
        $response->assertJsonPath('message', '当前订单状态【confirmed】不允许变更为【confirmed】');
    }

    public function test_cancel_cancels_order()
    {
        $this->authenticateAsAdmin();

        $supplier = Supplier::factory()->create();
        $order = MoqOrder::factory()->create([
            'supplier_id' => $supplier->id,
            'status' => MoqOrder::STATUS_PENDING,
        ]);

        $response = $this->postJson("/api/v1/moq-direct-ship/orders/{$order->id}/cancel", [
            'reason' => '客户取消订单',
        ]);

        $response->assertOk();
        $response->assertJsonPath('message', '订单取消成功');
        $response->assertJsonPath('data.status', MoqOrder::STATUS_CANCELLED);
        $this->assertStringContainsString('客户取消订单', $response->json('data.internal_note'));
    }

    public function test_cancel_restores_stock_for_confirmed_order()
    {
        $this->authenticateAsAdmin();

        $supplier = Supplier::factory()->create();
        $product = Product::factory()->create([
            'supplier_id' => $supplier->id,
            'stock_quantity' => 80,
        ]);
        $order = MoqOrder::factory()->confirmed()->create(['supplier_id' => $supplier->id]);

        MoqOrderItem::factory()->create([
            'moq_order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 20,
            'shipped_quantity' => 0,
        ]);

        $response = $this->postJson("/api/v1/moq-direct-ship/orders/{$order->id}/cancel", [
            'reason' => '客户取消',
        ]);

        $response->assertOk();
        $this->assertEquals(100, $product->fresh()->stock_quantity);
    }

    public function test_process_starts_processing()
    {
        $this->authenticateAsAdmin();

        $supplier = Supplier::factory()->create();
        $product = Product::factory()->create(['supplier_id' => $supplier->id]);
        $order = MoqOrder::factory()->confirmed()->create(['supplier_id' => $supplier->id]);

        MoqOrderItem::factory()->create([
            'moq_order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 20,
        ]);

        $response = $this->postJson("/api/v1/moq-direct-ship/orders/{$order->id}/process");

        $response->assertOk();
        $response->assertJsonPath('message', '订单已开始处理');
        $response->assertJsonPath('data.status', MoqOrder::STATUS_PROCESSING);
    }

    public function test_ship_creates_shipment()
    {
        $this->authenticateAsAdmin();

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
            'shipping_cost' => 15.00,
            'weight' => 5.5,
            'items' => [
                ['order_item_id' => $orderItem->id, 'quantity' => 20],
            ],
        ];

        $response = $this->postJson("/api/v1/moq-direct-ship/orders/{$order->id}/ship", $shipmentData);

        $response->assertCreated();
        $response->assertJsonPath('message', '发货成功');
        $response->assertJsonPath('data.status', Shipment::STATUS_SHIPPED);
        $response->assertJsonPath('data.carrier_code', 'sf');
        $response->assertJsonPath('data.tracking_no', 'SF1234567890');
        $this->assertEquals(20, $orderItem->fresh()->shipped_quantity);
        $this->assertEquals(MoqOrder::STATUS_SHIPPED, $order->fresh()->status);
    }

    public function test_ship_validates_required_fields()
    {
        $this->authenticateAsAdmin();

        $supplier = Supplier::factory()->create();
        $order = MoqOrder::factory()->confirmed()->create(['supplier_id' => $supplier->id]);

        $response = $this->postJson("/api/v1/moq-direct-ship/orders/{$order->id}/ship", []);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['carrier_code', 'tracking_no']);
    }

    public function test_complete_completes_order()
    {
        $this->authenticateAsAdmin();

        $supplier = Supplier::factory()->create();
        $product = Product::factory()->create(['supplier_id' => $supplier->id]);
        $order = MoqOrder::factory()->shipped()->create(['supplier_id' => $supplier->id]);

        MoqOrderItem::factory()->create([
            'moq_order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 20,
            'shipped_quantity' => 20,
        ]);

        Shipment::factory()->create([
            'moq_order_id' => $order->id,
            'status' => Shipment::STATUS_IN_TRANSIT,
        ]);

        $response = $this->postJson("/api/v1/moq-direct-ship/orders/{$order->id}/complete");

        $response->assertOk();
        $response->assertJsonPath('message', '订单已完成');
        $response->assertJsonPath('data.status', MoqOrder::STATUS_COMPLETED);
        $this->assertNotNull($order->fresh()->completed_at);
    }

    public function test_complete_fails_when_not_fully_shipped()
    {
        $this->authenticateAsAdmin();

        $supplier = Supplier::factory()->create();
        $product = Product::factory()->create(['supplier_id' => $supplier->id]);
        $order = MoqOrder::factory()->shipped()->create(['supplier_id' => $supplier->id]);

        MoqOrderItem::factory()->create([
            'moq_order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 20,
            'shipped_quantity' => 10,
        ]);

        $response = $this->postJson("/api/v1/moq-direct-ship/orders/{$order->id}/complete");

        $response->assertUnprocessable();
        $response->assertJsonPath('message', '订单尚未全部发货，无法完成');
    }

    public function test_pay_updates_payment()
    {
        $this->authenticateAsAdmin();

        $supplier = Supplier::factory()->create();
        $order = MoqOrder::factory()->create([
            'supplier_id' => $supplier->id,
            'payable_amount' => 1000.00,
            'paid_amount' => 0,
            'status' => MoqOrder::STATUS_PENDING,
        ]);

        $response = $this->postJson("/api/v1/moq-direct-ship/orders/{$order->id}/pay", [
            'amount' => 500.00,
            'payment_method' => 'wechat',
        ]);

        $response->assertOk();
        $response->assertJsonPath('message', '支付成功');
        $response->assertJsonPath('data.paid_amount', 500.00);
        $response->assertJsonPath('data.payment_method', 'wechat');
    }

    public function test_pay_validates_required_fields()
    {
        $this->authenticateAsAdmin();

        $supplier = Supplier::factory()->create();
        $order = MoqOrder::factory()->create(['supplier_id' => $supplier->id]);

        $response = $this->postJson("/api/v1/moq-direct-ship/orders/{$order->id}/pay", []);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['amount', 'payment_method']);
    }

    public function test_refund_processes_refund()
    {
        $this->authenticateAsAdmin();

        $supplier = Supplier::factory()->create();
        $order = MoqOrder::factory()->shipped()->create([
            'supplier_id' => $supplier->id,
            'payable_amount' => 1000.00,
            'paid_amount' => 1000.00,
        ]);

        $response = $this->postJson("/api/v1/moq-direct-ship/orders/{$order->id}/refund", [
            'amount' => 300.00,
            'reason' => '部分商品退款',
        ]);

        $response->assertOk();
        $response->assertJsonPath('message', '退款成功');
        $response->assertJsonPath('data.paid_amount', 700.00);
    }

    public function test_refund_fails_with_invalid_amount()
    {
        $this->authenticateAsAdmin();

        $supplier = Supplier::factory()->create();
        $order = MoqOrder::factory()->shipped()->create([
            'supplier_id' => $supplier->id,
            'paid_amount' => 500.00,
        ]);

        $response = $this->postJson("/api/v1/moq-direct-ship/orders/{$order->id}/refund", [
            'amount' => 1000.00,
        ]);

        $response->assertUnprocessable();
        $response->assertJsonPath('message', '退款金额无效');
    }

    public function test_statistics_returns_stats()
    {
        $this->authenticateAsAdmin();

        $supplier = Supplier::factory()->create();
        $product = Product::factory()->create(['supplier_id' => $supplier->id]);

        MoqOrder::factory()->count(3)->create([
            'supplier_id' => $supplier->id,
            'status' => MoqOrder::STATUS_COMPLETED,
            'payable_amount' => 1000.00,
            'paid_amount' => 1000.00,
            'created_at' => now()->subDays(5),
        ]);

        MoqOrder::factory()->count(2)->create([
            'supplier_id' => $supplier->id,
            'status' => MoqOrder::STATUS_PENDING,
            'payable_amount' => 500.00,
            'created_at' => now()->subDays(2),
        ]);

        $response = $this->getJson('/api/v1/moq-direct-ship/orders/statistics');

        $response->assertOk();
        $response->assertJsonStructure([
            'code',
            'message',
            'data' => [
                'orders' => [
                    'total',
                    'total_amount',
                    'paid_amount',
                    'unpaid_amount',
                    'pending',
                    'confirmed',
                    'processing',
                    'shipped',
                    'completed',
                    'cancelled',
                ],
                'products',
                'suppliers',
                'shipments',
                'date_range',
            ],
        ]);
        $response->assertJsonPath('data.orders.total', 5);
        $response->assertJsonPath('data.orders.total_amount', 4000.00);
        $response->assertJsonPath('data.orders.pending', 2);
        $response->assertJsonPath('data.orders.completed', 3);
    }

    public function test_get_status_options()
    {
        $this->authenticateAsAdmin();

        $response = $this->getJson('/api/v1/moq-direct-ship/orders/status-options');

        $response->assertOk();
        $response->assertJsonCount(7, 'data');
        $response->assertJsonPath('data.0.value', 'pending');
        $response->assertJsonPath('data.0.label', '待确认');
    }

    public function test_get_payment_options()
    {
        $this->authenticateAsAdmin();

        $response = $this->getJson('/api/v1/moq-direct-ship/orders/payment-options');

        $response->assertOk();
        $response->assertJsonCount(5, 'data');
    }

    public function test_requires_authentication()
    {
        $response = $this->getJson('/api/v1/moq-direct-ship/orders');

        $response->assertUnauthorized();
    }

    public function test_unauthorized_user_cannot_manage_orders()
    {
        $user = User::factory()->viewer()->create();
        Sanctum::actingAs($user);

        $supplier = Supplier::factory()->create();
        $product = Product::factory()->create(['supplier_id' => $supplier->id]);
        $order = MoqOrder::factory()->create(['supplier_id' => $supplier->id]);

        $response = $this->postJson("/api/v1/moq-direct-ship/orders/{$order->id}/confirm");

        $response->assertForbidden();
    }
}

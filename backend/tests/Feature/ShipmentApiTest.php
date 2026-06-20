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

class ShipmentApiTest extends TestCase
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

    public function test_index_returns_paginated_shipments()
    {
        $this->authenticateAsAdmin();

        $supplier = Supplier::factory()->create();
        $order = MoqOrder::factory()->create(['supplier_id' => $supplier->id]);

        Shipment::factory()->count(15)->create(['moq_order_id' => $order->id]);

        $response = $this->getJson('/api/v1/moq-direct-ship/shipments?per_page=10');

        $response->assertOk();
        $response->assertJsonStructure([
            'code',
            'message',
            'data' => [
                'list',
                'total',
                'current_page',
                'per_page',
                'stats',
            ],
        ]);
        $response->assertJsonPath('data.total', 15);
        $response->assertJsonPath('data.per_page', 10);
        $response->assertJsonCount(10, 'data.list');
        $this->assertArrayHasKey('total', $response->json('data.stats'));
        $this->assertArrayHasKey('pending', $response->json('data.stats'));
    }

    public function test_index_filters_by_status()
    {
        $this->authenticateAsAdmin();

        $supplier = Supplier::factory()->create();
        $order = MoqOrder::factory()->create(['supplier_id' => $supplier->id]);

        Shipment::factory()->count(5)->create([
            'moq_order_id' => $order->id,
            'status' => Shipment::STATUS_PENDING,
        ]);
        Shipment::factory()->count(3)->delivered()->create([
            'moq_order_id' => $order->id,
        ]);

        $response = $this->getJson('/api/v1/moq-direct-ship/shipments?status=delivered');

        $response->assertOk();
        $response->assertJsonPath('data.total', 3);
    }

    public function test_index_filters_by_carrier()
    {
        $this->authenticateAsAdmin();

        $supplier = Supplier::factory()->create();
        $order = MoqOrder::factory()->create(['supplier_id' => $supplier->id]);

        Shipment::factory()->count(5)->create([
            'moq_order_id' => $order->id,
            'carrier_code' => 'sf',
        ]);
        Shipment::factory()->count(3)->create([
            'moq_order_id' => $order->id,
            'carrier_code' => 'yto',
        ]);

        $response = $this->getJson('/api/v1/moq-direct-ship/shipments?carrier_code=yto');

        $response->assertOk();
        $response->assertJsonPath('data.total', 3);
    }

    public function test_index_filters_by_keyword()
    {
        $this->authenticateAsAdmin();

        $supplier = Supplier::factory()->create();
        $order = MoqOrder::factory()->create(['supplier_id' => $supplier->id]);

        Shipment::factory()->create([
            'moq_order_id' => $order->id,
            'tracking_no' => 'SF1234567890',
            'shipment_no' => 'SH20240101ABC',
        ]);
        Shipment::factory()->count(3)->create(['moq_order_id' => $order->id]);

        $response = $this->getJson('/api/v1/moq-direct-ship/shipments?keyword=SF1234567890');

        $response->assertOk();
        $response->assertJsonPath('data.total', 1);
    }

    public function test_show_returns_shipment_details()
    {
        $this->authenticateAsAdmin();

        $supplier = Supplier::factory()->create();
        $product = Product::factory()->create(['supplier_id' => $supplier->id]);
        $order = MoqOrder::factory()->create(['supplier_id' => $supplier->id]);

        MoqOrderItem::factory()->create([
            'moq_order_id' => $order->id,
            'product_id' => $product->id,
        ]);

        $shipment = Shipment::factory()->create(['moq_order_id' => $order->id]);

        $response = $this->getJson("/api/v1/moq-direct-ship/shipments/{$shipment->id}");

        $response->assertOk();
        $response->assertJsonStructure([
            'code',
            'message',
            'data' => [
                'id',
                'shipment_no',
                'carrier_code',
                'carrier_name',
                'tracking_no',
                'status',
                'order' => [
                    'items',
                    'supplier',
                ],
            ],
        ]);
    }

    public function test_store_creates_shipment()
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
            'moq_order_id' => $order->id,
            'carrier_code' => 'sf',
            'tracking_no' => 'SF1234567890',
            'shipping_cost' => 15.00,
            'weight' => 5.5,
            'package_count' => 2,
            'ship_items' => [
                ['order_item_id' => $orderItem->id, 'quantity' => 10],
            ],
        ];

        $response = $this->postJson('/api/v1/moq-direct-ship/shipments', $shipmentData);

        $response->assertCreated();
        $response->assertJsonPath('code', 0);
        $response->assertJsonPath('message', '发货单创建成功');
        $response->assertJsonPath('data.status', Shipment::STATUS_PENDING);
        $response->assertJsonPath('data.carrier_code', 'sf');
        $response->assertJsonPath('data.tracking_no', 'SF1234567890');
        $this->assertEquals(10, $orderItem->fresh()->shipped_quantity);
        $this->assertEquals(MoqOrder::STATUS_PROCESSING, $order->fresh()->status);
    }

    public function test_store_validates_required_fields()
    {
        $this->authenticateAsAdmin();

        $response = $this->postJson('/api/v1/moq-direct-ship/shipments', []);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors([
            'moq_order_id',
            'carrier_code',
            'tracking_no',
        ]);
    }

    public function test_store_fails_when_order_not_shippable()
    {
        $this->authenticateAsAdmin();

        $supplier = Supplier::factory()->create();
        $order = MoqOrder::factory()->create([
            'supplier_id' => $supplier->id,
            'status' => MoqOrder::STATUS_PENDING,
        ]);

        $shipmentData = [
            'moq_order_id' => $order->id,
            'carrier_code' => 'sf',
            'tracking_no' => 'SF1234567890',
        ];

        $response = $this->postJson('/api/v1/moq-direct-ship/shipments', $shipmentData);

        $response->assertUnprocessable();
        $response->assertJsonPath('message', '当前订单状态【pending】不允许变更为【shipped】');
    }

    public function test_update_modifies_shipment()
    {
        $this->authenticateAsAdmin();

        $supplier = Supplier::factory()->create();
        $order = MoqOrder::factory()->create(['supplier_id' => $supplier->id]);
        $shipment = Shipment::factory()->create([
            'moq_order_id' => $order->id,
            'status' => Shipment::STATUS_PENDING,
        ]);

        $updateData = [
            'tracking_no' => 'SF9999999999',
            'remark' => '测试备注',
        ];

        $response = $this->putJson("/api/v1/moq-direct-ship/shipments/{$shipment->id}", $updateData);

        $response->assertOk();
        $response->assertJsonPath('message', '发货单更新成功');
        $response->assertJsonPath('data.tracking_no', 'SF9999999999');
        $response->assertJsonPath('data.remark', '测试备注');
    }

    public function test_update_fails_when_not_pending()
    {
        $this->authenticateAsAdmin();

        $supplier = Supplier::factory()->create();
        $order = MoqOrder::factory()->create(['supplier_id' => $supplier->id]);
        $shipment = Shipment::factory()->shipped()->create(['moq_order_id' => $order->id]);

        $response = $this->putJson("/api/v1/moq-direct-ship/shipments/{$shipment->id}", [
            'tracking_no' => 'NEW-123',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonPath('message', '只有待发货状态的发货单才能编辑');
    }

    public function test_destroy_deletes_shipment()
    {
        $this->authenticateAsAdmin();

        $supplier = Supplier::factory()->create();
        $order = MoqOrder::factory()->create(['supplier_id' => $supplier->id]);
        $shipment = Shipment::factory()->create([
            'moq_order_id' => $order->id,
            'status' => Shipment::STATUS_PENDING,
        ]);

        $response = $this->deleteJson("/api/v1/moq-direct-ship/shipments/{$shipment->id}");

        $response->assertOk();
        $response->assertJsonPath('message', '发货单删除成功');
        $this->assertDatabaseMissing('shipments', ['id' => $shipment->id]);
    }

    public function test_destroy_fails_when_not_pending()
    {
        $this->authenticateAsAdmin();

        $supplier = Supplier::factory()->create();
        $order = MoqOrder::factory()->create(['supplier_id' => $supplier->id]);
        $shipment = Shipment::factory()->shipped()->create(['moq_order_id' => $order->id]);

        $response = $this->deleteJson("/api/v1/moq-direct-ship/shipments/{$shipment->id}");

        $response->assertUnprocessable();
        $response->assertJsonPath('message', '只有待发货状态的发货单才能删除');
    }

    public function test_ship_marks_shipment_as_shipped()
    {
        $this->authenticateAsAdmin();

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

        $response = $this->postJson("/api/v1/moq-direct-ship/shipments/{$shipment->id}/ship");

        $response->assertOk();
        $response->assertJsonPath('message', '发货成功');
        $response->assertJsonPath('data.status', Shipment::STATUS_SHIPPED);
        $this->assertNotNull($shipment->fresh()->shipped_at);
    }

    public function test_mark_picked()
    {
        $this->authenticateAsAdmin();

        $supplier = Supplier::factory()->create();
        $order = MoqOrder::factory()->create(['supplier_id' => $supplier->id]);
        $shipment = Shipment::factory()->create([
            'moq_order_id' => $order->id,
            'status' => Shipment::STATUS_PENDING,
        ]);

        $response = $this->postJson("/api/v1/moq-direct-ship/shipments/{$shipment->id}/mark-picked");

        $response->assertOk();
        $response->assertJsonPath('message', '已标记揽收');
        $response->assertJsonPath('data.status', Shipment::STATUS_PICKED);
    }

    public function test_mark_in_transit()
    {
        $this->authenticateAsAdmin();

        $supplier = Supplier::factory()->create();
        $order = MoqOrder::factory()->create(['supplier_id' => $supplier->id]);
        $shipment = Shipment::factory()->shipped()->create(['moq_order_id' => $order->id]);

        $response = $this->postJson("/api/v1/moq-direct-ship/shipments/{$shipment->id}/mark-in-transit");

        $response->assertOk();
        $response->assertJsonPath('message', '已标记运输中');
        $response->assertJsonPath('data.status', Shipment::STATUS_IN_TRANSIT);
    }

    public function test_mark_delivered()
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

        $shipment = Shipment::factory()->shipped()->create(['moq_order_id' => $order->id]);

        $response = $this->postJson("/api/v1/moq-direct-ship/shipments/{$shipment->id}/mark-delivered");

        $response->assertOk();
        $response->assertJsonPath('message', '已标记签收');
        $response->assertJsonPath('data.status', Shipment::STATUS_DELIVERED);
        $this->assertNotNull($shipment->fresh()->delivered_at);
        $this->assertEquals(MoqOrder::STATUS_COMPLETED, $order->fresh()->status);
    }

    public function test_mark_failed()
    {
        $this->authenticateAsAdmin();

        $supplier = Supplier::factory()->create();
        $order = MoqOrder::factory()->create(['supplier_id' => $supplier->id]);
        $shipment = Shipment::factory()->shipped()->create(['moq_order_id' => $order->id]);

        $response = $this->postJson("/api/v1/moq-direct-ship/shipments/{$shipment->id}/mark-failed", [
            'reason' => '客户拒收',
        ]);

        $response->assertOk();
        $response->assertJsonPath('message', '已标记派送失败');
        $response->assertJsonPath('data.status', Shipment::STATUS_FAILED);
        $this->assertStringContainsString('客户拒收', $response->json('data.remark'));
    }

    public function test_mark_returned()
    {
        $this->authenticateAsAdmin();

        $supplier = Supplier::factory()->create();
        $order = MoqOrder::factory()->create(['supplier_id' => $supplier->id]);
        $shipment = Shipment::factory()->delivered()->create(['moq_order_id' => $order->id]);

        $response = $this->postJson("/api/v1/moq-direct-ship/shipments/{$shipment->id}/mark-returned", [
            'reason' => '商品损坏',
        ]);

        $response->assertOk();
        $response->assertJsonPath('message', '已标记退回');
        $response->assertJsonPath('data.status', Shipment::STATUS_RETURNED);
        $this->assertStringContainsString('商品损坏', $response->json('data.remark'));
    }

    public function test_mark_failed_fails_for_invalid_transition()
    {
        $this->authenticateAsAdmin();

        $supplier = Supplier::factory()->create();
        $order = MoqOrder::factory()->create(['supplier_id' => $supplier->id]);
        $shipment = Shipment::factory()->delivered()->create(['moq_order_id' => $order->id]);

        $response = $this->postJson("/api/v1/moq-direct-ship/shipments/{$shipment->id}/mark-failed");

        $response->assertUnprocessable();
        $response->assertJsonPath('message', '当前运单状态【delivered】不允许变更为【failed】');
    }

    public function test_update_tracking()
    {
        $this->authenticateAsAdmin();

        $supplier = Supplier::factory()->create();
        $order = MoqOrder::factory()->create(['supplier_id' => $supplier->id]);
        $shipment = Shipment::factory()->shipped()->create(['moq_order_id' => $order->id]);

        $trackingData = [
            'status' => '已签收',
            'list' => [
                ['time' => '2024-01-01 10:00:00', 'content' => '快件已签收'],
            ],
        ];

        $response = $this->postJson("/api/v1/moq-direct-ship/shipments/{$shipment->id}/update-tracking", [
            'tracking_data' => $trackingData,
        ]);

        $response->assertOk();
        $response->assertJsonPath('message', '物流信息更新成功');
        $response->assertJsonPath('data.status', Shipment::STATUS_DELIVERED);
        $this->assertNotNull($shipment->fresh()->delivered_at);
    }

    public function test_update_tracking_with_status_param()
    {
        $this->authenticateAsAdmin();

        $supplier = Supplier::factory()->create();
        $order = MoqOrder::factory()->create(['supplier_id' => $supplier->id]);
        $shipment = Shipment::factory()->create([
            'moq_order_id' => $order->id,
            'status' => Shipment::STATUS_PENDING,
        ]);

        $response = $this->postJson("/api/v1/moq-direct-ship/shipments/{$shipment->id}/update-tracking", [
            'status' => '运输中',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.status', Shipment::STATUS_IN_TRANSIT);
    }

    public function test_get_status_options()
    {
        $this->authenticateAsAdmin();

        $response = $this->getJson('/api/v1/moq-direct-ship/shipments/status-options');

        $response->assertOk();
        $response->assertJsonCount(7, 'data');
        $response->assertJsonPath('data.0.value', 'pending');
        $response->assertJsonPath('data.0.label', '待发货');
    }

    public function test_get_carrier_options()
    {
        $this->authenticateAsAdmin();

        $response = $this->getJson('/api/v1/moq-direct-ship/shipments/carrier-options');

        $response->assertOk();
        $response->assertJsonCount(8, 'data');
        $response->assertJsonPath('data.0.value', 'sf');
        $response->assertJsonPath('data.0.label', '顺丰速运');
    }

    public function test_requires_authentication()
    {
        $response = $this->getJson('/api/v1/moq-direct-ship/shipments');

        $response->assertUnauthorized();
    }

    public function test_store_creates_full_shipment_updates_order_to_shipped()
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
            'moq_order_id' => $order->id,
            'carrier_code' => 'sf',
            'tracking_no' => 'SF1234567890',
            'ship_items' => [
                ['order_item_id' => $orderItem->id, 'quantity' => 20],
            ],
        ];

        $response = $this->postJson('/api/v1/moq-direct-ship/shipments', $shipmentData);

        $response->assertCreated();
        $this->assertEquals(20, $orderItem->fresh()->shipped_quantity);
        $this->assertEquals(MoqOrder::STATUS_SHIPPED, $order->fresh()->status);
        $this->assertNotNull($order->fresh()->shipped_at);
    }
}

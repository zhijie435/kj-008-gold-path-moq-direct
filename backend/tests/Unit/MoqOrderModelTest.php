<?php

namespace Tests\Unit;

use App\Models\MoqOrder;
use App\Models\Supplier;
use App\Models\Product;
use App\Models\MoqOrderItem;
use App\Exceptions\Moq\InvalidStatusTransitionException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MoqOrderModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_status_transitions_pending_to_confirmed()
    {
        $order = MoqOrder::factory()->create(['status' => MoqOrder::STATUS_PENDING]);

        $this->assertTrue($order->canTransitionTo(MoqOrder::STATUS_CONFIRMED));
        $this->assertTrue($order->canTransitionTo(MoqOrder::STATUS_CANCELLED));
        $this->assertFalse($order->canTransitionTo(MoqOrder::STATUS_SHIPPED));
        $this->assertFalse($order->canTransitionTo(MoqOrder::STATUS_COMPLETED));
    }

    public function test_status_transitions_confirmed_to_processing_or_shipped()
    {
        $order = MoqOrder::factory()->create(['status' => MoqOrder::STATUS_CONFIRMED]);

        $this->assertTrue($order->canTransitionTo(MoqOrder::STATUS_PROCESSING));
        $this->assertTrue($order->canTransitionTo(MoqOrder::STATUS_SHIPPED));
        $this->assertTrue($order->canTransitionTo(MoqOrder::STATUS_CANCELLED));
        $this->assertFalse($order->canTransitionTo(MoqOrder::STATUS_COMPLETED));
    }

    public function test_status_transitions_processing_to_shipped()
    {
        $order = MoqOrder::factory()->create(['status' => MoqOrder::STATUS_PROCESSING]);

        $this->assertTrue($order->canTransitionTo(MoqOrder::STATUS_SHIPPED));
        $this->assertTrue($order->canTransitionTo(MoqOrder::STATUS_PROCESSING));
        $this->assertTrue($order->canTransitionTo(MoqOrder::STATUS_CANCELLED));
        $this->assertFalse($order->canTransitionTo(MoqOrder::STATUS_CONFIRMED));
    }

    public function test_status_transitions_shipped_to_completed_or_refunded()
    {
        $order = MoqOrder::factory()->create(['status' => MoqOrder::STATUS_SHIPPED]);

        $this->assertTrue($order->canTransitionTo(MoqOrder::STATUS_COMPLETED));
        $this->assertTrue($order->canTransitionTo(MoqOrder::STATUS_REFUNDED));
        $this->assertTrue($order->canTransitionTo(MoqOrder::STATUS_SHIPPED));
        $this->assertFalse($order->canTransitionTo(MoqOrder::STATUS_CANCELLED));
    }

    public function test_status_transitions_completed_to_refunded()
    {
        $order = MoqOrder::factory()->create(['status' => MoqOrder::STATUS_COMPLETED]);

        $this->assertTrue($order->canTransitionTo(MoqOrder::STATUS_REFUNDED));
        $this->assertFalse($order->canTransitionTo(MoqOrder::STATUS_SHIPPED));
        $this->assertFalse($order->canTransitionTo(MoqOrder::STATUS_CANCELLED));
    }

    public function test_status_transitions_terminal_states_allow_no_transitions()
    {
        $cancelledOrder = MoqOrder::factory()->create(['status' => MoqOrder::STATUS_CANCELLED]);
        $refundedOrder = MoqOrder::factory()->create(['status' => MoqOrder::STATUS_REFUNDED]);

        $this->assertEmpty(MoqOrder::STATUS_TRANSITIONS[MoqOrder::STATUS_CANCELLED]);
        $this->assertEmpty(MoqOrder::STATUS_TRANSITIONS[MoqOrder::STATUS_REFUNDED]);
        $this->assertFalse($cancelledOrder->canTransitionTo(MoqOrder::STATUS_CONFIRMED));
        $this->assertFalse($refundedOrder->canTransitionTo(MoqOrder::STATUS_COMPLETED));
    }

    public function test_assert_can_transition_to_throws_exception_for_invalid_transition()
    {
        $this->expectException(InvalidStatusTransitionException::class);

        $order = MoqOrder::factory()->create(['status' => MoqOrder::STATUS_SHIPPED]);
        $order->assertCanTransitionTo(MoqOrder::STATUS_CANCELLED);
    }

    public function test_is_shippable_check()
    {
        $pendingOrder = MoqOrder::factory()->create(['status' => MoqOrder::STATUS_PENDING]);
        $confirmedOrder = MoqOrder::factory()->create(['status' => MoqOrder::STATUS_CONFIRMED]);
        $processingOrder = MoqOrder::factory()->create(['status' => MoqOrder::STATUS_PROCESSING]);
        $shippedOrder = MoqOrder::factory()->create(['status' => MoqOrder::STATUS_SHIPPED]);
        $completedOrder = MoqOrder::factory()->create(['status' => MoqOrder::STATUS_COMPLETED]);

        $this->assertFalse($pendingOrder->isShippable());
        $this->assertTrue($confirmedOrder->isShippable());
        $this->assertTrue($processingOrder->isShippable());
        $this->assertTrue($shippedOrder->isShippable());
        $this->assertFalse($completedOrder->isShippable());
    }

    public function test_is_payable_check()
    {
        $pendingOrder = MoqOrder::factory()->create(['status' => MoqOrder::STATUS_PENDING]);
        $shippedOrder = MoqOrder::factory()->create(['status' => MoqOrder::STATUS_SHIPPED]);
        $cancelledOrder = MoqOrder::factory()->create(['status' => MoqOrder::STATUS_CANCELLED]);
        $refundedOrder = MoqOrder::factory()->create(['status' => MoqOrder::STATUS_REFUNDED]);

        $this->assertTrue($pendingOrder->isPayable());
        $this->assertTrue($shippedOrder->isPayable());
        $this->assertFalse($cancelledOrder->isPayable());
        $this->assertFalse($refundedOrder->isPayable());
    }

    public function test_is_refundable_check()
    {
        $pendingOrder = MoqOrder::factory()->create(['status' => MoqOrder::STATUS_PENDING]);
        $shippedOrder = MoqOrder::factory()->create(['status' => MoqOrder::STATUS_SHIPPED]);
        $completedOrder = MoqOrder::factory()->create(['status' => MoqOrder::STATUS_COMPLETED]);
        $cancelledOrder = MoqOrder::factory()->create(['status' => MoqOrder::STATUS_CANCELLED]);

        $this->assertFalse($pendingOrder->isRefundable());
        $this->assertTrue($shippedOrder->isRefundable());
        $this->assertTrue($completedOrder->isRefundable());
        $this->assertFalse($cancelledOrder->isRefundable());
    }

    public function test_is_cancellable_check()
    {
        $pendingOrder = MoqOrder::factory()->create(['status' => MoqOrder::STATUS_PENDING]);
        $confirmedOrder = MoqOrder::factory()->create(['status' => MoqOrder::STATUS_CONFIRMED]);
        $processingOrder = MoqOrder::factory()->create(['status' => MoqOrder::STATUS_PROCESSING]);
        $shippedOrder = MoqOrder::factory()->create(['status' => MoqOrder::STATUS_SHIPPED]);

        $this->assertTrue($pendingOrder->isCancellable());
        $this->assertTrue($confirmedOrder->isCancellable());
        $this->assertTrue($processingOrder->isCancellable());
        $this->assertFalse($shippedOrder->isCancellable());
    }

    public function test_is_completable_check()
    {
        $pendingOrder = MoqOrder::factory()->create(['status' => MoqOrder::STATUS_PENDING]);
        $shippedOrder = MoqOrder::factory()->create(['status' => MoqOrder::STATUS_SHIPPED]);
        $completedOrder = MoqOrder::factory()->create(['status' => MoqOrder::STATUS_COMPLETED]);

        $this->assertFalse($pendingOrder->isCompletable());
        $this->assertTrue($shippedOrder->isCompletable());
        $this->assertFalse($completedOrder->isCompletable());
    }

    public function test_full_address_accessor()
    {
        $order = MoqOrder::factory()->create([
            'province' => '广东省',
            'city' => '深圳市',
            'district' => '南山区',
            'address' => '科技园路1号',
        ]);

        $this->assertEquals('广东省深圳市南山区科技园路1号', $order->full_address);
    }

    public function test_full_address_accessor_with_null_fields()
    {
        $order = MoqOrder::factory()->create([
            'province' => '广东省',
            'city' => '深圳市',
            'district' => '',
            'address' => '科技园路1号',
        ]);

        $this->assertEquals('广东省深圳市科技园路1号', $order->full_address);
    }

    public function test_total_quantity_accessor()
    {
        $supplier = Supplier::factory()->create();
        $product = Product::factory()->create(['supplier_id' => $supplier->id]);
        $order = MoqOrder::factory()->create(['supplier_id' => $supplier->id]);

        MoqOrderItem::factory()->create([
            'moq_order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 10,
        ]);

        MoqOrderItem::factory()->create([
            'moq_order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 20,
        ]);

        $this->assertEquals(30, $order->total_quantity);
    }

    public function test_shipped_quantity_accessor()
    {
        $supplier = Supplier::factory()->create();
        $product = Product::factory()->create(['supplier_id' => $supplier->id]);
        $order = MoqOrder::factory()->create(['supplier_id' => $supplier->id]);

        MoqOrderItem::factory()->create([
            'moq_order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 20,
            'shipped_quantity' => 10,
        ]);

        MoqOrderItem::factory()->create([
            'moq_order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 30,
            'shipped_quantity' => 30,
        ]);

        $this->assertEquals(40, $order->shipped_quantity);
    }

    public function test_unpaid_amount_accessor()
    {
        $order = MoqOrder::factory()->create([
            'payable_amount' => 1000.00,
            'paid_amount' => 600.00,
        ]);

        $this->assertEquals(400.00, $order->unpaid_amount);
    }

    public function test_unpaid_amount_accessor_never_negative()
    {
        $order = MoqOrder::factory()->create([
            'payable_amount' => 1000.00,
            'paid_amount' => 1500.00,
        ]);

        $this->assertEquals(0, $order->unpaid_amount);
    }

    public function test_is_fully_shipped_accessor()
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

        MoqOrderItem::factory()->create([
            'moq_order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 30,
            'shipped_quantity' => 10,
        ]);

        $this->assertFalse($order->fresh()->is_fully_shipped);
    }

    public function test_is_fully_paid_accessor()
    {
        $order1 = MoqOrder::factory()->create([
            'payable_amount' => 1000.00,
            'paid_amount' => 1000.00,
        ]);

        $order2 = MoqOrder::factory()->create([
            'payable_amount' => 1000.00,
            'paid_amount' => 999.99,
        ]);

        $order3 = MoqOrder::factory()->create([
            'payable_amount' => 1000.00,
            'paid_amount' => 1500.00,
        ]);

        $this->assertTrue($order1->is_fully_paid);
        $this->assertFalse($order2->is_fully_paid);
        $this->assertTrue($order3->is_fully_paid);
    }

    public function test_status_label_and_color_accessors()
    {
        $order = MoqOrder::factory()->create(['status' => MoqOrder::STATUS_PENDING]);
        $this->assertEquals('待确认', $order->status_label);
        $this->assertEquals('warning', $order->status_color);

        $order->status = MoqOrder::STATUS_SHIPPED;
        $this->assertEquals('已发货', $order->status_label);
        $this->assertEquals('success', $order->status_color);

        $order->status = MoqOrder::STATUS_CANCELLED;
        $this->assertEquals('已取消', $order->status_label);
        $this->assertEquals('danger', $order->status_color);
    }

    public function test_get_status_options_returns_all_statuses()
    {
        $options = MoqOrder::getStatusOptions();

        $this->assertCount(7, $options);
        $this->assertEquals(['value', 'label', 'color'], array_keys($options[0]));
    }

    public function test_get_payment_options_returns_all_methods()
    {
        $options = MoqOrder::getPaymentOptions();

        $this->assertCount(5, $options);
        $this->assertEquals(['value', 'label'], array_keys($options[0]));
    }

    public function test_order_relationship_with_supplier()
    {
        $supplier = Supplier::factory()->create();
        $order = MoqOrder::factory()->create(['supplier_id' => $supplier->id]);

        $this->assertTrue($order->supplier->is($supplier));
    }

    public function test_order_relationship_with_items()
    {
        $supplier = Supplier::factory()->create();
        $product = Product::factory()->create(['supplier_id' => $supplier->id]);
        $order = MoqOrder::factory()->create(['supplier_id' => $supplier->id]);

        $item1 = MoqOrderItem::factory()->create([
            'moq_order_id' => $order->id,
            'product_id' => $product->id,
        ]);

        $item2 = MoqOrderItem::factory()->create([
            'moq_order_id' => $order->id,
            'product_id' => $product->id,
        ]);

        $this->assertCount(2, $order->items);
        $this->assertTrue($order->items->contains($item1));
        $this->assertTrue($order->items->contains($item2));
    }

    public function test_invalid_status_transition_exception_message()
    {
        $this->expectException(InvalidStatusTransitionException::class);
        $this->expectExceptionMessage('当前订单状态【shipped】不允许变更为【cancelled】');

        $order = MoqOrder::factory()->create(['status' => MoqOrder::STATUS_SHIPPED]);
        $order->assertCanTransitionTo(MoqOrder::STATUS_CANCELLED);
    }
}

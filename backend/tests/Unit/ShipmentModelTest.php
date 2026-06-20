<?php

namespace Tests\Unit;

use App\Models\Shipment;
use App\Models\MoqOrder;
use App\Models\Supplier;
use App\Models\Product;
use App\Models\MoqOrderItem;
use App\Exceptions\Moq\InvalidStatusTransitionException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShipmentModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_status_transitions_pending()
    {
        $shipment = Shipment::factory()->create(['status' => Shipment::STATUS_PENDING]);

        $this->assertTrue($shipment->canTransitionTo(Shipment::STATUS_PICKED));
        $this->assertTrue($shipment->canTransitionTo(Shipment::STATUS_SHIPPED));
        $this->assertTrue($shipment->canTransitionTo(Shipment::STATUS_FAILED));
        $this->assertFalse($shipment->canTransitionTo(Shipment::STATUS_DELIVERED));
        $this->assertFalse($shipment->canTransitionTo(Shipment::STATUS_RETURNED));
    }

    public function test_status_transitions_picked()
    {
        $shipment = Shipment::factory()->create(['status' => Shipment::STATUS_PICKED]);

        $this->assertTrue($shipment->canTransitionTo(Shipment::STATUS_SHIPPED));
        $this->assertTrue($shipment->canTransitionTo(Shipment::STATUS_IN_TRANSIT));
        $this->assertTrue($shipment->canTransitionTo(Shipment::STATUS_DELIVERED));
        $this->assertTrue($shipment->canTransitionTo(Shipment::STATUS_FAILED));
        $this->assertFalse($shipment->canTransitionTo(Shipment::STATUS_RETURNED));
    }

    public function test_status_transitions_shipped()
    {
        $shipment = Shipment::factory()->create(['status' => Shipment::STATUS_SHIPPED]);

        $this->assertTrue($shipment->canTransitionTo(Shipment::STATUS_IN_TRANSIT));
        $this->assertTrue($shipment->canTransitionTo(Shipment::STATUS_DELIVERED));
        $this->assertTrue($shipment->canTransitionTo(Shipment::STATUS_FAILED));
        $this->assertTrue($shipment->canTransitionTo(Shipment::STATUS_RETURNED));
        $this->assertFalse($shipment->canTransitionTo(Shipment::STATUS_PICKED));
    }

    public function test_status_transitions_in_transit()
    {
        $shipment = Shipment::factory()->create(['status' => Shipment::STATUS_IN_TRANSIT]);

        $this->assertTrue($shipment->canTransitionTo(Shipment::STATUS_DELIVERED));
        $this->assertTrue($shipment->canTransitionTo(Shipment::STATUS_FAILED));
        $this->assertTrue($shipment->canTransitionTo(Shipment::STATUS_RETURNED));
        $this->assertFalse($shipment->canTransitionTo(Shipment::STATUS_PICKED));
    }

    public function test_status_transitions_delivered()
    {
        $shipment = Shipment::factory()->create(['status' => Shipment::STATUS_DELIVERED]);

        $this->assertTrue($shipment->canTransitionTo(Shipment::STATUS_RETURNED));
        $this->assertFalse($shipment->canTransitionTo(Shipment::STATUS_SHIPPED));
        $this->assertFalse($shipment->canTransitionTo(Shipment::STATUS_FAILED));
    }

    public function test_status_transitions_failed()
    {
        $shipment = Shipment::factory()->create(['status' => Shipment::STATUS_FAILED]);

        $this->assertTrue($shipment->canTransitionTo(Shipment::STATUS_RETURNED));
        $this->assertFalse($shipment->canTransitionTo(Shipment::STATUS_DELIVERED));
        $this->assertFalse($shipment->canTransitionTo(Shipment::STATUS_SHIPPED));
    }

    public function test_status_transitions_returned_is_terminal()
    {
        $shipment = Shipment::factory()->create(['status' => Shipment::STATUS_RETURNED]);

        $this->assertEmpty(Shipment::STATUS_TRANSITIONS[Shipment::STATUS_RETURNED]);
        $this->assertFalse($shipment->canTransitionTo(Shipment::STATUS_DELIVERED));
        $this->assertFalse($shipment->canTransitionTo(Shipment::STATUS_SHIPPED));
    }

    public function test_assert_can_transition_to_throws_exception_for_invalid_transition()
    {
        $this->expectException(InvalidStatusTransitionException::class);
        $this->expectExceptionMessage('当前运单状态【delivered】不允许变更为【shipped】');

        $shipment = Shipment::factory()->create(['status' => Shipment::STATUS_DELIVERED]);
        $shipment->assertCanTransitionTo(Shipment::STATUS_SHIPPED);
    }

    public function test_is_trackable_check()
    {
        $shipmentWithTracking = Shipment::factory()->create(['tracking_no' => 'SF1234567890']);
        $shipmentWithoutTracking = Shipment::factory()->create(['tracking_no' => '']);

        $this->assertTrue($shipmentWithTracking->isTrackable());
        $this->assertFalse($shipmentWithoutTracking->isTrackable());
    }

    public function test_is_delivered_check()
    {
        $deliveredShipment = Shipment::factory()->delivered()->create();
        $shippedShipment = Shipment::factory()->shipped()->create();

        $this->assertTrue($deliveredShipment->isDelivered());
        $this->assertFalse($shippedShipment->isDelivered());
    }

    public function test_is_in_transit_check()
    {
        $pendingShipment = Shipment::factory()->create(['status' => Shipment::STATUS_PENDING]);
        $shippedShipment = Shipment::factory()->create(['status' => Shipment::STATUS_SHIPPED]);
        $inTransitShipment = Shipment::factory()->create(['status' => Shipment::STATUS_IN_TRANSIT]);
        $deliveredShipment = Shipment::factory()->create(['status' => Shipment::STATUS_DELIVERED]);

        $this->assertFalse($pendingShipment->isInTransit());
        $this->assertTrue($shippedShipment->isInTransit());
        $this->assertTrue($inTransitShipment->isInTransit());
        $this->assertFalse($deliveredShipment->isInTransit());
    }

    public function test_status_label_and_color_accessors()
    {
        $shipment = Shipment::factory()->create(['status' => Shipment::STATUS_PENDING]);
        $this->assertEquals('待发货', $shipment->status_label);
        $this->assertEquals('warning', $shipment->status_color);

        $shipment->status = Shipment::STATUS_IN_TRANSIT;
        $this->assertEquals('运输中', $shipment->status_label);
        $this->assertEquals('info', $shipment->status_color);

        $shipment->status = Shipment::STATUS_DELIVERED;
        $this->assertEquals('已签收', $shipment->status_label);
        $this->assertEquals('success', $shipment->status_color);

        $shipment->status = Shipment::STATUS_FAILED;
        $this->assertEquals('派送失败', $shipment->status_label);
        $this->assertEquals('danger', $shipment->status_color);
    }

    public function test_carrier_label_accessor()
    {
        $shipment = Shipment::factory()->create([
            'carrier_code' => 'sf',
            'carrier_name' => '顺丰速运',
        ]);

        $this->assertEquals('顺丰速运', $shipment->carrier_label);
    }

    public function test_carrier_label_accessor_uses_carrier_code_when_no_name()
    {
        $shipment = Shipment::factory()->create([
            'carrier_code' => 'yto',
            'carrier_name' => '',
        ]);

        $this->assertEquals('圆通速递', $shipment->carrier_label);
    }

    public function test_tracking_url_accessor()
    {
        $shipment = Shipment::factory()->create([
            'carrier_code' => 'sf',
            'tracking_no' => 'SF1234567890',
        ]);

        $this->assertStringContainsString('sf-express.com', $shipment->tracking_url);
        $this->assertStringContainsString('SF1234567890', $shipment->tracking_url);
    }

    public function test_tracking_url_accessor_for_ems()
    {
        $shipment = Shipment::factory()->create([
            'carrier_code' => 'ems',
            'tracking_no' => 'EE123456789CN',
        ]);

        $this->assertStringContainsString('ems.com.cn', $shipment->tracking_url);
        $this->assertStringContainsString('EE123456789CN', $shipment->tracking_url);
    }

    public function test_tracking_url_accessor_returns_null_for_unknown_carrier()
    {
        $shipment = Shipment::factory()->create([
            'carrier_code' => 'unknown',
            'carrier_name' => '未知快递',
            'tracking_no' => '123456',
        ]);

        $this->assertNull($shipment->tracking_url);
    }

    public function test_tracking_url_accessor_returns_null_when_no_tracking_no()
    {
        $shipment = Shipment::factory()->create([
            'carrier_code' => 'sf',
            'tracking_no' => '',
        ]);

        $this->assertNull($shipment->tracking_url);
    }

    public function test_shipment_relationship_with_order()
    {
        $supplier = Supplier::factory()->create();
        $order = MoqOrder::factory()->create(['supplier_id' => $supplier->id]);
        $shipment = Shipment::factory()->create(['moq_order_id' => $order->id]);

        $this->assertTrue($shipment->order->is($order));
    }

    public function test_get_status_options_returns_all_statuses()
    {
        $options = Shipment::getStatusOptions();

        $this->assertCount(7, $options);
        $this->assertEquals(['value', 'label', 'color'], array_keys($options[0]));
    }

    public function test_get_carrier_options_returns_all_carriers()
    {
        $options = Shipment::getCarrierOptions();

        $this->assertCount(8, $options);
        $this->assertEquals(['value', 'label'], array_keys($options[0]));
    }
}

<?php

namespace Tests\Unit;

use App\Models\MoqOrderItem;
use App\Models\MoqOrder;
use App\Models\Product;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MoqOrderItemModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_unshipped_quantity_accessor()
    {
        $item = MoqOrderItem::factory()->create([
            'quantity' => 20,
            'shipped_quantity' => 10,
        ]);

        $this->assertEquals(10, $item->unshipped_quantity);
    }

    public function test_unshipped_quantity_accessor_never_negative()
    {
        $item = MoqOrderItem::factory()->create([
            'quantity' => 20,
            'shipped_quantity' => 30,
        ]);

        $this->assertEquals(0, $item->unshipped_quantity);
    }

    public function test_is_fully_shipped_accessor()
    {
        $item1 = MoqOrderItem::factory()->create([
            'quantity' => 20,
            'shipped_quantity' => 20,
        ]);

        $item2 = MoqOrderItem::factory()->create([
            'quantity' => 20,
            'shipped_quantity' => 19,
        ]);

        $item3 = MoqOrderItem::factory()->create([
            'quantity' => 20,
            'shipped_quantity' => 25,
        ]);

        $this->assertTrue($item1->is_fully_shipped);
        $this->assertFalse($item2->is_fully_shipped);
        $this->assertTrue($item3->is_fully_shipped);
    }

    public function test_order_item_relationship_with_order()
    {
        $supplier = Supplier::factory()->create();
        $order = MoqOrder::factory()->create(['supplier_id' => $supplier->id]);
        $item = MoqOrderItem::factory()->create(['moq_order_id' => $order->id]);

        $this->assertTrue($item->order->is($order));
    }

    public function test_order_item_relationship_with_product()
    {
        $supplier = Supplier::factory()->create();
        $product = Product::factory()->create(['supplier_id' => $supplier->id]);
        $item = MoqOrderItem::factory()->create(['product_id' => $product->id]);

        $this->assertTrue($item->product->is($product));
    }

    public function test_order_item_casts()
    {
        $item = MoqOrderItem::factory()->create([
            'quantity' => '20',
            'unit_price' => '99.99',
            'total_price' => '1999.80',
            'cost_price' => '50.00',
            'shipped_quantity' => '10',
        ]);

        $item = $item->fresh();

        $this->assertIsInt($item->quantity);
        $this->assertEquals(20, $item->quantity);
        $this->assertEquals(99.99, $item->unit_price);
        $this->assertEquals(1999.80, $item->total_price);
        $this->assertIsInt($item->shipped_quantity);
        $this->assertEquals(10, $item->shipped_quantity);
    }
}

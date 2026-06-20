<?php

namespace Tests\Unit;

use App\Models\Product;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_is_low_stock_attribute()
    {
        $product = Product::factory()->create([
            'stock_quantity' => 5,
            'safety_stock' => 10,
        ]);

        $this->assertTrue($product->is_low_stock);

        $product->stock_quantity = 15;
        $this->assertFalse($product->is_low_stock);

        $product->stock_quantity = 10;
        $this->assertTrue($product->is_low_stock);
    }

    public function test_profit_margin_attribute()
    {
        $product = Product::factory()->create([
            'price' => 100.00,
            'cost_price' => 60.00,
        ]);

        $this->assertEquals(40.00, $product->profit_margin);
    }

    public function test_profit_margin_attribute_with_zero_price()
    {
        $product = Product::factory()->create([
            'price' => 0,
            'cost_price' => 60.00,
        ]);

        $this->assertEquals(0, $product->profit_margin);
    }

    public function test_profit_margin_attribute_with_negative_price()
    {
        $product = Product::factory()->create([
            'price' => -10.00,
            'cost_price' => 60.00,
        ]);

        $this->assertEquals(0, $product->profit_margin);
    }

    public function test_profit_margin_attribute_with_no_profit()
    {
        $product = Product::factory()->create([
            'price' => 50.00,
            'cost_price' => 50.00,
        ]);

        $this->assertEquals(0, $product->profit_margin);
    }

    public function test_product_relationship_with_supplier()
    {
        $supplier = Supplier::factory()->create();
        $product = Product::factory()->create(['supplier_id' => $supplier->id]);

        $this->assertTrue($product->supplier->is($supplier));
    }

    public function test_get_status_options()
    {
        $options = Product::getStatusOptions();

        $this->assertCount(2, $options);
        $this->assertEquals(true, $options[0]['value']);
        $this->assertEquals('上架', $options[0]['label']);
        $this->assertEquals(false, $options[1]['value']);
        $this->assertEquals('下架', $options[1]['label']);
    }

    public function test_get_unit_options()
    {
        $options = Product::getUnitOptions();

        $this->assertCount(10, $options);
        $units = collect($options)->pluck('value')->toArray();
        $this->assertContains('件', $units);
        $this->assertContains('个', $units);
        $this->assertContains('kg', $units);
        $this->assertContains('㎡', $units);
    }

    public function test_product_casts()
    {
        $product = Product::factory()->create([
            'price' => '99.99',
            'cost_price' => '50.00',
            'moq' => '10',
            'stock_quantity' => '100',
            'is_active' => '1',
            'images' => ['img1.jpg', 'img2.jpg'],
            'attributes' => ['color' => 'red', 'size' => 'M'],
        ]);

        $product = $product->fresh();

        $this->assertEquals(99.99, $product->price);
        $this->assertEquals(50.00, $product->cost_price);
        $this->assertIsInt($product->moq);
        $this->assertEquals(10, $product->moq);
        $this->assertIsBool($product->is_active);
        $this->assertTrue($product->is_active);
        $this->assertIsArray($product->images);
        $this->assertIsArray($product->attributes);
    }

    public function test_product_soft_deletes()
    {
        $product = Product::factory()->create();

        $product->delete();

        $this->assertSoftDeleted($product);
        $this->assertNotNull($product->deleted_at);
    }
}

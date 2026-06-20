<?php

namespace Tests\Unit;

use App\Models\Supplier;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupplierModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_full_address_accessor()
    {
        $supplier = Supplier::factory()->create([
            'province' => '广东省',
            'city' => '深圳市',
            'district' => '南山区',
            'address' => '科技园路1号',
        ]);

        $this->assertEquals('广东省深圳市南山区科技园路1号', $supplier->full_address);
    }

    public function test_full_address_accessor_with_empty_fields()
    {
        $supplier = Supplier::factory()->create([
            'province' => '广东省',
            'city' => '深圳市',
            'district' => '',
            'address' => '科技园路1号',
        ]);

        $this->assertEquals('广东省深圳市科技园路1号', $supplier->full_address);
    }

    public function test_supplier_relationship_with_products()
    {
        $supplier = Supplier::factory()->create();
        $product1 = Product::factory()->create(['supplier_id' => $supplier->id]);
        $product2 = Product::factory()->create(['supplier_id' => $supplier->id]);

        $this->assertCount(2, $supplier->products);
        $this->assertTrue($supplier->products->contains($product1));
        $this->assertTrue($supplier->products->contains($product2));
    }

    public function test_get_status_options()
    {
        $options = Supplier::getStatusOptions();

        $this->assertCount(2, $options);
        $this->assertEquals(true, $options[0]['value']);
        $this->assertEquals('启用', $options[0]['label']);
        $this->assertEquals(false, $options[1]['value']);
        $this->assertEquals('禁用', $options[1]['label']);
    }

    public function test_supplier_casts()
    {
        $supplier = Supplier::factory()->create([
            'is_active' => '1',
            'sort_order' => '5',
        ]);

        $supplier = $supplier->fresh();

        $this->assertIsBool($supplier->is_active);
        $this->assertTrue($supplier->is_active);
        $this->assertIsInt($supplier->sort_order);
        $this->assertEquals(5, $supplier->sort_order);
    }

    public function test_supplier_soft_deletes()
    {
        $supplier = Supplier::factory()->create();

        $supplier->delete();

        $this->assertSoftDeleted($supplier);
        $this->assertNotNull($supplier->deleted_at);
    }
}

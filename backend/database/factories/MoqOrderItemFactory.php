<?php

namespace Database\Factories;

use App\Models\MoqOrderItem;
use App\Models\MoqOrder;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

class MoqOrderItemFactory extends Factory
{
    protected $model = MoqOrderItem::class;

    public function definition(): array
    {
        $product = Product::factory()->create();
        $quantity = fake()->numberBetween(1, 100);
        $unitPrice = fake()->randomFloat(2, 10, 1000);

        return [
            'moq_order_id' => MoqOrder::factory(),
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_sku' => $product->sku,
            'specification' => $product->specification,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'total_price' => $quantity * $unitPrice,
            'cost_price' => $product->cost_price,
            'shipped_quantity' => 0,
            'remark' => fake()->text(50),
        ];
    }
}

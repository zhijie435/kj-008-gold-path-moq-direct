<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        return [
            'name' => fake()->word() . ' ' . fake()->word(),
            'sku' => fake()->unique()->bothify('SKU-####'),
            'barcode' => fake()->ean13(),
            'supplier_id' => Supplier::factory(),
            'category' => fake()->word(),
            'brand' => fake()->word(),
            'specification' => fake()->word() . ' x ' . fake()->word(),
            'unit' => '件',
            'moq' => fake()->numberBetween(1, 100),
            'price' => fake()->randomFloat(2, 10, 1000),
            'cost_price' => fake()->randomFloat(2, 5, 500),
            'weight' => fake()->randomFloat(2, 0.1, 50),
            'volume' => fake()->randomFloat(2, 0.01, 1),
            'origin' => fake()->country(),
            'description' => fake()->text(200),
            'images' => null,
            'attributes' => null,
            'stock_quantity' => fake()->numberBetween(0, 1000),
            'safety_stock' => fake()->numberBetween(0, 50),
            'is_active' => true,
            'sort_order' => fake()->numberBetween(0, 100),
        ];
    }
}

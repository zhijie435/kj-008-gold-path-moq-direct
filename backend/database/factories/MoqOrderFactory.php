<?php

namespace Database\Factories;

use App\Models\MoqOrder;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

class MoqOrderFactory extends Factory
{
    protected $model = MoqOrder::class;

    public function definition(): array
    {
        return [
            'order_no' => 'MOQ' . now()->format('Ymd') . strtoupper(fake()->bothify('??????')),
            'supplier_id' => Supplier::factory(),
            'customer_name' => fake()->name(),
            'customer_phone' => fake()->phoneNumber(),
            'province' => fake()->state(),
            'city' => fake()->city(),
            'district' => fake()->streetName(),
            'address' => fake()->address(),
            'address_detail' => fake()->text(50),
            'total_amount' => fake()->randomFloat(2, 100, 10000),
            'shipping_fee' => fake()->randomFloat(2, 0, 100),
            'discount_amount' => fake()->randomFloat(2, 0, 500),
            'payable_amount' => fake()->randomFloat(2, 100, 10000),
            'paid_amount' => 0,
            'payment_method' => null,
            'paid_at' => null,
            'status' => 'pending',
            'source' => 'manual',
            'remark' => fake()->text(100),
            'internal_note' => fake()->text(100),
            'confirmed_at' => null,
            'shipped_at' => null,
            'completed_at' => null,
            'created_by' => null,
            'updated_by' => null,
        ];
    }

    public function confirmed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'confirmed',
            'confirmed_at' => now(),
        ]);
    }

    public function shipped(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'shipped',
            'shipped_at' => now(),
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'completed',
            'completed_at' => now(),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'cancelled',
        ]);
    }
}

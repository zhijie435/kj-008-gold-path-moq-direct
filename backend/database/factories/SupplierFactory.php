<?php

namespace Database\Factories;

use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

class SupplierFactory extends Factory
{
    protected $model = Supplier::class;

    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'code' => fake()->unique()->bothify('SUP####'),
            'contact_person' => fake()->name(),
            'phone' => fake()->phoneNumber(),
            'email' => fake()->companyEmail(),
            'province' => fake()->state(),
            'city' => fake()->city(),
            'district' => fake()->streetName(),
            'address' => fake()->address(),
            'business_license' => fake()->bothify('##############'),
            'bank_name' => fake()->company(),
            'bank_account' => fake()->bankAccountNumber(),
            'tax_number' => fake()->bothify('##############'),
            'remark' => fake()->text(100),
            'is_active' => true,
            'sort_order' => fake()->numberBetween(0, 100),
        ];
    }
}

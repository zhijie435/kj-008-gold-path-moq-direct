<?php

namespace Database\Factories;

use App\Models\Shipment;
use App\Models\MoqOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

class ShipmentFactory extends Factory
{
    protected $model = Shipment::class;

    public function definition(): array
    {
        $carriers = ['sf', 'yto', 'zto', 'sto', 'best', 'yunda', 'jd', 'ems'];
        $carrierNames = [
            'sf' => '顺丰速运',
            'yto' => '圆通速递',
            'zto' => '中通快递',
            'sto' => '申通快递',
            'best' => '百世快递',
            'yunda' => '韵达快递',
            'jd' => '京东物流',
            'ems' => 'EMS',
        ];
        $carrierCode = fake()->randomElement($carriers);

        return [
            'shipment_no' => 'SH' . now()->format('Ymd') . strtoupper(fake()->bothify('????????')),
            'moq_order_id' => MoqOrder::factory(),
            'carrier_code' => $carrierCode,
            'carrier_name' => $carrierNames[$carrierCode],
            'tracking_no' => fake()->bothify('################'),
            'shipping_method' => fake()->randomElement(['标准快递', '经济快递', '特快专递']),
            'shipping_cost' => fake()->randomFloat(2, 5, 100),
            'weight' => fake()->randomFloat(2, 0.1, 50),
            'package_count' => fake()->numberBetween(1, 10),
            'package_info' => null,
            'status' => 'pending',
            'shipped_at' => null,
            'delivered_at' => null,
            'tracking_data' => null,
            'remark' => fake()->text(100),
            'created_by' => null,
        ];
    }

    public function shipped(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'shipped',
            'shipped_at' => now(),
        ]);
    }

    public function delivered(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'delivered',
            'shipped_at' => now()->subDays(3),
            'delivered_at' => now(),
        ]);
    }
}

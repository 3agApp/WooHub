<?php

namespace Database\Factories;

use App\Models\WooShop;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\WooOrder>
 */
class WooOrderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'woo_shop_id' => WooShop::factory(),
            'external_order_id' => fake()->numberBetween(1000, 999999),
            'order_number' => (string) fake()->numberBetween(1000, 999999),
            'status' => fake()->randomElement(['processing', 'completed', 'on-hold']),
            'currency' => 'CHF',
            'total' => fake()->randomFloat(2, 20, 5000),
            'customer_name' => fake()->name(),
            'customer_email' => fake()->safeEmail(),
            'order_created_at' => now()->subDays(fake()->numberBetween(0, 30)),
            'order_paid_at' => now()->subDays(fake()->numberBetween(0, 30)),
        ];
    }
}

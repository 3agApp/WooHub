<?php

namespace Database\Factories;

use App\Models\WooShop;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\WooDailyRevenue>
 */
class WooDailyRevenueFactory extends Factory
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
            'revenue_date' => fake()->dateTimeBetween('-30 days', 'now')->format('Y-m-d'),
            'currency' => 'CHF',
            'revenue_total' => fake()->randomFloat(2, 20, 5000),
            'orders_count' => fake()->numberBetween(1, 40),
        ];
    }
}

<?php

namespace Database\Factories;

use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\WooShop>
 */
class WooShopFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'name' => fake()->company().' Shop',
            'url' => fake()->url(),
            'consumer_key' => 'ck_'.fake()->regexify('[A-Za-z0-9]{32}'),
            'consumer_secret' => 'cs_'.fake()->regexify('[A-Za-z0-9]{32}'),
        ];
    }
}

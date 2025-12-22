<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Domain>
 */
class DomainFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
   public function definition(): array
{
    return [
        // أضف كلمة unique() قبل اسم الخاصية
        'name' => $this->faker->unique()->domainWord,
        'tld'  => $this->faker->randomElement(['com', 'net', 'org', 'io', 'shop']),
        'price_monthly' => $this->faker->randomFloat(2, 5, 50),
        'price_yearly' => $this->faker->randomFloat(2, 50, 500),
        'expires_at' => $this->faker->dateTimeBetween('now', '+2 years'),
        'available' => $this->faker->boolean(80),
    ];
}
}

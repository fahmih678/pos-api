<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $cost = fake()->numberBetween(5000, 50000);
        $selling = $cost + fake()->numberBetween(2000, 20000);

        return [
            'category_id' => Category::factory(),
            'sku' => fake()->unique()->bothify('PRD-####-????'),
            'barcode' => fake()->unique()->ean13(),
            'name' => fake()->words(3, true),
            'description' => fake()->paragraph(),
            'cost_price' => $cost,
            'selling_price' => $selling,
            'stock' => fake()->numberBetween(10, 100),
            'alert_stock_threshold' => 10,
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => [
            'is_active' => false,
        ]);
    }

    public function outOfStock(): static
    {
        return $this->state(fn () => [
            'stock' => 0,
        ]);
    }

    public function lowStock(): static
    {
        return $this->state(fn () => [
            'stock' => 5,
            'alert_stock_threshold' => 10,
        ]);
    }
}

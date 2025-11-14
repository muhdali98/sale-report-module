<?php

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Product>
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
        $category = Category::inRandomOrder()->first() ?? Category::factory()->create();

        $productNames = match ($category->name) {
            'Laptop' => ['MacBook Pro', 'Dell XPS', 'HP Spectre', 'Asus ZenBook'],
            'Tablet' => ['iPad Pro', 'Samsung Galaxy Tab', 'Huawei MatePad'],
            'Accessories' => ['Magic Keyboard', 'AirPods', 'Smart Folio', 'USB-C Hub'],
            'PC' => ['iMac', 'Dell Optiplex', 'HP Pavilion'],
            'Watches' => ['Apple Watch', 'Samsung Galaxy Watch'],
            default => ['Generic Product', 'Sample Item'],
        };

        return [
            'name' => $this->faker->randomElement($productNames),
            'category_id' => $category->id,
            'price' => $this->faker->randomFloat(2, 50, 5000), // realistic price range
        ];
    }
}

<?php

namespace Database\Factories;

use App\Models\Item;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Item>
 */
class ItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'kind' => 'news',
            'url' => $url = fake()->unique()->url(),
            'url_hash' => sha1($url),
            'content_hash' => sha1(fake()->unique()->sentence()),
            'title' => fake()->sentence(),
            'publisher' => fake()->company(),
            'country_code' => fake()->randomElement(['KH', 'US', 'FR', 'JP']),
            'language' => 'en',
            'category' => fake()->randomElement(Item::CATEGORIES),
            'severity' => fake()->numberBetween(1, 3),
            'is_cambodia' => false,
            'enrichment_status' => 'skipped',
            'published_at' => fake()->dateTimeBetween('-7 days'),
        ];
    }

    public function cambodianAlert(): static
    {
        return $this->state(fn () => ['is_cambodia' => true, 'severity' => 5, 'country_code' => 'KH', 'kind' => 'threat', 'category' => 'attack']);
    }
}

<?php

namespace Database\Factories;

use App\Models\Source;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Source>
 */
class SourceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->company().' feed',
            'type' => 'rss',
            'config' => ['url' => fake()->url()],
            'interval_minutes' => 60,
            'enabled' => true,
            'ai_enabled' => true,
        ];
    }
}

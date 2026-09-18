<?php

namespace Database\Factories;

use App\Models\WatchlistTerm;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WatchlistTerm>
 */
class WatchlistTermFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'kind' => 'cambodia',
            'term' => fake()->unique()->word(),
        ];
    }
}

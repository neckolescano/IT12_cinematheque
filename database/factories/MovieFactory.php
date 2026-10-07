<?php

namespace Database\Factories;

use App\Models\Movie;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Movie>
 */
class MovieFactory extends Factory
{
    public function definition(): array
    {
        return [
            'title' => rtrim(fake()->unique()->sentence(3), '.'),
            'runtime_minutes' => fake()->numberBetween(70, 180),
            'rating' => fake()->randomElement(['G', 'PG', 'PG-13', 'R-13', 'R-16', 'R-18']),
            'release_year' => fake()->numberBetween(1960, (int) date('Y')),
            'synopsis' => fake()->paragraph(),
        ];
    }
}

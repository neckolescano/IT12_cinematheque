<?php

namespace Database\Factories;

use App\Models\Screening;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Screening>
 */
class ScreeningFactory extends Factory
{
    public function definition(): array
    {
        return [
            'event_title' => rtrim(fake()->sentence(3), '.'),
            'movie_id' => null,
            'event_date' => today()->addDays(fake()->numberBetween(1, 30))->toDateString(),
            'start_time' => '18:00',
            'end_time' => '20:00',
            'type' => 'free',
            'price' => null,
            'total_seats' => 100,
            'created_by' => User::factory(),
        ];
    }

    public function paid(float $price = 150.00): static
    {
        return $this->state(fn () => ['type' => 'paid', 'price' => $price]);
    }

    public function past(): static
    {
        return $this->state(fn () => ['event_date' => today()->subDays(7)->toDateString()]);
    }
}

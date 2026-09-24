<?php

namespace Database\Factories;

use App\Models\Reservation;
use App\Models\ReservationSeat;
use App\Models\Screening;
use App\Models\Seat;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Reservation>
 */
class ReservationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'screening_id' => Screening::factory(),
            'booking_reference' => 'CCD-'.Str::upper(Str::random(8)),
            'status' => 'confirmed',
            'reservation_datetime' => now(),
            'lead_first_name' => fake()->firstName(),
            'lead_middle_name' => null,
            'lead_last_name' => fake()->lastName(),
            'lead_contact_no' => '09'.fake()->numerify('#########'),
            'lead_email' => fake()->safeEmail(),
        ];
    }

    /**
     * Hold the given seats (or the first N free seats) with one declared attendee each,
     * mirroring what the public booking form produces.
     *
     * @param  array<int, Seat>|int  $seats
     */
    public function withSeats(array|int $seats = 1): static
    {
        return $this->afterCreating(function (Reservation $reservation) use ($seats) {
            if (is_int($seats)) {
                $seats = Seat::whereNotIn('seat_id', $reservation->screening->takenSeatIds())
                    ->orderBy('seat_id')->limit($seats)->get()->all();
            }

            foreach (array_values($seats) as $i => $seat) {
                $rs = ReservationSeat::create([
                    'reservation_id' => $reservation->reservation_id,
                    'screening_id' => $reservation->screening_id,
                    'seat_id' => $seat->seat_id,
                ]);

                $rs->attendee()->create([
                    'is_lead_reserver' => $i === 0,
                    'first_name' => $i === 0 ? $reservation->lead_first_name : fake()->firstName(),
                    'last_name' => $i === 0 ? $reservation->lead_last_name : fake()->lastName(),
                    'age' => fake()->numberBetween(12, 80),
                    'sex' => fake()->randomElement(['M', 'F']),
                    'company_school' => fake()->optional()->company(),
                    'pwd_indicator' => false,
                ]);
            }
        });
    }

    public function pending(): static
    {
        return $this->state(fn () => ['status' => 'pending']);
    }
}

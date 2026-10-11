<?php

namespace Database\Seeders;

use App\Models\Movie;
use App\Models\Program;
use App\Models\Reservation;
use App\Models\Screening;
use App\Models\Seat;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Demo screenings: FDCP Cinematheque Centre Davao's Cinemalaya 2026 schedule (Pelikula ng Bayan),
 * 15–16 October 2026. The 15 October screenings are free; the 16 October ones are ₱150. Bookings:
 *  - free: confirmed on booking (e-ticket), including an almost-full screening
 *  - paid through PayMongo (confirmed, e-ticket)
 *  - awaiting payment (held for 15 minutes from seeding)
 *  - never paid (its window already passed, so the first page visit expires it and frees the seats)
 *
 * The PayMongo ids below are demo values; they don't exist in any PayMongo account.
 */
class DemoScreeningSeeder extends Seeder
{
    public const PRICE = 150.00;

    /** [date, start, title, price or null for free] as on the CCD schedule; the end time follows the film's runtime. */
    public const SCHEDULE = [
        ['2026-10-15', '13:00', 'Tirik (To Set Upright)', null],
        ['2026-10-15', '15:00', 'Kaka sa Yawan (Brothers by the River)', null],
        ['2026-10-15', '17:00', 'A.ni.mál', null],
        ['2026-10-16', '13:00', 'Hand of God', self::PRICE],
        ['2026-10-16', '15:00', 'Ganggang', self::PRICE],
        ['2026-10-16', '17:00', 'Tayo Lang ang Nakakaalam (Only Known to Us)', self::PRICE],
    ];

    public function run(): void
    {
        $avt = User::where('email', 'avt@cinematheque.test')->firstOrFail();
        $pdo = User::where('email', 'pdo@cinematheque.test')->firstOrFail();
        $programId = Program::where('name', CatalogSeeder::PROGRAM)->value('program_id');

        $screenings = [];
        foreach (self::SCHEDULE as $i => [$date, $time, $title, $price]) {
            $movie = Movie::where('title', $title)->firstOrFail();
            $start = Carbon::parse("{$date} {$time}");
            // Round the end up to the next 5 minutes.
            $end = $start->copy()->addMinutes((int) (ceil($movie->runtime_minutes / 5) * 5));

            $screenings[$title] = Screening::create([
                'event_title' => $title,
                'movie_id' => $movie->movie_id,
                'films_count' => 1,
                'program_id' => $programId,
                'event_date' => $date,
                'start_time' => $start->format('H:i'),
                'end_time' => $end->format('H:i'),
                'type' => $price ? 'paid' : 'free',
                'price' => $price,
                'total_seats' => Seat::CAPACITY,
                'created_by' => ($i < 3 ? $avt : $pdo)->user_id,
            ]);
        }

        // Free (15 October): confirmed as soon as they are booked; one screening almost full (100 of 120 seats).
        Reservation::factory()->for($screenings['Tirik (To Set Upright)'])->withSeats(3)->create(['status' => 'confirmed']);
        Reservation::factory()->for($screenings['Kaka sa Yawan (Brothers by the River)'])->withSeats(2)->create(['status' => 'confirmed']);
        Reservation::factory()->count(10)->for($screenings['A.ni.mál'])->withSeats(10)->create(['status' => 'confirmed']);

        // Paid through PayMongo → confirmed (e-ticket).
        $this->paid(Reservation::factory()->for($screenings['Hand of God'])->withSeats(4)->create(['status' => 'confirmed']));
        $this->paid(Reservation::factory()->for($screenings['Ganggang'])->withSeats(2)->create(['status' => 'confirmed']));

        // Awaiting payment: held for 15 minutes from now.
        $tayo = $screenings['Tayo Lang ang Nakakaalam (Only Known to Us)'];
        Reservation::factory()->for($tayo)->awaitingPayment()->withSeats(2)->create()
            ->payment()->create(['amount' => 2 * self::PRICE, 'status' => 'pending']);

        // Never paid: its window passed an hour ago, so the first page visit expires it.
        Reservation::factory()->for($tayo)->awaitingPayment()->withSeats(2)->create(['reservation_datetime' => now()->subHour()])
            ->payment()->create(['amount' => 2 * self::PRICE, 'status' => 'pending']);
    }

    private function paid(Reservation $reservation): void
    {
        $reservation->payment()->create([
            'amount' => $reservation->reservationSeats()->count() * self::PRICE,
            'status' => 'verified',
            'payment_channel' => 'gcash',
            'provider_session_id' => 'cs_demo_'.Str::lower(Str::random(20)),
            'provider_payment_id' => 'pay_demo_'.Str::lower(Str::random(20)),
            'paid_at' => now()->subDay(),
        ]);
    }
}

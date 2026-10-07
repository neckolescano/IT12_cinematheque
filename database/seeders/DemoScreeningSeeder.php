<?php

namespace Database\Seeders;

use App\Models\Movie;
use App\Models\Reservation;
use App\Models\Screening;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Demo screenings for the CatalogSeeder lineup (free LVN / Nora Aunor tributes, ₱150 world
 * cinema), dated from tomorrow on, plus bookings covering every workflow state:
 *  - free: awaiting staff approval, approved, cancelled by staff (seats released)
 *  - paid: paid through PayMongo, awaiting payment, expired unpaid (seats released)
 *  - past screening: one seat admitted, one no-show
 *  - an almost-full screening
 *
 * The "awaiting payment" booking expires 15 minutes after seeding, like any unpaid booking.
 * The PayMongo ids below are demo values; they don't exist in any PayMongo account.
 */
class DemoScreeningSeeder extends Seeder
{
    public function run(): void
    {
        $avt = User::where('email', 'avt@cinematheque.test')->firstOrFail();
        $pdo = User::where('email', 'pdo@cinematheque.test')->firstOrFail();

        $screen = function (string $title, int $day, string $time, ?float $price = null, ?User $by = null) use ($avt) {
            $start = Carbon::parse($time);

            return Screening::create([
                'event_title' => $title,
                'movie_id' => Movie::where('title', $title)->value('movie_id'),
                'event_date' => today()->addDays($day),
                'start_time' => $start->format('H:i'),
                'end_time' => $start->copy()->addHours(2)->format('H:i'),
                'type' => $price ? 'paid' : 'free',
                'price' => $price,
                'total_seats' => \App\Models\Seat::CAPACITY,
                'created_by' => ($by ?? $avt)->user_id,
            ]);
        };

        // Pamanang Pelikula: Tribute to LVN Pictures (free)
        $malvarosa = $screen('Malvarosa', 1, '13:00');
        $biyaya = $screen('Biyaya ng Lupa', 1, '15:00');
        $screen('Anak Dalita', 2, '13:00');
        // A free community event with no catalogued film (generated poster tile).
        $shorts = $screen('Shorts Night: Mindanao Filmmakers', 3, '18:00');
        // FDCP Presents: world cinema (₱150)
        $accident = $screen('It Was Just an Accident', 4, '15:00', 150, $pdo);
        $screen('Case 137', 4, '17:00', 150, $pdo);
        $screen('The Secret Agent', 5, '12:00', 150, $pdo);
        $screen('Sound of Falling', 5, '15:00', 150, $pdo);
        // Pamanang Pelikula: Tribute to Nora Aunor (free)
        $himala = $screen('Himala', 6, '17:00');
        $screen('Resurrection', 7, '12:00', 150, $pdo);
        $screen('The Blue Trail', 7, '15:00', 150, $pdo);
        $sentimental = $screen('Sentimental Value', 7, '17:00', 150, $pdo);

        // Free: awaiting approval, approved, and cancelled by staff (its seats are free again).
        Reservation::factory()->for($malvarosa)->pending()->withSeats(2)->create();
        Reservation::factory()->for($malvarosa)->withSeats(3)->create(['status' => 'confirmed']);
        Reservation::factory()->for($shorts)->withSeats(2)->create(['status' => 'confirmed']);
        Reservation::factory()->for($biyaya)->withSeats(2)->create(['status' => 'confirmed'])->cancel('staff');

        // Paid through PayMongo → approved (e-ticket).
        $this->paid(Reservation::factory()->for($accident)->withSeats(3)->create(['status' => 'confirmed']), 450.00);
        $this->paid(Reservation::factory()->for($sentimental)->withSeats(4)->create(['status' => 'confirmed']), 600.00);

        // Awaiting payment: held for 15 minutes from now.
        Reservation::factory()->for($accident)->pending()->withSeats(2)->create()
            ->payment()->create(['amount' => 300.00, 'status' => 'pending']);

        // Never paid: its window passed an hour ago, so the first page visit expires it.
        Reservation::factory()->for($accident)->pending()->withSeats(2)->create(['reservation_datetime' => now()->subHour()])
            ->payment()->create(['amount' => 300.00, 'status' => 'pending']);

        // Almost full.
        Reservation::factory()->count(8)->for($himala)->withSeats(10)->create(['status' => 'confirmed']);

        // Past screening: one seat admitted, one no-show (reserved, never arrived).
        $past = $screen('Anak Dalita', -5, '14:00');
        $pastReservation = Reservation::factory()->for($past)->withSeats(2)
            ->create(['status' => 'confirmed', 'reservation_datetime' => now()->subDays(8)]);
        $pastReservation->reservationSeats()->first()->attendance()->create([
            'checked_in_at' => today()->subDays(5)->setTime(13, 50),
            'checked_in_by' => $pdo->user_id,
            'remarks' => 'Arrived early.',
        ]);
    }

    private function paid(Reservation $reservation, float $amount): void
    {
        $reservation->payment()->create([
            'amount' => $amount,
            'status' => 'verified',
            'payment_channel' => 'gcash',
            'provider_session_id' => 'cs_demo_'.Str::lower(Str::random(20)),
            'provider_payment_id' => 'pay_demo_'.Str::lower(Str::random(20)),
            'paid_at' => now()->subDay(),
        ]);
    }
}

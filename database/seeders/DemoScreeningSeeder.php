<?php

namespace Database\Seeders;

use App\Models\Movie;
use App\Models\Reservation;
use App\Models\Screening;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Demo data covering every workflow state:
 *  - free screening: one booking awaiting staff approval, one approved
 *  - paid screening: one booking awaiting PayMongo payment, one paid through PayMongo
 *  - past screening: one seat admitted, one no-show
 *
 * The PayMongo ids below are demo values only; they don't exist in any PayMongo account.
 */
class DemoScreeningSeeder extends Seeder
{
    public function run(): void
    {
        $avt = User::where('email', 'avt@cinematheque.test')->firstOrFail();
        $pdo = User::where('email', 'pdo@cinematheque.test')->firstOrFail();

        $free = Screening::create([
            'event_title' => 'Shorts Night: Mindanao Filmmakers',
            'event_date' => today()->addDays(3), 'start_time' => '18:00', 'end_time' => '20:30',
            'type' => 'free', 'total_seats' => 100, 'created_by' => $avt->user_id,
        ]);
        Reservation::factory()->for($free)->pending()->withSeats(2)->create();
        Reservation::factory()->for($free)->withSeats(3)->create(['status' => 'confirmed']);

        $movie = Movie::first();
        $paid = Screening::create([
            'event_title' => $movie?->title ?? 'Feature Presentation',
            'movie_id' => $movie?->movie_id,
            'event_date' => today()->addDays(7), 'start_time' => '19:00', 'end_time' => '21:00',
            'type' => 'paid', 'price' => 150.00, 'total_seats' => 80, 'created_by' => $pdo->user_id,
        ]);

        // Awaiting payment: reserved, payment record exists, PayMongo not yet paid.
        $unpaid = Reservation::factory()->for($paid)->pending()->withSeats(2)->create();
        $unpaid->payment()->create(['amount' => 300.00, 'status' => 'pending']);

        // Paid through PayMongo → approved (e-ticket).
        $settled = Reservation::factory()->for($paid)->withSeats(3)->create(['status' => 'confirmed']);
        $settled->payment()->create([
            'amount' => 450.00,
            'status' => 'verified',
            'payment_channel' => 'gcash',
            'provider_session_id' => 'cs_demo_'.Str::lower(Str::random(20)),
            'provider_payment_id' => 'pay_demo_'.Str::lower(Str::random(20)),
            'paid_at' => now()->subDay(),
        ]);

        // Past screening: one seat admitted, one no-show (reserved, never arrived).
        $past = Screening::create([
            'event_title' => 'Documentary Afternoon',
            'event_date' => today()->subDays(5), 'start_time' => '14:00', 'end_time' => '16:00',
            'type' => 'free', 'total_seats' => 100, 'created_by' => $avt->user_id,
        ]);
        $pastReservation = Reservation::factory()->for($past)->withSeats(2)->create(['status' => 'confirmed']);
        $pastReservation->reservationSeats()->first()->attendance()->create([
            'checked_in_at' => today()->subDays(5)->setTime(13, 50),
            'checked_in_by' => $pdo->user_id,
            'remarks' => 'Arrived early.',
        ]);
    }
}

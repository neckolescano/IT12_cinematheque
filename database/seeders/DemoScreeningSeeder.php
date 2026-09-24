<?php

namespace Database\Seeders;

use App\Models\Movie;
use App\Models\PaymentQrCode;
use App\Models\Reservation;
use App\Models\Screening;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

/**
 * Demo data covering every workflow state:
 *  - a free screening with a confirmed reservation
 *  - a paid screening with (a) a pending payment + pending proof awaiting review,
 *    (b) a verified payment, (c) a pending payment with no proof yet
 *  - a past screening with one checked-in seat and one no-show
 *  - an active payment QR code
 */
class DemoScreeningSeeder extends Seeder
{
    /** 1×1 white PNG used as a stand-in for uploaded images. */
    private const PLACEHOLDER_PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAIAAACQd1PeAAAADElEQVR4nGP4//8/AAX+Av4N70a4AAAAAElFTkSuQmCC';

    public function run(): void
    {
        $avt = User::where('email', 'avt@cinematheque.test')->firstOrFail();
        $pdo = User::where('email', 'pdo@cinematheque.test')->firstOrFail();

        $qrPath = $this->placeholderImage('qr-codes/demo-qr.png');
        PaymentQrCode::create(['qr_image' => $qrPath, 'is_active' => true, 'uploaded_by' => $avt->user_id, 'uploaded_at' => now()]);

        $free = Screening::create([
            'event_title' => 'Shorts Night: Mindanao Filmmakers',
            'event_date' => today()->addDays(3), 'start_time' => '18:00', 'end_time' => '20:30',
            'type' => 'free', 'total_seats' => 100, 'created_by' => $avt->user_id,
        ]);
        Reservation::factory()->for($free)->withSeats(2)->create(['status' => 'confirmed']);

        $paid = Screening::create([
            'event_title' => Movie::first()?->title ?? 'Feature Presentation',
            'movie_id' => Movie::first()?->movie_id,
            'event_date' => today()->addDays(7), 'start_time' => '19:00', 'end_time' => '21:00',
            'type' => 'paid', 'price' => 150.00, 'total_seats' => 80, 'created_by' => $pdo->user_id,
        ]);

        // (a) pending payment with a proof awaiting review
        $awaiting = Reservation::factory()->for($paid)->pending()->withSeats(3)->create();
        $payment = $awaiting->payment()->create(['amount' => 450.00, 'status' => 'pending', 'payment_channel' => 'GCash']);
        $payment->proofs()->create([
            'proof_image' => $this->placeholderImage('payment-proofs/demo-proof-1.png'),
            'submitted_at' => now(),
            'status' => 'pending',
        ]);

        // (b) verified payment, confirmed reservation
        $verified = Reservation::factory()->for($paid)->withSeats(1)->create(['status' => 'confirmed']);
        $vp = $verified->payment()->create(['amount' => 150.00, 'status' => 'verified', 'payment_channel' => 'Maya']);
        $vp->proofs()->create([
            'proof_image' => $this->placeholderImage('payment-proofs/demo-proof-2.png'),
            'submitted_at' => now()->subDay(), 'status' => 'accepted',
            'reviewed_by' => $avt->user_id, 'reviewed_at' => now()->subHours(20),
        ]);

        // (c) pending payment, no proof uploaded yet
        $unpaid = Reservation::factory()->for($paid)->pending()->withSeats(2)->create();
        $unpaid->payment()->create(['amount' => 300.00, 'status' => 'pending']);

        // Past screening: one seat admitted, one no-show.
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

    private function placeholderImage(string $path): string
    {
        Storage::disk('public')->put($path, base64_decode(self::PLACEHOLDER_PNG));

        return $path;
    }
}

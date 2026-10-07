<?php

namespace Tests\Feature;

use App\Mail\ReservationApprovedMail;
use App\Mail\ReservationCancelledMail;
use App\Mail\ReservationPendingMail;
use App\Models\Reservation;
use App\Models\Screening;
use App\Models\Seat;
use App\Models\User;
use Database\Seeders\SeatSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Mail::fake() captures emails instead of sending them, so these tests check WHICH email
 * goes out, to WHOM, and WHAT it contains — not actual SMTP delivery.
 */
class ReservationEmailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SeatSeeder::class);
    }

    private function book(Screening $screening): void
    {
        $seat = Seat::first();
        $this->post(route('bookings.store', $screening), [
            'seat_ids' => [$seat->seat_id],
            'lead_first_name' => 'Ana', 'lead_last_name' => 'Santos', 'lead_contact_no' => '09171234567',
            'lead_email' => 'ana@example.test', 'lead_seat_id' => $seat->seat_id,
            'attendees' => [$seat->seat_id => ['first_name' => 'Ana', 'last_name' => 'Santos', 'age' => 30, 'sex' => 'F', 'company_school' => 'Ateneo de Davao', 'contact_no' => '0917 123 4567', 'email' => 'guest@example.test']],
        ]);
    }

    public function test_new_booking_sends_the_pending_email_to_the_booker(): void
    {
        Mail::fake();
        $this->book(Screening::factory()->create());

        Mail::assertSent(ReservationPendingMail::class, fn ($m) => $m->hasTo('ana@example.test'));
    }

    public function test_paid_pending_email_contains_the_payment_link(): void
    {
        $reservation = Reservation::factory()->for(Screening::factory()->paid(150))->pending()->withSeats(1)->create();
        $reservation->payment()->create(['amount' => 150, 'status' => 'pending']);

        $html = (new ReservationPendingMail($reservation))->render();

        $this->assertStringContainsString(route('bookings.pay', $reservation), $html);
        $this->assertStringContainsString($reservation->booking_reference, $html);
        $this->assertStringContainsString('AWAITING PAYMENT', $html);
    }

    public function test_approving_sends_the_e_ticket_with_the_booking_details(): void
    {
        Mail::fake();
        $screening = Screening::factory()->create(['event_title' => 'Himala', 'start_time' => '19:00']);
        $reservation = Reservation::factory()->for($screening)->pending()->withSeats(2)->create();

        $this->actingAs(User::factory()->create())->patch(route('staff.reservations.confirm', $reservation))
            ->assertSessionHas('status');

        Mail::assertSent(ReservationApprovedMail::class, function (ReservationApprovedMail $mail) use ($reservation) {
            $html = $mail->render();
            $reservation->load('reservationSeats.seat', 'reservationSeats.attendee');

            return $mail->hasTo($reservation->lead_email)
                && str_contains($html, 'E-TICKET')
                && str_contains($html, $reservation->booking_reference)
                && str_contains($html, 'Himala')
                && str_contains($html, '7:00 PM')
                && str_contains($html, $reservation->reservationSeats[0]->seat->seat_label)
                && str_contains($html, e($reservation->reservationSeats[1]->attendee->full_name));
        });
    }

    public function test_cancelling_sends_the_cancellation_notice(): void
    {
        Mail::fake();
        $reservation = Reservation::factory()->withSeats(1)->create();

        $this->actingAs(User::factory()->create())->patch(route('staff.reservations.cancel', $reservation));

        Mail::assertSent(ReservationCancelledMail::class, fn ($m) => $m->hasTo($reservation->lead_email));
    }

    public function test_a_mail_failure_never_breaks_the_booking_and_is_reported(): void
    {
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('SMTP server unreachable'));

        $screening = Screening::factory()->create();
        $this->book($screening);

        $reservation = Reservation::firstOrFail();
        $this->assertSame('pending', $reservation->status); // booking saved despite the failure
        $this->assertStringContainsString('could not send', session('status'));

        $this->actingAs(User::factory()->create())->patch(route('staff.reservations.confirm', $reservation))
            ->assertSessionHas('warning');
        $this->assertSame('confirmed', $reservation->fresh()->status);
    }

    public function test_staff_can_resend_the_current_email(): void
    {
        Mail::fake();
        $reservation = Reservation::factory()->withSeats(1)->create(['status' => 'confirmed']);

        $this->actingAs(User::factory()->create())->post(route('staff.reservations.resend', $reservation))
            ->assertSessionHas('status');

        Mail::assertSent(ReservationApprovedMail::class, 1);
    }
}

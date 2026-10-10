<?php

namespace Tests\Feature;

use App\Mail\ReservationApprovedMail;
use App\Models\Reservation;
use App\Models\ReservationSeat;
use App\Models\Screening;
use App\Models\Seat;
use App\Models\User;
use Database\Seeders\SeatSeeder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/** Decisions 5a (cancelled bookings release seats) and 5b (unpaid bookings expire after 15 min). */
class SeatReleaseAndExpiryTest extends TestCase
{
    use RefreshDatabase;

    private const API = 'https://api.paymongo.com/v1/checkout_sessions';

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.paymongo.secret_key' => 'sk_test_fake']);
        Mail::fake();
        $this->seed(SeatSeeder::class);
    }

    private function bookingPayload(Seat $seat): array
    {
        return [
            'reviewed' => 1,
            'seat_ids' => [$seat->seat_id],
            'lead_first_name' => 'Ana', 'lead_last_name' => 'Santos',
            'lead_contact_no' => '09171234567', 'lead_email' => 'ana@example.test',
            'lead_seat_id' => $seat->seat_id,
            'attendees' => [$seat->seat_id => ['first_name' => 'Ana', 'last_name' => 'Santos', 'age' => 25, 'sex' => 'F', 'company_school' => 'Ateneo de Davao', 'contact_no' => '0917 123 4567', 'email' => 'guest@example.test']],
        ];
    }

    private function unpaidBooking(): Reservation
    {
        $reservation = Reservation::factory()->for(Screening::factory()->paid(150))->awaitingPayment()->withSeats([Seat::first()])->create();
        $reservation->payment()->create(['amount' => 150, 'status' => 'pending', 'provider_session_id' => 'cs_late']);

        return $reservation;
    }

    private function fakePaidSession(Reservation $reservation, int $paidAt): void
    {
        Http::fake([self::API.'/cs_late' => Http::response(['data' => ['id' => 'cs_late', 'attributes' => [
            'status' => 'active', 'checkout_url' => 'https://checkout.paymongo.com/cs_late',
            'reference_number' => $reservation->booking_reference,
            'payments' => [['id' => 'pay_late', 'attributes' => ['amount' => 15000, 'status' => 'paid', 'paid_at' => $paidAt, 'source' => ['type' => 'gcash']]]],
        ]]])]);
    }

    public function test_staff_cancel_releases_the_seats_but_keeps_the_booking_as_history(): void
    {
        $screening = Screening::factory()->create();
        $seat = Seat::first();
        $reservation = Reservation::factory()->for($screening)->withSeats([$seat])->create();
        $this->assertSame(\App\Models\Seat::CAPACITY - 1, $screening->availableSeatCount());

        $this->actingAs(User::factory()->create())->patch(route('staff.reservations.cancel', $reservation))->assertRedirect();

        $reservation->refresh();
        $this->assertSame('cancelled', $reservation->status);
        $this->assertSame('staff', $reservation->cancellation_reason);
        $this->assertNotNull($reservation->cancelled_at);
        $this->assertNotNull($reservation->reservationSeats()->first()->released_at);
        $this->assertNotNull($reservation->reservationSeats()->first()->attendee);
        $this->assertSame(\App\Models\Seat::CAPACITY, $screening->availableSeatCount());

        // Someone else can now book that exact seat.
        $this->post(route('bookings.store', $screening), $this->bookingPayload($seat))->assertSessionHasNoErrors();
        $this->assertSame(2, ReservationSeat::where('screening_id', $screening->screening_id)->where('seat_id', $seat->seat_id)->count());
    }

    public function test_the_database_still_blocks_two_holds_on_one_seat(): void
    {
        $screening = Screening::factory()->create();
        $seat = Seat::first();
        Reservation::factory()->for($screening)->withSeats([$seat])->create()->cancel('staff');
        $second = Reservation::factory()->for($screening)->withSeats([$seat])->create(); // released seat: allowed

        $this->expectException(UniqueConstraintViolationException::class);
        ReservationSeat::create(['reservation_id' => $second->reservation_id, 'screening_id' => $screening->screening_id, 'seat_id' => $seat->seat_id]);
    }

    public function test_an_unpaid_booking_expires_after_15_minutes_and_frees_its_seats(): void
    {
        $reservation = $this->unpaidBooking();

        $this->travel(14)->minutes();
        $this->get(route('home'))->assertOk();
        $this->assertSame('awaiting_payment', $reservation->fresh()->status);

        $this->travel(2)->minutes();
        $this->get(route('home'))->assertOk();

        $reservation->refresh();
        $this->assertSame('cancelled', $reservation->status);
        $this->assertSame('payment_expired', $reservation->cancellation_reason);
        $this->assertSame(\App\Models\Seat::CAPACITY, $reservation->screening->availableSeatCount());
        $this->get(route('bookings.show', $reservation))->assertSee('Reservation expired');
    }

    public function test_free_bookings_and_paid_bookings_already_paid_never_expire(): void
    {
        $free = Reservation::factory()->for(Screening::factory())->withSeats(1)->create();
        $paid = Reservation::factory()->for(Screening::factory()->paid(150))->withSeats(1)->create(['status' => 'confirmed']);
        $paid->payment()->create(['amount' => 150, 'status' => 'verified']);

        $this->travel(2)->days();
        $this->artisan('reservations:expire-unpaid')->assertSuccessful();

        $this->assertSame('confirmed', $free->fresh()->status);
        $this->assertSame('confirmed', $paid->fresh()->status);
    }

    public function test_a_payment_made_after_the_window_keeps_the_booking_cancelled_and_flags_a_refund(): void
    {
        $reservation = $this->unpaidBooking();
        $this->travel(20)->minutes();
        $this->get(route('home'));
        $this->fakePaidSession($reservation, now()->timestamp); // paid 20 minutes in: too late

        $this->get(route('bookings.payment.return', $reservation))->assertSessionHas('warning');

        $this->assertSame('cancelled', $reservation->fresh()->status);
        $this->assertSame('verified', $reservation->payment->fresh()->status);
        Mail::assertNotSent(ReservationApprovedMail::class);
        $this->actingAs(User::factory()->create())->followingRedirects()->get(route('staff.reservations.show', $reservation))->assertSee('refund due');
    }

    public function test_a_payment_made_in_time_but_reported_after_expiry_gets_its_seats_back(): void
    {
        $reservation = $this->unpaidBooking();
        $paidAt = now()->addMinutes(14)->timestamp; // paid within the window...
        $this->travel(16)->minutes();
        $this->get(route('home')); // ...but the booking expired before PayMongo's notice arrived
        $this->assertSame('cancelled', $reservation->fresh()->status);
        $this->fakePaidSession($reservation, $paidAt);

        $this->get(route('bookings.payment.return', $reservation))->assertSessionHas('status');

        $reservation->refresh();
        $this->assertSame('confirmed', $reservation->status);
        $this->assertNull($reservation->cancellation_reason);
        $this->assertSame(\App\Models\Seat::CAPACITY - 1, $reservation->screening->availableSeatCount());
        Mail::assertSent(ReservationApprovedMail::class, 1);
    }

    public function test_an_in_time_payment_cannot_take_back_a_seat_someone_else_has_booked(): void
    {
        $reservation = $this->unpaidBooking();
        $paidAt = now()->addMinutes(14)->timestamp;
        $this->travel(16)->minutes();
        $this->get(route('home'));
        Reservation::factory()->for($reservation->screening)->withSeats([Seat::first()])->create();
        $this->fakePaidSession($reservation, $paidAt);

        $this->get(route('bookings.payment.return', $reservation))->assertSessionHas('warning');

        $this->assertSame('cancelled', $reservation->fresh()->status);
        $this->assertSame('verified', $reservation->payment->fresh()->status);
    }

    public function test_the_retired_proof_and_qr_tables_are_gone(): void
    {
        $this->assertFalse(Schema::hasTable('payment_proofs'));
        $this->assertFalse(Schema::hasTable('payment_qr_codes'));
    }
}

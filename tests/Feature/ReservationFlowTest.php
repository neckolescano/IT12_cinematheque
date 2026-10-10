<?php

namespace Tests\Feature;

use App\Models\Reservation;
use App\Models\ReservationSeat;
use App\Models\Screening;
use App\Models\Seat;
use Database\Seeders\SeatSeeder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReservationFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SeatSeeder::class);
    }

    private function payload(array $seatIds, array $overrides = []): array
    {
        $attendees = [];
        foreach ($seatIds as $id) {
            $attendees[$id] = ['first_name' => 'Juan'.$id, 'last_name' => 'Dela Cruz', 'age' => 25, 'sex' => 'M', 'company_school' => 'Ateneo de Davao', 'contact_no' => '0917 123 4567', 'email' => 'guest@example.test'];
        }

        return array_merge([
            'reviewed' => 1,
            'seat_ids' => $seatIds,
            'lead_first_name' => 'Ana',
            'lead_last_name' => 'Santos',
            'lead_contact_no' => '09171234567',
            'lead_email' => 'ana@example.test',
            'lead_seat_id' => $seatIds[0],
            'attendees' => $attendees,
        ], $overrides);
    }

    public function test_seat_picker_and_details_steps_render(): void
    {
        $screening = Screening::factory()->create();
        $seat = Seat::first();

        $this->get(route('bookings.create', $screening))->assertOk()->assertSee('Select your seats');
        $this->get(route('bookings.create', [$screening, 'seats' => [$seat->seat_id]]))
            ->assertOk()->assertSee('Attendee for seat '.$seat->seat_label);
    }

    public function test_free_reservation_creates_seats_and_attendees_and_is_confirmed_at_once(): void
    {
        $screening = Screening::factory()->create();
        $seatIds = Seat::limit(3)->pluck('seat_id')->all();

        $response = $this->post(route('bookings.store', $screening), $this->payload($seatIds));

        $reservation = Reservation::firstOrFail();
        $response->assertRedirect(route('bookings.show', $reservation));
        $this->assertSame('confirmed', $reservation->status); // auto-approved: no staff step
        $this->assertCount(3, $reservation->reservationSeats);
        $this->assertCount(3, $reservation->attendees);
        $this->assertSame(1, $reservation->attendees()->where('is_lead_reserver', true)->count());
        $this->assertNull($reservation->payment);
        $this->assertSame(\App\Models\Seat::CAPACITY - 3, $screening->availableSeatCount());

        $this->get(route('bookings.show', $reservation))->assertOk()->assertSee($reservation->booking_reference);
    }

    public function test_paid_reservation_creates_pending_payment_for_price_times_seats(): void
    {
        $screening = Screening::factory()->paid(150)->create();
        $seatIds = Seat::limit(2)->pluck('seat_id')->all();

        $response = $this->post(route('bookings.store', $screening), $this->payload($seatIds));

        $reservation = Reservation::firstOrFail();
        $response->assertRedirect(route('bookings.pay', $reservation)); // straight on to PayMongo
        $this->assertSame('awaiting_payment', $reservation->status);
        $this->assertSame('pending', $reservation->payment->status);
        $this->assertSame('300.00', $reservation->payment->amount);
    }

    public function test_booker_email_is_required_for_the_e_ticket(): void
    {
        $screening = Screening::factory()->create();

        $this->post(route('bookings.store', $screening), $this->payload([Seat::first()->seat_id], ['lead_email' => '']))
            ->assertSessionHasErrors('lead_email');
        $this->assertDatabaseCount('reservations', 0);
    }

    public function test_every_seat_needs_exactly_one_attendee(): void
    {
        $screening = Screening::factory()->create();
        $seatIds = Seat::limit(2)->pluck('seat_id')->all();
        $payload = $this->payload($seatIds);
        unset($payload['attendees'][$seatIds[1]]);

        $this->post(route('bookings.store', $screening), $payload)->assertSessionHasErrors('attendees');
        $this->assertDatabaseCount('reservations', 0);
    }

    public function test_already_reserved_seat_is_rejected(): void
    {
        $screening = Screening::factory()->create();
        $seat = Seat::first();
        Reservation::factory()->for($screening)->withSeats([$seat])->create();

        $this->post(route('bookings.store', $screening), $this->payload([$seat->seat_id]))
            ->assertSessionHasErrors('seat_ids');
        $this->assertDatabaseCount('reservations', 1);
    }

    public function test_database_blocks_double_booking_of_a_seat(): void
    {
        $screening = Screening::factory()->create();
        $seat = Seat::first();
        $a = Reservation::factory()->for($screening)->withSeats([$seat])->create();
        $b = Reservation::factory()->for($screening)->create();

        $this->expectException(UniqueConstraintViolationException::class);
        ReservationSeat::create(['reservation_id' => $b->reservation_id, 'screening_id' => $screening->screening_id, 'seat_id' => $seat->seat_id]);
    }

    public function test_capacity_is_enforced(): void
    {
        $screening = Screening::factory()->create(['total_seats' => 1]);
        $seatIds = Seat::limit(2)->pluck('seat_id')->all();

        $this->post(route('bookings.store', $screening), $this->payload($seatIds))->assertSessionHasErrors('seat_ids');
    }

    public function test_past_screenings_cannot_be_booked(): void
    {
        $screening = Screening::factory()->past()->create();

        $this->post(route('bookings.store', $screening), $this->payload([Seat::first()->seat_id]))
            ->assertSessionHasErrors('seat_ids');
    }

    public function test_booking_lookup_by_reference_opens_the_ticket_only(): void
    {
        $reservation = Reservation::factory()->withSeats(1)->create();

        $this->get(route('bookings.lookup', ['reference' => strtolower($reservation->booking_reference)]))
            ->assertRedirect(route('bookings.ticket', $reservation));
        $this->get(route('bookings.lookup', ['reference' => 'CCD-NOPE1234']))->assertSessionHasErrors('reference');

        // The ticket page sits under "Find my booking"; the booking-flow page under "Screenings".
        $ticket = $this->get(route('bookings.ticket', $reservation))->assertOk()->assertSee($reservation->booking_reference)->getContent();
        $this->assertMatchesRegularExpression('#href="[^"]*/booking"\s+aria-current="page"#', $ticket);
        $this->assertStringNotContainsString('class="status', $ticket);
        $flow = $this->get(route('bookings.show', $reservation))->getContent();
        $this->assertMatchesRegularExpression('#href="[^"]*/cinemathequecentredavao"\s+aria-current="page"#', $flow);
    }

    public function test_the_first_seat_is_the_primary_booker_and_mobile_takes_ten_digits_after_63(): void
    {
        $screening = Screening::factory()->create(['type' => 'free']);
        [$a, $b] = Seat::orderBy('seat_id')->take(2)->get()->all();
        $person = fn ($first, $email, $mobile = '9171234567') => ['first_name' => $first, 'last_name' => 'Cruz', 'age' => 30, 'sex' => 'F', 'company_school' => 'UP Mindanao', 'contact_no' => $mobile, 'email' => $email];

        // The form's field sits after a fixed "+63": 9 digits (the old "+639" field) are now one short.
        $this->post(route('bookings.store', $screening), [
            'reviewed' => 1,
            'seat_ids' => [$a->seat_id],
            'attendees' => [$a->seat_id => $person('Ana', 'ana@example.test', '171234567')],
        ])->assertSessionHasErrors('attendees.'.$a->seat_id.'.contact_no');

        // Seats in picking order: B first, so B's person is the booker. No "Your details" fields are sent.
        $this->post(route('bookings.store', $screening), [
            'reviewed' => 1,
            'seat_ids' => [$b->seat_id, $a->seat_id],
            'attendees' => [$b->seat_id => $person('Bea', 'bea@example.test'), $a->seat_id => $person('Ana', 'ana@example.test')],
        ])->assertSessionHasNoErrors();

        $reservation = Reservation::with('attendees')->firstOrFail();
        $this->assertSame('Bea', $reservation->lead_first_name);
        $this->assertSame('bea@example.test', $reservation->lead_email);
        $this->assertSame('+639171234567', $reservation->lead_contact_no);
        $this->assertSame('Bea', $reservation->attendees->firstWhere('is_lead_reserver', true)->first_name);

        // The details step lists the seats in the order they were picked.
        $other = Screening::factory()->create(['type' => 'free']);
        $this->get(route('bookings.create', ['screening' => $other, 'seats' => [$b->seat_id, $a->seat_id]]))
            ->assertSeeInOrder(['Seat '.$b->seat_label, 'You · primary booker', 'Seat '.$a->seat_label]);
    }
}

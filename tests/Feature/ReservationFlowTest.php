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
            $attendees[$id] = ['first_name' => 'Juan'.$id, 'last_name' => 'Dela Cruz', 'age' => 25, 'sex' => 'M', 'pwd_indicator' => '0'];
        }

        return array_merge([
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

        $this->get(route('bookings.create', $screening))->assertOk()->assertSee('Choose your seats');
        $this->get(route('bookings.create', [$screening, 'seats' => [$seat->seat_id]]))
            ->assertOk()->assertSee('Attendee for seat '.$seat->seat_label);
    }

    public function test_free_reservation_creates_seats_and_attendees_and_is_confirmed(): void
    {
        $screening = Screening::factory()->create();
        $seatIds = Seat::limit(3)->pluck('seat_id')->all();

        $response = $this->post(route('bookings.store', $screening), $this->payload($seatIds));

        $reservation = Reservation::firstOrFail();
        $response->assertRedirect(route('bookings.show', $reservation));
        $this->assertSame('confirmed', $reservation->status);
        $this->assertCount(3, $reservation->reservationSeats);
        $this->assertCount(3, $reservation->attendees);
        $this->assertSame(1, $reservation->attendees()->where('is_lead_reserver', true)->count());
        $this->assertNull($reservation->payment);
        $this->assertSame(97, $screening->availableSeatCount());

        $this->get(route('bookings.show', $reservation))->assertOk()->assertSee($reservation->booking_reference);
    }

    public function test_paid_reservation_creates_pending_payment_for_price_times_seats(): void
    {
        $screening = Screening::factory()->paid(150)->create();
        $seatIds = Seat::limit(2)->pluck('seat_id')->all();

        $this->post(route('bookings.store', $screening), $this->payload($seatIds))->assertRedirect();

        $reservation = Reservation::firstOrFail();
        $this->assertSame('pending', $reservation->status);
        $this->assertSame('pending', $reservation->payment->status);
        $this->assertSame('300.00', $reservation->payment->amount);
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

    public function test_booking_lookup_by_reference(): void
    {
        $reservation = Reservation::factory()->withSeats(1)->create();

        $this->get(route('bookings.lookup', ['reference' => strtolower($reservation->booking_reference)]))
            ->assertRedirect(route('bookings.show', $reservation));
    }
}

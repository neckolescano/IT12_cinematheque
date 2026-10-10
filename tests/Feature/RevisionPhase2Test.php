<?php

namespace Tests\Feature;

use App\Models\Reservation;
use App\Models\Screening;
use App\Models\Seat;
use Database\Seeders\SeatSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Revision phase 2 (2026-10-11): compulsory Review step, auto-approval, 20% PWD / Senior Citizen
 * discount per ticket, required logsheet fields, and the floor plan as the venue sees it.
 */
class RevisionPhase2Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SeatSeeder::class);
        Mail::fake();
    }

    /** One attendee per seat; $ids maps an attendee index to ['pwd_id_no' => ..] or ['senior_card_no' => ..]. */
    private function payload(array $seatIds, array $ids = [], bool $reviewed = true): array
    {
        $attendees = [];
        foreach ($seatIds as $i => $seatId) {
            $attendees[$seatId] = [
                'first_name' => 'Guest'.$i, 'last_name' => 'Cruz', 'age' => 30, 'sex' => 'F',
                'company_school' => 'Ateneo de Davao', 'contact_no' => '9171234567', 'email' => "guest{$i}@example.test",
                ...($ids[$i] ?? []),
            ];
        }

        return array_filter(['reviewed' => $reviewed ? 1 : null, 'seat_ids' => $seatIds, 'attendees' => $attendees], fn ($v) => $v !== null);
    }

    public function test_pwd_and_senior_tickets_are_20_percent_off_each(): void
    {
        $screening = Screening::factory()->paid(50)->create();
        $seatIds = Seat::limit(5)->pluck('seat_id')->all();

        $this->post(route('bookings.store', $screening), $this->payload($seatIds, [4 => ['pwd_id_no' => '11-2402-000-0000001']]))
            ->assertSessionHasNoErrors();

        // 4 regular × ₱50 + 1 PWD × ₱40 = ₱240
        $reservation = Reservation::firstOrFail();
        $this->assertSame('240.00', $reservation->payment->amount);
        $this->assertSame('awaiting_payment', $reservation->status);
        $pwd = $reservation->reservationSeats()->where('seat_id', $seatIds[4])->first();
        $this->assertSame(['pwd', '50.00', '40.00'], [$pwd->discount_type, $pwd->unit_price, $pwd->amount_due]);
    }

    public function test_one_discount_per_ticket_pwd_first(): void
    {
        $screening = Screening::factory()->paid(150)->create();
        $seatIds = Seat::limit(2)->pluck('seat_id')->all();

        $this->post(route('bookings.store', $screening), $this->payload($seatIds, [
            0 => ['pwd_id_no' => '1124020000000002', 'senior_card_no' => 'OSCA-0001'],
            1 => ['senior_card_no' => 'OSCA-0002'],
        ]))->assertSessionHasNoErrors();

        $reservation = Reservation::firstOrFail();
        $this->assertSame(['pwd', 'senior'], $reservation->reservationSeats->sortBy('seat_id')->pluck('discount_type')->values()->all());
        $this->assertSame('240.00', $reservation->payment->amount); // 120 + 120, never stacked
    }

    public function test_free_screenings_cost_nothing_even_with_an_id(): void
    {
        $screening = Screening::factory()->create();
        $seat = Seat::first();

        $this->post(route('bookings.store', $screening), $this->payload([$seat->seat_id], [0 => ['senior_card_no' => 'OSCA-0009']]))
            ->assertSessionHasNoErrors();

        $reservation = Reservation::firstOrFail();
        $this->assertSame('confirmed', $reservation->status);
        $this->assertNull($reservation->payment);
        $this->assertSame('0.00', $reservation->reservationSeats->first()->amount_due);
    }

    public function test_the_review_step_is_compulsory(): void
    {
        $screening = Screening::factory()->paid(50)->create();
        $seatIds = Seat::limit(2)->pluck('seat_id')->all();

        // Skipping the review shows the review page and saves nothing.
        $this->post(route('bookings.store', $screening), $this->payload($seatIds, [], reviewed: false))
            ->assertOk()->assertSee('Review your booking')->assertSee('name="reviewed" value="1"', false);
        $this->assertSame(0, Reservation::count());
    }

    public function test_review_page_shows_every_detail_and_the_discounted_total(): void
    {
        $screening = Screening::factory()->paid(50)->create();
        $seatIds = Seat::limit(2)->pluck('seat_id')->all();

        $this->post(route('bookings.review', $screening), $this->payload($seatIds, [1 => ['senior_card_no' => 'OSCA-0077']]))
            ->assertOk()
            ->assertSee('Guest0 Cruz')->assertSee('Ateneo de Davao')->assertSee('OSCA-0077')
            ->assertSee('20% off')->assertSee('₱90.00')
            ->assertSee('Confirm and pay')->assertSee('Edit details');
        $this->assertSame(0, Reservation::count());
    }

    public function test_edit_details_goes_back_to_the_form_with_everything_kept(): void
    {
        $screening = Screening::factory()->create();
        $seatIds = Seat::limit(2)->pluck('seat_id')->all();

        $this->post(route('bookings.review', $screening), [...$this->payload($seatIds), 'edit' => 1])
            ->assertRedirect(route('bookings.create', [$screening, 'seats' => $seatIds]))
            ->assertSessionHasInput('attendees');
    }

    public function test_school_or_company_is_required_and_marked(): void
    {
        $screening = Screening::factory()->create();
        $seat = Seat::first();
        $payload = $this->payload([$seat->seat_id]);
        $payload['attendees'][$seat->seat_id]['company_school'] = '';

        $this->post(route('bookings.review', $screening), $payload)
            ->assertSessionHasErrors("attendees.{$seat->seat_id}.company_school");

        $this->get(route('bookings.create', [$screening, 'seats' => [$seat->seat_id]]))
            ->assertOk()->assertSee('class="req"', false)->assertDontSee('OWWA')
            ->assertSee(route('bookings.review', $screening), false);
    }

    public function test_floor_plan_puts_the_screen_at_the_bottom_and_exits_by_row_b(): void
    {
        $html = $this->get(route('bookings.create', Screening::factory()->create()))->assertOk()->getContent();

        $this->assertLessThan(strpos($html, 'screen-bar'), strpos($html, 'entrance-mark'));   // entrance above, screen below
        $this->assertLessThan(strpos($html, 'title="Seat A1"'), strpos($html, 'title="Seat J1"')); // row J first, A last
        preg_match_all('/<div class="seat-row">.*?<\/div>/s', $html, $rows);
        $exitRows = array_values(array_filter($rows[0], fn ($row) => str_contains($row, 'exit-sign')));
        $this->assertCount(1, $exitRows);
        $this->assertStringContainsString('title="Seat B1"', $exitRows[0]);
    }

    public function test_the_footer_wordmark_banner_is_gone(): void
    {
        $this->get(route('home'))->assertOk()->assertDontSee('footer-wordmark', false);
    }
}

<?php

namespace Tests\Feature;

use App\Models\Movie;
use App\Models\Program;
use App\Models\Reservation;
use App\Models\ReservationAttendee;
use App\Models\Screening;
use App\Models\Seat;
use App\Models\User;
use Database\Seeders\SeatSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * 2026-10-11 refinements: PayMongo without card payments and without a doubled +63, the home banner
 * (upcoming films, else recent screenings per program; logline / director / programme – rating – genres),
 * the phone mask and Philippine ID number rules, and the trimmed booking copy.
 */
class BookingRefinementsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SeatSeeder::class);
        Mail::fake();
    }

    private function booking(array $attendee = []): array
    {
        $seat = Seat::first();

        return [
            'reviewed' => 1,
            'seat_ids' => [$seat->seat_id],
            'attendees' => [$seat->seat_id => [
                'first_name' => 'Ana', 'last_name' => 'Cruz', 'age' => 30, 'sex' => 'F', 'company_school' => 'Ateneo de Davao',
                'contact_no' => '917 123 4567', 'email' => 'ana@example.test', ...$attendee,
            ]],
        ];
    }

    public function test_paymongo_gets_the_national_phone_number_and_never_card(): void
    {
        config(['services.paymongo.secret_key' => 'sk_test_fake', 'services.paymongo.payment_method_types' => ['card', 'gcash', 'paymaya']]);
        Http::fake(['https://api.paymongo.com/v1/checkout_sessions' => Http::response(['data' => ['id' => 'cs_1', 'attributes' => [
            'checkout_url' => 'https://checkout.paymongo.com/cs_1', 'status' => 'active',
        ]]])]);

        $this->post(route('bookings.store', Screening::factory()->paid(150)->create()), $this->booking())
            ->assertRedirect(); // → bookings.pay
        $this->get(route('bookings.pay', Reservation::firstOrFail()))->assertRedirect('https://checkout.paymongo.com/cs_1');

        Http::assertSent(fn (Request $r) => $r['data']['attributes']['billing']['phone'] === '9171234567'
            && $r['data']['attributes']['payment_method_types'] === ['gcash', 'paymaya']);
        $this->assertSame('+639171234567', Reservation::firstOrFail()->lead_contact_no); // stored in full
        $this->get(route('bookings.show', Reservation::firstOrFail()))->assertDontSee('Card');
    }

    public function test_pwd_and_senior_id_numbers_follow_the_philippine_rules(): void
    {
        $screening = Screening::factory()->create();
        $key = 'attendees.'.Seat::first()->seat_id.'.';

        foreach (['PWD-1', '11-2402-000-123', '11240200000012345'] as $bad) {
            $this->post(route('bookings.review', $screening), $this->booking(['pwd_id_no' => $bad]))->assertSessionHasErrors($key.'pwd_id_no');
        }
        foreach (['123', 'OSCA#0001', str_repeat('9', 21)] as $bad) {
            $this->post(route('bookings.review', $screening), $this->booking(['senior_card_no' => $bad]))->assertSessionHasErrors($key.'senior_card_no');
        }

        // 16 digits in any grouping are stored as RR-PPMM-BBB-NNNNNNN; OSCA IDs keep letters, spaces and hyphens.
        $this->post(route('bookings.store', $screening), $this->booking(['pwd_id_no' => '1124020000001234', 'senior_card_no' => 'DVO 2024-00123']))
            ->assertSessionHasNoErrors();
        $attendee = ReservationAttendee::firstOrFail();
        $this->assertSame(['11-2402-000-0001234', 'DVO 2024-00123'], [$attendee->pwd_id_no, $attendee->senior_card_no]);
    }

    public function test_the_details_form_has_the_masks_and_none_of_the_removed_copy(): void
    {
        $screening = Screening::factory()->paid(150)->create();
        $seat = Seat::first();

        $this->get(route('bookings.create', [$screening, 'seats' => [$seat->seat_id]]))->assertOk()
            ->assertSee('<span class="phone__prefix" aria-hidden="true">+63</span>', false)
            ->assertSee('placeholder="9XX XXX XXXX"', false)
            ->assertSee('placeholder="RR-PPMM-BBB-NNNNNNN"', false)
            ->assertDontSee('OWWA')
            ->assertDontSee('get 20% off their own ticket')
            ->assertDontSee('You can check everything before you');
    }

    public function test_banner_shows_the_six_soonest_films_with_logline_director_and_programme_line(): void
    {
        $program = Program::factory()->create(['name' => 'World Cinema']);
        $movie = Movie::factory()->create(['title' => 'Himala', 'rating' => 'PG', 'logline' => null,
            'synopsis' => 'A young woman claims to see the Virgin Mary. The town changes forever.']);
        $movie->syncDetails(['Drama', 'Historical'], 'Ishmael Bernal', null);
        Screening::factory()->create(['movie_id' => $movie->movie_id, 'program_id' => $program->program_id, 'event_date' => today()->addDay()]);
        foreach (range(2, 8) as $day) {
            Screening::factory()->create(['event_title' => "Later Film {$day}", 'event_date' => today()->addDays($day)]);
        }

        $html = $this->get(route('home'))->assertOk()->getContent();
        $banner = substr($html, strpos($html, 'class="spotlight"'), strpos($html, 'spotlight-cta') - strpos($html, 'class="spotlight"'));

        $this->assertSame(6, substr_count($banner, 'data-spotlight-slide='));
        $this->assertStringContainsString('A young woman claims to see the Virgin Mary.', $banner); // first sentence of the synopsis
        $this->assertStringNotContainsString('The town changes forever.', $banner);
        $this->assertStringContainsString('Directed by <strong>Ishmael Bernal</strong>', $banner);
        $this->assertStringContainsString('World Cinema - PG - Drama, Historical', $banner);
        $this->assertStringContainsString('Later Film 6', $banner);
        $this->assertStringNotContainsString('Later Film 7', $banner); // 6 slides, soonest first
        $this->assertStringContainsString('See full schedule', $html);
    }

    public function test_without_upcoming_screenings_the_banner_shows_recent_ones_from_different_programs(): void
    {
        [$a, $b, $c] = Program::factory()->count(3)->create();
        foreach ([[$a, 2, 'Alpha One'], [$a, 3, 'Alpha Two'], [$a, 4, 'Alpha Three'], [$b, 5, 'Bravo One'], [$c, 30, 'Charlie One'], [$a, 40, 'Alpha Old'], [$a, 50, 'Alpha Oldest']] as [$program, $daysAgo, $title]) {
            Screening::factory()->create(['program_id' => $program->program_id, 'event_title' => $title, 'event_date' => today()->subDays($daysAgo)]);
        }
        Screening::factory()->draft()->create(['event_title' => 'Draft Past', 'event_date' => today()->subDay()]);

        $html = $this->get(route('home'))->assertOk()->getContent();

        $this->assertSame(5, substr_count($html, 'data-spotlight-slide='));
        // One per program first (newest of each), then the most recent of the rest.
        foreach (['Alpha One', 'Bravo One', 'Charlie One', 'Alpha Two', 'Alpha Three'] as $title) {
            $this->assertStringContainsString($title, $html);
        }
        $this->assertStringNotContainsString('Alpha Oldest', $html);
        $this->assertStringNotContainsString('Draft Past', $html);
        $this->assertStringContainsString('Recently screened', $html);
        $this->assertStringNotContainsString('Book Seats', $html);
    }

    public function test_the_film_form_saves_a_logline(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('staff.movies.store'), ['title' => 'Himala', 'logline' => 'A miracle, or not.'])->assertSessionHasNoErrors();

        $this->assertSame('A miracle, or not.', Movie::firstOrFail()->loglineText());
        $this->assertSame(str_repeat('a', 120).'...', Movie::make(['synopsis' => str_repeat('a', 200)])->loglineText());
    }
}

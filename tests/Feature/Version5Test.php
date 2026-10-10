<?php

namespace Tests\Feature;

use App\Models\Movie;
use App\Models\Reservation;
use App\Models\ReservationAttendee;
use App\Models\Screening;
use App\Models\Seat;
use App\Models\User;
use Database\Seeders\SeatSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Version 5 spec: form validation, find-by-name, Now Showing / Upcoming, movie and screening forms. */
class Version5Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SeatSeeder::class);
    }

    private function payload(Seat $seat, array $attendee = [], array $lead = []): array
    {
        return array_merge([
            'reviewed' => 1,
            'seat_ids' => [$seat->seat_id],
            'lead_first_name' => 'Ana', 'lead_last_name' => 'Santos',
            'lead_contact_no' => '09171234567', 'lead_email' => 'ana@example.test',
            'attendees' => [$seat->seat_id => array_merge([
                'first_name' => 'Ana', 'last_name' => 'Santos', 'age' => 30, 'sex' => 'F',
                'company_school' => 'UP Mindanao', 'contact_no' => '09171234567', 'email' => 'ana@example.test',
            ], $attendee)],
        ], $lead);
    }

    public function test_phone_numbers_must_be_philippine_mobile_numbers(): void
    {
        $screening = Screening::factory()->create();
        $seat = Seat::first();

        $this->post(route('bookings.store', $screening), $this->payload($seat, ['contact_no' => '12345'], ['lead_contact_no' => '0812']))
            ->assertSessionHasErrors(['lead_contact_no', 'attendees.'.$seat->seat_id.'.contact_no']);

        $this->post(route('bookings.store', $screening), $this->payload($seat, ['contact_no' => '+63 917 765 4321'], ['lead_contact_no' => '0917-123-4567']))
            ->assertSessionHasNoErrors();
        $this->assertSame('09171234567', Reservation::firstOrFail()->lead_contact_no);
        $this->assertSame('+639177654321', ReservationAttendee::firstOrFail()->contact_no);
    }

    public function test_every_attendee_detail_is_required_except_middle_name_senior_and_pwd_ids(): void
    {
        $screening = Screening::factory()->create();
        $seat = Seat::first();
        $key = 'attendees.'.$seat->seat_id.'.';

        $this->post(route('bookings.store', $screening), $this->payload($seat, ['age' => '', 'sex' => '', 'company_school' => '', 'contact_no' => '', 'email' => '']))
            ->assertSessionHasErrors([$key.'age', $key.'sex', $key.'company_school', $key.'contact_no', $key.'email']);

        $this->post(route('bookings.store', $screening), $this->payload($seat, ['pwd_id_no' => '11-2402-000-0001234']))->assertSessionHasNoErrors();
        $attendee = ReservationAttendee::firstOrFail();
        $this->assertSame('11-2402-000-0001234', $attendee->pwd_id_no);
        $this->assertTrue($attendee->pwd_indicator);
        $this->assertNull($attendee->middle_name);
        $this->assertNull($attendee->senior_card_no);
    }

    public function test_find_my_booking_by_email_lists_only_upcoming_bookings_and_opens_the_ticket(): void
    {
        $soon = \App\Models\Screening::factory()->create(['event_date' => today()->addDays(2)]);
        $one = Reservation::factory()->for($soon)->withSeats(1)->create(['lead_email' => 'maria@example.test']);
        $past = \App\Models\Screening::factory()->create(['event_date' => today()->subDays(3)]);
        Reservation::factory()->for($past)->withSeats(1)->create(['lead_email' => 'maria@example.test']);

        // One upcoming booking: straight to its ticket (case-insensitive email).
        $this->get(route('bookings.lookup', ['email' => 'MARIA@example.test']))->assertRedirect(route('bookings.ticket', $one));
        $this->get(route('bookings.lookup', ['email' => 'someone@example.test']))->assertSessionHasErrors('email');
        $this->get(route('bookings.lookup', ['reference' => '', 'email' => '']))->assertSessionHasErrors('reference');

        // Several upcoming: a list of tickets (the past booking is not shown).
        $two = Reservation::factory()->for($soon)->withSeats(1)->create(['lead_email' => 'maria@example.test']);
        $this->get(route('bookings.lookup', ['email' => 'maria@example.test']))
            ->assertOk()->assertSee($one->booking_reference)->assertSee($two->booking_reference)
            ->assertSee(route('bookings.ticket', $two), false);
    }

    public function test_home_splits_now_showing_advance_booking_and_coming_soon(): void
    {
        Screening::factory()->create(['event_title' => 'This Week Film', 'event_date' => today()->addDays(2)]);
        Screening::factory()->create(['event_title' => 'Later Film', 'event_date' => today()->addDays(20)]);
        Screening::factory()->create(['event_title' => 'Far Film (35mm) + Q&A', 'event_date' => today()->addDays(45)]);

        $html = $this->get(route('home'))->assertOk()->assertDontSee('Book Now')->getContent(); // the whole card is the link
        $panel = fn (string $key) => preg_match('~id="showing-'.$key.'".*?</section>~s', $html, $m) ? $m[0] : '';

        $this->assertStringContainsString('This Week Film', $panel('now'));
        $this->assertStringNotContainsString('Later Film', $panel('now'));
        $this->assertStringContainsString('Later Film', $panel('advance'));
        $this->assertStringContainsString('Far Film', $panel('soon'));
        // Special Screenings: the 35mm + Q&A screening (no film linked, so all three are special programmes).
        $this->assertStringContainsString('Far Film', $panel('special'));
        $this->assertStringContainsString('35mm Film Print', $panel('soon'));
        $this->assertStringContainsString('Director Q&amp;A', $panel('soon'));
    }

    public function test_spotlight_and_cards_show_the_curators_note_and_play_youtube_trailers_in_the_page(): void
    {
        $movie = Movie::factory()->create([
            'title' => 'Himala', 'release_year' => (int) date('Y'),
            'curator_note' => 'A defining performance.', 'trailer_url' => 'https://www.youtube.com/watch?v=abcdefghijk',
            'logline' => 'A girl in a drought-stricken town claims to see the Virgin Mary.',
        ]);
        $screening = Screening::factory()->create(['movie_id' => $movie->movie_id, 'event_title' => 'Himala', 'event_date' => today()->addDay()]);

        $this->get(route('home'))->assertOk()
            ->assertSee('class="spotlight"', false)
            ->assertSee('A girl in a drought-stricken town claims to see the Virgin Mary.') // the logline, in the banner
            ->assertSee("Curator's Pick")
            ->assertSee('data-trailer="https://www.youtube-nocookie.com/embed/abcdefghijk"', false);
        $this->get(route('screenings.show', $screening))->assertSee('A defining performance.');

        $this->assertNull(Movie::make(['trailer_url' => 'https://example.com/trailer.mp4'])->trailerEmbedUrl());
        $this->assertSame('https://player.vimeo.com/video/76979871', Movie::make(['trailer_url' => 'https://vimeo.com/76979871'])->trailerEmbedUrl());
    }

    public function test_home_shows_one_card_per_film_and_the_film_page_lists_every_showtime(): void
    {
        $movie = Movie::factory()->create(['title' => 'Himala']);
        $first = Screening::factory()->create(['movie_id' => $movie->movie_id, 'event_title' => 'Himala', 'event_date' => today()->addDay()]);
        $second = Screening::factory()->create(['movie_id' => $movie->movie_id, 'event_title' => 'Himala', 'event_date' => today()->addDays(3)]);

        $html = $this->get(route('home'))->assertOk()->getContent();
        $this->assertSame(1, substr_count($html, 'class="film-card"'));
        $this->assertStringNotContainsString('class="showtime', $html); // times and seats live on the film page

        // Every showtime row has its own Choose seats; the screening you came from is marked.
        $page = $this->get(route('screenings.show', $second))->assertOk()->getContent();
        $this->assertSame(1, substr_count($page, route('bookings.create', $first).'"'));
        $this->assertSame(1, substr_count($page, route('bookings.create', $second).'"'));
        $this->assertSame(2, substr_count($page, '>Reserve seats<')); // free screenings; paid ones say "Get tickets"
        $this->assertSame(1, substr_count($page, 'aria-current="true"'));
        $this->assertStringContainsString('class="showrow is-chosen"', $page);
        $this->assertStringNotContainsString('Your screening', $page);
    }

    public function test_movie_ratings_come_from_the_dropdown_list(): void
    {
        $this->actingAs(User::factory()->create());

        $this->post(route('staff.movies.store'), ['title' => 'A', 'rating' => 'XYZ'])->assertSessionHasErrors('rating');
        $this->post(route('staff.movies.store'), ['title' => 'B', 'rating' => 'R-13'])->assertSessionHasNoErrors();
        $this->assertSame('R-13', Movie::where('title', 'B')->value('rating'));
    }

    public function test_choosing_a_film_fills_in_the_title_and_end_time(): void
    {
        $staff = User::factory()->create();
        $movie = Movie::factory()->create(['title' => 'Himala', 'runtime_minutes' => 124]);

        $this->actingAs($staff)->post(route('staff.screenings.store'), [
            'program_id' => \App\Models\Program::factory()->create()->program_id,
            'movie_id' => $movie->movie_id, 'event_title' => '', 'event_date' => today()->addDay()->format('Y-m-d'),
            'start_time' => '17:00', 'end_time' => '', 'type' => 'free',
        ])->assertSessionHasNoErrors();

        $screening = Screening::firstOrFail();
        $this->assertSame('Himala', $screening->event_title);
        $this->assertSame('19:04', substr($screening->end_time, 0, 5));
    }

    public function test_admin_top_bar_has_the_global_search_and_an_avatar_menu(): void
    {
        $staff = User::factory()->create(['first_name' => 'Zed', 'last_name' => 'Quill']);

        // v5 removed the search; the staff redesign (v6) brought it back on request.
        $html = $this->actingAs($staff)->get(route('staff.dashboard'))->assertOk()->getContent();
        $this->assertStringContainsString('id="global-search"', $html);
        $this->assertStringContainsString('usermenu__caret', $html);
    }

    public function test_global_search_finds_bookings_screenings_and_films(): void
    {
        $staff = User::factory()->create();
        $movie = Movie::factory()->create(['title' => 'Himala']);
        $screening = Screening::factory()->create(['event_title' => 'Himala', 'movie_id' => $movie->movie_id]);
        $booking = Reservation::factory()->for($screening)->withSeats(1)->create(['lead_first_name' => 'Nora', 'lead_last_name' => 'Villamayor', 'lead_email' => 'nora@example.test']);

        $this->actingAs($staff)->get(route('staff.search', ['q' => strtolower($booking->booking_reference)]))
            ->assertRedirect(route('staff.reservations.show', $booking));

        $this->actingAs($staff)->get(route('staff.search', ['q' => 'himala']))->assertOk()
            ->assertSee('Screenings')->assertSee('Films')->assertSee(route('staff.movies.edit', $movie), false);

        $this->actingAs($staff)->get(route('staff.search', ['q' => 'Nora Villa']))->assertOk()
            ->assertSee($booking->booking_reference);
        $this->actingAs($staff)->get(route('staff.search', ['q' => 'nora@example']))->assertOk()
            ->assertSee($booking->booking_reference);
    }

    public function test_bookings_live_on_the_screening_roster_with_an_awaiting_payment_filter(): void
    {
        $staff = User::factory()->create();
        $screening = Screening::factory()->paid(150)->create();
        $unpaid = Reservation::factory()->for($screening)->awaitingPayment()->withSeats(1)->create();

        // The old Reservations pages redirect to the roster.
        $this->actingAs($staff)->get(route('staff.reservations.index'))->assertRedirect(route('staff.screenings.index'));
        $this->get(route('staff.reservations.show', $unpaid))->assertRedirect(route('staff.screenings.show', [$screening, 'open' => $unpaid->booking_reference]).'#booking-'.$unpaid->booking_reference);

        $this->get(route('staff.screenings.show', $screening))->assertOk()
            ->assertSee($unpaid->booking_reference)->assertSee('data-filter="pending"', false)
            ->assertSee(route('staff.reservations.resend', $unpaid), false)->assertDontSee('Approve');
    }
}

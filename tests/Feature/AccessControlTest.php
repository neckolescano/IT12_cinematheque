<?php

namespace Tests\Feature;

use App\Models\Screening;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccessControlTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_can_browse_public_pages(): void
    {
        $screening = Screening::factory()->create();

        $this->get(route('home'))->assertOk()->assertSee($screening->event_title);
        $this->get(route('screenings.show', $screening))->assertOk();
        $this->get(route('bookings.lookup'))->assertOk();
    }

    public function test_guests_are_redirected_from_every_staff_route(): void
    {
        foreach (['/ccdadmin', '/ccdadmin/screenings', '/ccdadmin/reservations', '/ccdadmin/users',
                  '/ccdadmin/reports', '/ccdadmin/reports/export', '/ccdadmin/movies'] as $url) {
            $this->get($url)->assertRedirect(route('login'));
        }
    }

    public function test_avt_and_pdo_have_identical_access(): void
    {
        foreach (User::POSITIONS as $position) {
            $user = User::factory()->create(['position' => $position]);

            $this->actingAs($user)->get('/ccdadmin')->assertOk();
            $this->actingAs($user)->get('/ccdadmin/screenings')->assertOk();
            $this->actingAs($user)->get('/ccdadmin/users')->assertOk();
            $this->actingAs($user)->get('/ccdadmin/reports')->assertOk();
        }
    }

    public function test_inactive_staff_cannot_log_in(): void
    {
        User::factory()->inactive()->create(['email' => 'old@example.test']);

        $this->post('/ccdadmin/login', ['email' => 'old@example.test', 'password' => 'password'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_active_staff_can_log_in_and_out(): void
    {
        User::factory()->create(['email' => 'staff@example.test']);

        $this->post('/ccdadmin/login', ['email' => 'staff@example.test', 'password' => 'password'])
            ->assertRedirect(route('staff.dashboard'));
        $this->assertAuthenticated();

        $this->post('/ccdadmin/logout')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_staff_deactivated_mid_session_is_logged_out(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get('/ccdadmin')->assertOk();

        $user->update(['is_active' => false]);

        $this->actingAs($user)->get('/ccdadmin')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_staff_cannot_deactivate_themselves(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->put(route('staff.users.update', $user), [
            'first_name' => $user->first_name, 'last_name' => $user->last_name,
            'email' => $user->email, 'is_active' => '0',
        ])->assertSessionHasErrors('is_active');

        $this->assertTrue($user->fresh()->is_active);
    }

    public function test_customer_pages_never_link_to_the_admin_side_even_for_staff(): void
    {
        $screening = Screening::factory()->create();
        $reservation = \App\Models\Reservation::factory()->for($screening)->withSeats(1)->create();

        $this->actingAs(User::factory()->create());
        foreach ([route('home'), route('screenings.show', $screening), route('bookings.show', $reservation), route('bookings.lookup'), route('about')] as $url) {
            $this->get($url)->assertOk()->assertDontSee('Staff view')->assertDontSee('ccdadmin');
        }
    }

    public function test_customer_and_admin_live_under_their_own_prefixes(): void
    {
        $this->get('/')->assertRedirect('/cinemathequecentredavao');
        $this->assertSame(url('/cinemathequecentredavao'), route('home'));
        $this->assertSame(url('/ccdadmin/login'), route('login'));
        $this->get('/ccdadmin/login')->assertOk()->assertSee('Staff sign in');
    }

    public function test_customer_pages_do_not_advertise_the_admin_area(): void
    {
        Screening::factory()->create();

        $this->get(route('home'))->assertOk()
            ->assertDontSee('ccdadmin')
            ->assertDontSee('Staff login');
    }

    public function test_logged_in_staff_opening_the_login_page_go_to_the_dashboard(): void
    {
        $this->actingAs(User::factory()->create())->get('/ccdadmin/login')->assertRedirect(route('staff.dashboard'));
    }
}

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

        $this->get('/')->assertOk()->assertSee($screening->event_title);
        $this->get(route('screenings.show', $screening))->assertOk();
        $this->get(route('bookings.lookup'))->assertOk();
    }

    public function test_guests_are_redirected_from_every_staff_route(): void
    {
        foreach (['/staff', '/staff/screenings', '/staff/reservations', '/staff/payment-proofs',
                  '/staff/qr-codes', '/staff/users', '/staff/reports', '/staff/movies'] as $url) {
            $this->get($url)->assertRedirect(route('login'));
        }
    }

    public function test_avt_and_pdo_have_identical_access(): void
    {
        foreach (User::POSITIONS as $position) {
            $user = User::factory()->create(['position' => $position]);

            $this->actingAs($user)->get('/staff')->assertOk();
            $this->actingAs($user)->get('/staff/payment-proofs')->assertOk();
            $this->actingAs($user)->get('/staff/users')->assertOk();
            $this->actingAs($user)->get('/staff/reports')->assertOk();
        }
    }

    public function test_inactive_staff_cannot_log_in(): void
    {
        User::factory()->inactive()->create(['email' => 'old@example.test']);

        $this->post('/login', ['email' => 'old@example.test', 'password' => 'password'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_active_staff_can_log_in_and_out(): void
    {
        User::factory()->create(['email' => 'staff@example.test']);

        $this->post('/login', ['email' => 'staff@example.test', 'password' => 'password'])
            ->assertRedirect(route('staff.dashboard'));
        $this->assertAuthenticated();

        $this->post('/logout')->assertRedirect(route('home'));
        $this->assertGuest();
    }

    public function test_staff_deactivated_mid_session_is_logged_out(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get('/staff')->assertOk();

        $user->update(['is_active' => false]);

        $this->actingAs($user)->get('/staff')->assertRedirect(route('login'));
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

    public function test_staff_links_only_render_for_staff(): void
    {
        $screening = Screening::factory()->create();

        $this->get(route('screenings.show', $screening))->assertDontSee('Staff view of this screening');
        $this->actingAs(User::factory()->create())
            ->get(route('screenings.show', $screening))->assertSee('Staff view of this screening');
    }
}

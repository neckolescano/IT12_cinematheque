<?php

namespace Tests\Feature;

use App\Mail\ReservationApprovedMail;
use App\Models\Reservation;
use App\Models\Screening;
use Database\Seeders\SeatSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * PayMongo is simulated with Http::fake(), so these tests prove how the app reacts to
 * PayMongo's responses. They do not contact PayMongo's real sandbox.
 */
class PayMongoPaymentTest extends TestCase
{
    use RefreshDatabase;

    private const API = 'https://api.paymongo.com/v1/checkout_sessions';

    private Reservation $reservation;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.paymongo.secret_key' => 'sk_test_fake', 'services.paymongo.webhook_secret' => 'whsk_test_secret']);
        Mail::fake();
        $this->seed(SeatSeeder::class);

        $this->reservation = Reservation::factory()->for(Screening::factory()->paid(150))->pending()->withSeats(2)->create();
        $this->reservation->payment()->create(['amount' => 300, 'status' => 'pending']);
    }

    private function checkoutSession(string $id, string $status = 'active', array $payments = [], ?string $reference = null): array
    {
        return ['data' => ['id' => $id, 'type' => 'checkout_session', 'attributes' => [
            'checkout_url' => 'https://checkout.paymongo.com/'.$id,
            'status' => $status,
            'reference_number' => $reference ?? $this->reservation->booking_reference,
            'payments' => $payments,
        ]]];
    }

    private function paidPayment(int $centavos = 30000): array
    {
        return ['id' => 'pay_test_123', 'type' => 'payment', 'attributes' => [
            'amount' => $centavos, 'status' => 'paid', 'paid_at' => now()->timestamp, 'source' => ['type' => 'gcash'],
        ]];
    }

    public function test_pay_creates_a_checkout_session_and_redirects_to_paymongo(): void
    {
        Http::fake([self::API => Http::response($this->checkoutSession('cs_test_1'))]);

        $this->get(route('bookings.pay', $this->reservation))->assertRedirect('https://checkout.paymongo.com/cs_test_1');

        $this->assertSame('cs_test_1', $this->reservation->payment->fresh()->provider_session_id);
        Http::assertSent(function (Request $r) {
            return $r->method() === 'POST'
                && $r->url() === self::API
                && $r->hasHeader('Authorization', 'Basic '.base64_encode('sk_test_fake:'))
                && $r['data']['attributes']['line_items'][0]['amount'] === 30000
                && $r['data']['attributes']['reference_number'] === $this->reservation->booking_reference
                && $r['data']['attributes']['success_url'] === route('bookings.payment.return', $this->reservation);
        });
        $this->assertSame('pending', $this->reservation->fresh()->status); // reaching checkout proves nothing
    }

    public function test_an_open_session_is_reused_instead_of_creating_another(): void
    {
        $this->reservation->payment->update(['provider_session_id' => 'cs_open']);
        Http::fake([self::API.'/cs_open' => Http::response($this->checkoutSession('cs_open'))]);

        $this->get(route('bookings.pay', $this->reservation))->assertRedirect('https://checkout.paymongo.com/cs_open');

        Http::assertSentCount(1);
        Http::assertNotSent(fn (Request $r) => $r->method() === 'POST');
    }

    public function test_returning_without_a_paid_payment_keeps_the_booking_pending(): void
    {
        $this->reservation->payment->update(['provider_session_id' => 'cs_unpaid']);
        Http::fake([self::API.'/cs_unpaid' => Http::response($this->checkoutSession('cs_unpaid'))]);

        $this->get(route('bookings.payment.return', $this->reservation))
            ->assertRedirect(route('bookings.show', $this->reservation))
            ->assertSessionHas('warning');

        $this->assertSame('pending', $this->reservation->payment->fresh()->status);
        $this->assertSame('pending', $this->reservation->fresh()->status);
        Mail::assertNothingSent();
    }

    public function test_paid_session_confirms_the_booking_and_sends_one_e_ticket(): void
    {
        $this->reservation->payment->update(['provider_session_id' => 'cs_paid']);
        Http::fake([self::API.'/cs_paid' => Http::response($this->checkoutSession('cs_paid', 'active', [$this->paidPayment()]))]);

        $this->get(route('bookings.payment.return', $this->reservation))->assertSessionHas('status');
        $this->get(route('bookings.payment.return', $this->reservation)); // repeat: must not double-send

        $payment = $this->reservation->payment->fresh();
        $this->assertSame('verified', $payment->status);
        $this->assertSame('pay_test_123', $payment->provider_payment_id);
        $this->assertSame('gcash', $payment->payment_channel);
        $this->assertNotNull($payment->paid_at);
        $this->assertSame('confirmed', $this->reservation->fresh()->status);
        Mail::assertSent(ReservationApprovedMail::class, 1);
    }

    public function test_wrong_amount_or_wrong_booking_is_never_accepted(): void
    {
        $this->reservation->payment->update(['provider_session_id' => 'cs_bad']);
        Http::fakeSequence(self::API.'/cs_bad')
            ->push($this->checkoutSession('cs_bad', 'active', [$this->paidPayment(100)]))
            ->push($this->checkoutSession('cs_bad', 'active', [$this->paidPayment()], 'CCD-SOMEONEELSE'));

        $this->get(route('bookings.payment.return', $this->reservation));
        $this->get(route('bookings.payment.return', $this->reservation));

        $this->assertSame('pending', $this->reservation->payment->fresh()->status);
        $this->assertSame('pending', $this->reservation->fresh()->status);
    }

    public function test_signed_webhook_settles_the_payment(): void
    {
        $this->reservation->payment->update(['provider_session_id' => 'cs_hook']);
        Http::fake([self::API.'/cs_hook' => Http::response($this->checkoutSession('cs_hook', 'active', [$this->paidPayment()]))]);

        $body = json_encode(['data' => ['id' => 'evt_1', 'attributes' => [
            'type' => 'checkout_session.payment.paid', 'livemode' => false,
            'data' => ['id' => 'cs_hook', 'attributes' => ['reference_number' => $this->reservation->booking_reference]],
        ]]]);
        $t = time();
        $sig = hash_hmac('sha256', $t.'.'.$body, 'whsk_test_secret');

        $this->call('POST', route('webhooks.paymongo'), [], [], [], ['HTTP_PAYMONGO_SIGNATURE' => "t={$t},te={$sig},li=", 'CONTENT_TYPE' => 'application/json'], $body)
            ->assertOk();

        $this->assertSame('confirmed', $this->reservation->fresh()->status);
        $this->assertSame('verified', $this->reservation->payment->fresh()->status);
    }

    public function test_webhook_with_a_bad_signature_is_rejected(): void
    {
        Http::fake();
        $body = json_encode(['data' => ['attributes' => ['type' => 'checkout_session.payment.paid', 'livemode' => false, 'data' => ['id' => 'cs_x']]]]);

        $this->call('POST', route('webhooks.paymongo'), [], [], [], ['HTTP_PAYMONGO_SIGNATURE' => 't=1,te=forged,li=', 'CONTENT_TYPE' => 'application/json'], $body)
            ->assertStatus(401);

        Http::assertNothingSent();
        $this->assertSame('pending', $this->reservation->fresh()->status);
    }

    public function test_paymongo_errors_and_missing_keys_are_handled(): void
    {
        Http::fake([self::API => Http::response(['errors' => [['detail' => 'Something went wrong']]], 500)]);
        $this->get(route('bookings.pay', $this->reservation))
            ->assertRedirect(route('bookings.show', $this->reservation))->assertSessionHasErrors('payment');

        config(['services.paymongo.secret_key' => null]);
        $this->get(route('bookings.pay', $this->reservation))->assertSessionHasErrors('payment');

        $this->assertSame('pending', $this->reservation->fresh()->status);
    }

    public function test_staff_can_refresh_payment_status_from_paymongo(): void
    {
        $this->reservation->payment->update(['provider_session_id' => 'cs_staff']);
        Http::fake([self::API.'/cs_staff' => Http::response($this->checkoutSession('cs_staff', 'active', [$this->paidPayment()]))]);

        $this->actingAs(\App\Models\User::factory()->create())
            ->post(route('staff.reservations.sync-payment', $this->reservation))
            ->assertSessionHas('status', 'PayMongo reports this booking as paid.');

        $this->assertSame('confirmed', $this->reservation->fresh()->status);
    }
}

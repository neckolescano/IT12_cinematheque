<?php

namespace Tests\Feature;

use App\Models\PaymentQrCode;
use App\Models\Reservation;
use App\Models\Screening;
use App\Models\User;
use Database\Seeders\SeatSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PaymentReviewTest extends TestCase
{
    use RefreshDatabase;

    /** 1x1 PNG, so tests do not need the GD extension that UploadedFile::fake()->image() requires. */
    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAIAAACQd1PeAAAADElEQVR4nGP4//8/AAX+Av4N70a4AAAAAElFTkSuQmCC';

    private Reservation $reservation;

    private function png(string $name): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, base64_decode(self::PNG));
    }

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->seed(SeatSeeder::class);

        $this->reservation = Reservation::factory()
            ->for(Screening::factory()->paid(150))
            ->pending()->withSeats(2)->create();
        $this->reservation->payment()->create(['amount' => 300, 'status' => 'pending']);
    }

    private function uploadProof(): void
    {
        $this->post(route('bookings.proof.store', $this->reservation), [
            'proof_image' => $this->png('proof.png'),
            'payment_channel' => 'GCash',
        ])->assertRedirect(route('bookings.show', $this->reservation));
    }

    public function test_moviegoer_can_upload_proof_without_logging_in(): void
    {
        $this->uploadProof();

        $proof = $this->reservation->payment->proofs()->firstOrFail();
        $this->assertSame('pending', $proof->status);
        Storage::disk('public')->assertExists($proof->proof_image);
        // Uploading never verifies anything by itself.
        $this->assertSame('pending', $this->reservation->payment->fresh()->status);
        $this->assertSame('pending', $this->reservation->fresh()->status);
    }

    public function test_second_upload_blocked_while_one_is_pending(): void
    {
        $this->uploadProof();

        $this->post(route('bookings.proof.store', $this->reservation), [
            'proof_image' => $this->png('again.png'),
        ])->assertSessionHasErrors('proof_image');
        $this->assertSame(1, $this->reservation->payment->proofs()->count());
    }

    public function test_accepting_proof_verifies_payment_and_confirms_reservation(): void
    {
        $this->uploadProof();
        $staff = User::factory()->create(['position' => 'PDO']);
        $proof = $this->reservation->payment->proofs()->firstOrFail();

        $this->actingAs($staff)->patch(route('staff.payment-proofs.accept', $proof))->assertRedirect();

        $proof->refresh();
        $this->assertSame('accepted', $proof->status);
        $this->assertSame($staff->user_id, $proof->reviewed_by);
        $this->assertNotNull($proof->reviewed_at);
        $this->assertSame('verified', $this->reservation->payment->fresh()->status);
        $this->assertSame('confirmed', $this->reservation->fresh()->status);

        // A proof is reviewed once only.
        $this->actingAs($staff)->patch(route('staff.payment-proofs.accept', $proof))->assertForbidden();
    }

    public function test_rejecting_proof_keeps_payment_pending_and_allows_resubmission(): void
    {
        $this->uploadProof();
        $staff = User::factory()->create();
        $proof = $this->reservation->payment->proofs()->firstOrFail();

        $this->actingAs($staff)->patch(route('staff.payment-proofs.reject', $proof), [])
            ->assertSessionHasErrors('rejection_reason');

        $this->actingAs($staff)->patch(route('staff.payment-proofs.reject', $proof), ['rejection_reason' => 'Amount does not match'])
            ->assertRedirect();

        $this->assertSame('rejected', $proof->fresh()->status);
        $this->assertSame('pending', $this->reservation->payment->fresh()->status);

        auth()->logout();
        $this->uploadProof();
        $this->assertSame(2, $this->reservation->payment->proofs()->count());
    }

    public function test_guest_cannot_review_proofs(): void
    {
        $this->uploadProof();
        $proof = $this->reservation->payment->proofs()->firstOrFail();

        $this->patch(route('staff.payment-proofs.accept', $proof))->assertRedirect(route('login'));
        $this->assertSame('pending', $proof->fresh()->status);
    }

    public function test_replacing_qr_code_leaves_exactly_one_active(): void
    {
        $staff = User::factory()->create();

        $this->actingAs($staff)->post(route('staff.qr-codes.store'), ['qr_image' => $this->png('qr1.png')]);
        $this->actingAs($staff)->post(route('staff.qr-codes.store'), ['qr_image' => $this->png('qr2.png')]);

        $this->assertSame(2, PaymentQrCode::count());
        $this->assertSame(1, PaymentQrCode::where('is_active', true)->count());

        $this->get(route('bookings.show', $this->reservation))->assertOk()->assertSee(PaymentQrCode::current()->qr_image);
    }
}

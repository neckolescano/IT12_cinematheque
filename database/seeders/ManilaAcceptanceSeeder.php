<?php

namespace Database\Seeders;

use App\Models\Movie;
use App\Models\Program;
use App\Models\Reservation;
use App\Models\Screening;
use App\Models\Seat;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Acceptance data (revision phase 5): the four example lines of Manila's February 2026 Free and Paid
 * Screening Report sheets, rebuilt as real screenings, bookings and door admissions, so the system's
 * program report can be compared with the sheets row by row (`php artisan reports:acceptance`).
 *
 * From the photos: the two free rows (Feb 18, Wed) are complete. For the paid rows the photos show the
 * viewers, tickets and sales but not the date, title, program name or PWD/Senior counts, so those are
 * placeholders here (Feb 20, "Paid example 1/2"). The ₱50 ticket price comes from the sheet's
 * 4 regular + 1 discount = ₱240.
 */
class ManilaAcceptanceSeeder extends Seeder
{
    public const PROGRAM = 'Acceptance: World Cinema, February 2026';

    public const PRICE = 50.00;

    /**
     * The sheets' lines: [date, time, title, type, M, F, regular, discount, sales, occupancy].
     * Discounted tickets go to the last seats of the booking (PWD ID).
     */
    public const SHEET_ROWS = [
        ['2026-02-18', '15:00', 'Ang Huling ChaCha Ni Anita', 'free', 6, 20, 0, 0, 0.00, 21.67],
        ['2026-02-18', '17:00', 'I Love You, Thank You', 'free', 15, 24, 0, 0, 0.00, 32.50],
        ['2026-02-20', '15:00', 'Paid example 1', 'paid', 2, 0, 2, 0, 100.00, 1.67],
        ['2026-02-20', '17:00', 'Paid example 2', 'paid', 2, 3, 4, 1, 240.00, 4.17],
    ];

    /** The program total those lines add up to (occupancy is the average of the rows). */
    public const SHEET_TOTAL = ['films' => 4, 'male' => 25, 'female' => 47, 'total_audience' => 72, 'occupancy_rate' => 15.00, 'regular_count' => 6, 'discount_count' => 1, 'total_sales' => 340.00];

    public function run(): void
    {
        if (Program::where('name', self::PROGRAM)->exists()) {
            $this->command?->warn('The acceptance program already exists; nothing was added.');

            return;
        }
        if (Seat::count() < Seat::CAPACITY) {
            $this->call(SeatSeeder::class);
        }

        DB::transaction(function () {
            $staff = User::where('is_active', true)->orderBy('user_id')->first() ?? User::factory()->create();
            $program = Program::create([
                'name' => self::PROGRAM,
                'description' => 'The example lines of the Manila Free and Paid Screening Report sheets, for checking the program report.',
            ]);

            foreach (self::SHEET_ROWS as [$date, $time, $title, $type, $male, $female, $regular, $discount]) {
                $movie = Movie::create(['title' => $title]);
                $movie->programs()->attach($program);

                $screening = Screening::create([
                    'event_title' => $title, 'movie_id' => $movie->movie_id, 'program_id' => $program->program_id,
                    'event_date' => $date, 'start_time' => $time, 'end_time' => date('H:i', strtotime($time) + 7200),
                    'type' => $type, 'price' => $type === 'paid' ? self::PRICE : null, 'created_by' => $staff->user_id,
                ]);

                // One party per screening; everyone in it was admitted at the door.
                $people = $male + $female;
                $party = Reservation::factory()->for($screening)->withSeats($people)->create(['status' => 'confirmed']);
                $seats = $party->reservationSeats()->with('attendee')->orderBy('seat_id')->get();

                foreach ($seats as $i => $seat) {
                    $seat->attendee->update(['sex' => $i < $male ? 'M' : 'F', 'pwd_id_no' => null, 'senior_card_no' => null, 'pwd_indicator' => false]);
                    if ($type === 'paid' && $i >= $people - $discount) {
                        $seat->update(['discount_type' => 'pwd', 'amount_due' => round(self::PRICE * 0.8, 2)]);
                        $seat->attendee->update(['pwd_id_no' => 'PWD-ACC-'.($i + 1), 'pwd_indicator' => true]);
                    }
                    $seat->attendance()->create(['checked_in_at' => "{$date} {$time}", 'checked_in_by' => $staff->user_id]);
                }

                if ($type === 'paid') {
                    $party->payment()->create([
                        'amount' => $seats->fresh()->sum(fn ($s) => (float) $s->amount_due),
                        'status' => 'verified', 'payment_channel' => 'gcash', 'paid_at' => "{$date} 09:00",
                    ]);
                }
            }
        });

        $this->command?->info('Acceptance program added: "'.self::PROGRAM.'". Generate its report under Program reports, or run `php artisan reports:acceptance`.');
    }
}

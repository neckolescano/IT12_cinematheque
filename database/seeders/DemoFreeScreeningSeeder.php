<?php

namespace Database\Seeders;

use App\Models\Movie;
use App\Models\Program;
use App\Models\Reservation;
use App\Models\Screening;
use App\Models\Seat;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * One free demo screening today, starting at the current hour (21:00 at the latest), so free booking
 * and check-in can be shown right away; it has one confirmed party of three. Part of DemoSeeder, and
 * safe to run on its own on the day of a demo (it adds a screening, it wipes nothing):
 *
 *   php artisan db:seed --class=DemoFreeScreeningSeeder
 */
class DemoFreeScreeningSeeder extends Seeder
{
    public const TITLE = 'Free Community Screening (Demo)';

    public function run(): void
    {
        $by = User::where('is_active', true)->orderBy('user_id')->firstOrFail();
        $program = Program::firstOrCreate(['name' => 'Community Screenings']);
        $movie = Movie::firstOrCreate(['title' => self::TITLE], [
            'runtime_minutes' => 120,
            'logline' => 'A free screening for trying out reservations and check-in at the door.',
        ]);
        $movie->programs()->syncWithoutDetaching([$program->program_id]);

        $start = now()->startOfHour()->min(today()->setTime(21, 0));
        $screening = Screening::create([
            'event_title' => self::TITLE,
            'movie_id' => $movie->movie_id,
            'films_count' => 1,
            'program_id' => $program->program_id,
            'event_date' => today(),
            'start_time' => $start->format('H:i'),
            'end_time' => $start->copy()->addHours(2)->format('H:i'),
            'type' => 'free',
            'price' => null,
            'total_seats' => Seat::CAPACITY,
            'created_by' => $by->user_id,
        ]);

        Reservation::factory()->for($screening)->withSeats(3)->create(['status' => 'confirmed']);

        $this->command?->info('Free demo screening added: today '.$start->format('g:i A').'–'.$start->copy()->addHours(2)->format('g:i A').'.');
    }
}

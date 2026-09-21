<?php

namespace Database\Seeders;

use App\Models\Cinema;
use App\Models\Movie;
use App\Models\Showtime;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // The users table = admins only. CHANGE THIS PASSWORD before going live.
        User::updateOrCreate(
            ['email' => 'admin@cinematheque.test'],
            ['name' => 'Admin', 'password' => Hash::make('password')]
        );

        // Cinemas 1-5, all free. To test the QR step later:
        // Cinema::find(5)->update(['requires_payment' => true, 'ticket_price' => 150, 'payment_qr_path' => 'qr/cinema-5.png']);
        $cinemas = [];
        foreach (range(1, 5) as $n) {
            $cinemas[$n] = Cinema::firstOrCreate(['name' => "Cinema $n"], [
                'seat_rows' => 10,
                'seats_per_row' => 12,
                'ticket_price' => 0,
                'requires_payment' => false,
            ]);
        }

        // Copy poster images to storage/app/public/posters/<file> (php artisan storage:link)
        // Age ratings and durations are placeholders, edit as needed.
        $nowShowing = [
            ['Scream 7', 'Horror', 'English', 103, 'R', 4.5, 'scream-7.jpg', 1],
            ['The Loved One', 'Romance', 'English', 103, 'PG-13', 4.2, 'the-loved-one.jpg', 2],
            ['Whistle', 'Horror', 'English', 103, 'R', 4.3, 'whistle.jpg', 3],
            ['1978', 'Horror', 'Filipino', 103, 'R', 4.6, '1978.jpg', 4],
            ['Goat', 'Animation', 'English', 103, 'PG', 4.4, 'goat.jpg', 5],
            ['Project Hail Mary', 'Sci-Fi', 'English', 142, 'PG-13', 4.8, 'project-hail-mary.jpg', 2],
            ['Iron Lung', 'Horror', 'English', 103, 'R', 4.1, 'iron-lung.jpg', 1],
        ];

        $slots = ['10:00', '13:00', '16:30', '19:00', '21:30'];
        $moviesInCinema = collect($nowShowing)->countBy(fn ($m) => $m[7]);
        $perCinema = [];

        foreach ($nowShowing as [$title, $genre, $language, $minutes, $age, $rating, $poster, $cinemaNo]) {
            $movie = Movie::updateOrCreate(['title' => $title], [
                'synopsis' => 'Synopsis coming soon.',
                'genre' => $genre,
                'language' => $language,
                'duration_minutes' => $minutes,
                'age_rating' => $age,
                'rating' => $rating,
                'poster_path' => "posters/$poster",
                'status' => 'now_showing',
                'release_date' => now()->subWeek(),
                'is_featured' => $title === 'Project Hail Mary',
            ]);

            // Cinemas that host 2 movies alternate slots so showtimes never overlap.
            $order = $perCinema[$cinemaNo] = ($perCinema[$cinemaNo] ?? -1) + 1;
            $mySlots = $moviesInCinema[$cinemaNo] === 1
                ? collect($slots)
                : collect($slots)->filter(fn ($_, $i) => $i % 2 === $order % 2);

            foreach (range(0, 6) as $day) {
                foreach ($mySlots as $time) {
                    Showtime::firstOrCreate([
                        'cinema_id' => $cinemas[$cinemaNo]->id,
                        'starts_at' => now()->addDays($day)->setTimeFromTimeString($time)->startOfMinute(),
                    ], ['movie_id' => $movie->id]);
                }
            }
        }

        $comingSoon = [
            ['Devil Black Cat', 'Horror', 'English', 'devil-black-cat.jpg', 7],
            ['Sisa', 'Drama', 'Filipino', 'sisa.jpg', 10],
            ['The Bridel', 'Horror', 'English', 'the-bridel.jpg', 14],
            ['A Special Memory', 'Drama', 'Filipino', 'a-special-memory.jpg', 21],
        ];

        foreach ($comingSoon as [$title, $genre, $language, $poster, $inDays]) {
            Movie::updateOrCreate(['title' => $title], [
                'genre' => $genre,
                'language' => $language,
                'duration_minutes' => 110,
                'poster_path' => "posters/$poster",
                'status' => 'coming_soon',
                'release_date' => now()->addDays($inDays),
            ]);
        }
    }
}

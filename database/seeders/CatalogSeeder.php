<?php

namespace Database\Seeders;

use App\Models\Director;
use App\Models\Genre;
use App\Models\Movie;
use App\Models\Program;
use Illuminate\Database\Seeder;
use Illuminate\Http\File;
use Illuminate\Support\Facades\Storage;

/**
 * Demo catalog: films from the Cinematheque Centre Davao lineup (FDCP, May 2026) with their
 * posters. Only facts from that lineup are seeded (title, year, director); runtime, rating,
 * synopsis, genres and cast are left for staff to fill in.
 *
 * Posters: database/seeders/posters/, the low-resolution copies Wikipedia hosts under fair
 * use (sources in posters/sources.json). For a class demo; credit the rights holders if they
 * appear in the paper.
 */
class CatalogSeeder extends Seeder
{
    /** title => [release year, directors, poster file in database/seeders/posters] */
    public const FILMS = [
        'Malvarosa' => [1958, ['Gregorio Fernandez'], 'malvarosa.jpg'],
        'Biyaya ng Lupa' => [1959, ['Manuel Silos'], 'biyaya-ng-lupa.jpg'],
        'Anak Dalita' => [1956, ['Lamberto V. Avellana'], 'anak-dalita.jpg'],
        'Himala' => [1982, ['Ishmael Bernal'], 'himala.jpg'],
        'It Was Just an Accident' => [2025, ['Jafar Panahi'], 'it-was-just-an-accident.jpg'],
        'Case 137' => [2025, ['Dominik Moll'], 'case-137.jpg'],
        'The Secret Agent' => [2025, ['Kleber Mendonça Filho'], 'the-secret-agent.png'],
        'Sound of Falling' => [2025, ['Mascha Schilinski'], 'sound-of-falling.webp'],
        'Resurrection' => [2025, ['Bi Gan'], 'resurrection.jpg'],
        'The Blue Trail' => [2025, ['Gabriel Mascaro'], 'the-blue-trail.jpg'],
        'Sentimental Value' => [2025, ['Joachim Trier'], 'sentimental-value.jpg'],
    ];

    /** The lineup's programs (parents of films and screenings) => the films in each. */
    public const PROGRAMS = [
        'Pamanang Pelikula: Tribute to LVN Pictures' => ['Malvarosa', 'Biyaya ng Lupa', 'Anak Dalita'],
        'Pamanang Pelikula: Tribute to Nora Aunor' => ['Himala'],
        'FDCP Presents: World Cinema' => ['It Was Just an Accident', 'Case 137', 'The Secret Agent', 'Sound of Falling', 'Resurrection', 'The Blue Trail', 'Sentimental Value'],
        'Community Screenings' => [],
    ];

    public function run(): void
    {
        // The fixed genre list (not assigned: the lineup doesn't list genres).
        collect(Movie::GENRES)
            ->each(fn ($name) => Genre::firstOrCreate(['genre_name' => $name]));

        foreach (self::FILMS as $title => [$year, $directors, $poster]) {
            $path = Storage::disk('public')->putFileAs(Movie::POSTER_DIR, new File(database_path('seeders/posters/'.$poster)), $poster);

            $movie = Movie::create(['title' => $title, 'release_year' => $year, 'poster_path' => $path]);
            $movie->directors()->sync(collect($directors)->map(fn ($name) => $this->director($name)->director_id));
        }

        foreach (self::PROGRAMS as $name => $titles) {
            $program = Program::firstOrCreate(['name' => $name]);
            $program->movies()->syncWithoutDetaching(Movie::whereIn('title', $titles)->pluck('movie_id'));
        }
    }

    /** "Lamberto V. Avellana" → first "Lamberto V.", last "Avellana". */
    private function director(string $name): Director
    {
        $parts = explode(' ', $name);
        $last = array_pop($parts);

        return Director::firstOrCreate(['first_name' => implode(' ', $parts), 'last_name' => $last]);
    }
}

<?php

namespace Database\Seeders;

use App\Models\Genre;
use App\Models\Movie;
use App\Models\Program;
use Illuminate\Database\Seeder;
use Illuminate\Http\File;
use Illuminate\Support\Facades\Storage;

/**
 * Demo catalog: the Cinemalaya 2026 full-length finalists that FDCP Cinematheque Centre Davao
 * screens under "Pelikula ng Bayan: FDCP Screenings" (CCD screening schedule, 15–16 October 2026).
 * Directors, runtimes, genres and cast are from each film's page on cinemalaya.org; the ratings
 * are the ones on the CCD schedule. The loglines are short summaries written for this demo.
 * Posters: database/seeders/posters/, the films' official Cinemalaya 2026 posters (resized to 600 px wide).
 */
class CatalogSeeder extends Seeder
{
    public const PROGRAM = 'Pelikula ng Bayan: Cinemalaya 2026';

    /** title => [runtime (min), rating, directors, genres, main cast, logline, poster file in database/seeders/posters] */
    public const FILMS = [
        'Tirik (To Set Upright)' => [91, 'PG', 'May-i Guia Padilla', ['Thriller', 'Drama'],
            'Rocco Nacino, Floyd Tena, Chai Fonacier',
            'A priest and a taxi driver cross Manila during the drug war while escaping an attempt on their lives.', 'tirik.jpg'],
        'Kaka sa Yawan (Brothers by the River)' => [90, 'PG', 'Alpha Habon', ['Drama'],
            'Beaver Magtalas, Bryce Nicolo Mercader, Nikki Valdez',
            'A Manila boy and a Tadyawan-Mangyan boy share one summer in Mindoro; years later, a promise brings him back.', 'kaka-sa-yawan.jpg'],
        'A.ni.mál' => [120, 'R-13', 'Dustin Celestino', ['Comedy', 'Drama'],
            'Maxine Ignacio, Frances Makil-Ignacio, Jojit Lorenzo',
            "A mayor's daughter comes home with a video of a powerful ally abusing his dog, and her family's loyalties crack.", 'animal.jpg'],
        'Hand of God' => [106, 'PG', 'Mark Duane Angos', ['Drama'],
            'Ruru Madrid, Iza Calzado, Ronnie Lazaro',
            'In a village scarred by conflict, a former child soldier finds hope in football.', 'hand-of-god.jpg'],
        'Ganggang' => [89, 'PG', 'JL Burgos', ['Drama'],
            'Raine Dela Cruz, Kenneth Vincent Mendoza, Don Rishmond Cerbito',
            "Three boys hunt for the best fighting spider to beat the school bully's, until the search tests their friendship.", 'ganggang.jpg'],
        'Tayo Lang ang Nakakaalam (Only Known to Us)' => [110, 'R-13', 'David R. Corpuz', ['Drama'],
            'Martin del Rosario, Miguel Odron, Yayo Aguila',
            'When someone they love falls ill, a family must face the love it has kept quiet.', 'tayo-lang-ang-nakakaalam.jpg'],
    ];

    public function run(): void
    {
        // The fixed genre list.
        collect(Movie::GENRES)
            ->each(fn ($name) => Genre::firstOrCreate(['genre_name' => $name]));

        $program = Program::firstOrCreate(['name' => self::PROGRAM], [
            'description' => 'Cinemalaya 2026 full-length finalists, screened by FDCP Cinematheque Centre Davao.',
        ]);
        // For events that are not a catalogued film (e.g. a talk or a community shorts block).
        Program::firstOrCreate(['name' => 'Community Screenings']);

        foreach (self::FILMS as $title => [$runtime, $rating, $directors, $genres, $cast, $logline, $poster]) {
            $path = Storage::disk('public')->putFileAs(Movie::POSTER_DIR, new File(database_path('seeders/posters/'.$poster)), $poster);

            $movie = Movie::create([
                'title' => $title,
                'release_year' => 2026,
                'runtime_minutes' => $runtime,
                'rating' => $rating,
                'logline' => $logline,
                'poster_path' => $path,
            ]);
            $movie->syncDetails($genres, $directors, $cast);
            $movie->programs()->attach($program);
        }
    }
}

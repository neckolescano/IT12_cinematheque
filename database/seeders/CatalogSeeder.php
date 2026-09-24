<?php

namespace Database\Seeders;

use App\Models\Actor;
use App\Models\Director;
use App\Models\Genre;
use App\Models\Movie;
use Illuminate\Database\Seeder;

/** Genres plus a few factory-made movies with cast, directors and genres attached. */
class CatalogSeeder extends Seeder
{
    public function run(): void
    {
        $genres = collect(['Documentary', 'Drama', 'Comedy', 'Animation', 'Horror', 'Romance', 'Experimental', 'Short Film'])
            ->map(fn ($name) => Genre::firstOrCreate(['genre_name' => $name]));

        $actors = Actor::factory()->count(12)->create();
        $directors = Director::factory()->count(4)->create();

        Movie::factory()->count(5)->create()->each(function (Movie $movie) use ($genres, $actors, $directors) {
            $movie->genres()->sync($genres->random(2)->pluck('genre_id'));
            $movie->actors()->sync($actors->random(3)->pluck('actor_id'));
            $movie->directors()->sync($directors->random(1)->pluck('director_id'));
        });
    }
}

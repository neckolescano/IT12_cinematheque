<?php

namespace Tests\Feature;

use App\Models\Movie;
use App\Models\Screening;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Decision 5d: posters uploaded by staff on the movie form, shown on the customer site. */
class MoviePosterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->actingAs(User::factory()->create());
    }

    /** A real image (the seed posters), so the tests don't need PHP's GD extension. */
    private function poster(string $name, string $source = 'malvarosa.jpg'): UploadedFile
    {
        $copy = tempnam(sys_get_temp_dir(), 'poster');
        copy(database_path('seeders/posters/'.$source), $copy);

        return new UploadedFile($copy, $name, null, null, true);
    }

    public function test_staff_can_add_a_movie_with_a_poster_and_customers_see_it(): void
    {
        $this->post(route('staff.movies.store'), [
            'title' => 'Himala',
            'poster' => $this->poster('himala.jpg'),
        ])->assertRedirect(route('staff.movies.show', Movie::firstOrFail()));

        $movie = Movie::firstOrFail();
        $this->assertStringStartsWith('posters/', $movie->poster_path);
        Storage::disk('public')->assertExists($movie->poster_path);

        $screening = Screening::factory()->create(['movie_id' => $movie->movie_id, 'event_title' => 'Himala']);
        $this->get(route('home'))->assertSee('storage/'.$movie->poster_path, false);
        $this->get(route('screenings.show', $screening))->assertSee('poster--image', false);
    }

    public function test_replacing_or_removing_a_poster_deletes_the_old_file(): void
    {
        $movie = Movie::factory()->create(['poster_path' => $this->poster('a.jpg')->store('posters', 'public')]);
        $first = $movie->poster_path;

        $this->put(route('staff.movies.update', $movie), ['title' => $movie->title, 'poster' => $this->poster('b.png', 'the-secret-agent.png')]);
        $second = $movie->fresh()->poster_path;
        $this->assertNotSame($first, $second);
        Storage::disk('public')->assertMissing($first);

        $this->put(route('staff.movies.update', $movie), ['title' => $movie->title, 'remove_poster' => '1']);
        $this->assertNull($movie->fresh()->poster_path);
        Storage::disk('public')->assertMissing($second);
    }

    public function test_only_images_are_accepted(): void
    {
        $this->post(route('staff.movies.store'), [
            'title' => 'Not a poster',
            'poster' => UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf'),
        ])->assertSessionHasErrors('poster');

        $this->assertSame(0, Movie::count());
    }

    public function test_films_without_a_poster_keep_the_generated_tile(): void
    {
        Screening::factory()->create(['event_title' => 'Shorts Night']);

        $this->get(route('home'))->assertSee('poster__art', false)->assertDontSee('poster--image', false);
    }
}

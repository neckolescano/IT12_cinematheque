<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_home_page_lists_screenings(): void
    {
        $this->get('/')->assertRedirect('/cinemathequecentredavao');
        $this->get('/cinemathequecentredavao')->assertOk()->assertSee('Upcoming screenings');
    }
}

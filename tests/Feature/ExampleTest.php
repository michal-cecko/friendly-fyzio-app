<?php

namespace Tests\Feature;

use App\Models\Page;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The homepage (the published "home" system page) returns a successful response.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        Page::factory()->system('home')->create(['slug' => '/']);

        $response = $this->get('/');

        $response->assertStatus(200);
    }
}

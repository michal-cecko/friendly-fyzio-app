<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\ServiceCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SitemapTest extends TestCase
{
    use RefreshDatabase;

    public function test_sitemap_renders_public_urls_as_xml(): void
    {
        $category = ServiceCategory::factory()->create(['slug' => 'fyzioterapie', 'published_at' => now()]);
        $course = Course::factory()->create(['slug' => 'hormonalni-joga', 'published_at' => now()]);

        $response = $this->get('/sitemap.xml')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/xml');

        $response->assertSee($category->permalink, false);
        $response->assertSee($course->permalink(), false);
    }

    public function test_sitemap_excludes_unpublished_records(): void
    {
        $draft = Course::factory()->create(['slug' => 'skryty-kurz', 'published_at' => null]);

        $this->get('/sitemap.xml')
            ->assertOk()
            ->assertDontSee($draft->permalink(), false);
    }
}

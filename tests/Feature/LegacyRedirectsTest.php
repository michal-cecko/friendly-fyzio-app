<?php

namespace Tests\Feature;

use App\Enums\ServiceVisibility;
use App\Models\CourseCategory;
use App\Models\Service;
use App\Models\ServiceCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LegacyRedirectsTest extends TestCase
{
    use RefreshDatabase;

    public function test_old_category_slug_301s_to_the_new_scheme(): void
    {
        ServiceCategory::factory()->create(['slug' => 'fyzioterapie', 'published_at' => now()]);

        $this->get('/fyzioterapie')
            ->assertStatus(301)
            ->assertRedirect('/sluzby/fyzioterapie');
    }

    public function test_curated_single_segment_slug_301s(): void
    {
        $this->get('/nas-tym')
            ->assertStatus(301)
            ->assertRedirect('/o-nas');
    }

    public function test_old_top_level_service_slug_301s_under_its_category(): void
    {
        $category = ServiceCategory::factory()->create(['slug' => 'fyzioterapie', 'published_at' => now()]);
        Service::factory()->for($category, 'category')->create([
            'slug' => 'terapie-panevniho-dna',
            'visibility' => ServiceVisibility::Public,
            'published_at' => now(),
        ]);

        $this->get('/terapie-panevniho-dna')
            ->assertStatus(301)
            ->assertRedirect('/sluzby/fyzioterapie/terapie-panevniho-dna');
    }

    public function test_old_course_category_page_301s_to_the_filtered_archive(): void
    {
        CourseCategory::factory()->create(['slug' => 'joga']);

        $this->get('/fyzio-kurzy/joga')
            ->assertStatus(301)
            ->assertRedirect('/kurzy?kategorie=joga');
    }

    public function test_unknown_course_category_falls_back_to_the_archive(): void
    {
        $this->get('/fyzio-kurzy/neexistuje')
            ->assertStatus(301)
            ->assertRedirect('/kurzy');
    }

    public function test_curated_multi_segment_slug_301s(): void
    {
        $this->get('/relaxace-ritualy/masaze')
            ->assertStatus(301)
            ->assertRedirect('/sluzby/relaxace/lymphaticke-masaze');
    }

    public function test_unknown_single_segment_slug_404s(): void
    {
        $this->get('/tato-stranka-neexistuje')->assertNotFound();
    }

    public function test_unknown_multi_segment_slug_404s(): void
    {
        $this->get('/nic/tady/neni')->assertNotFound();
    }
}

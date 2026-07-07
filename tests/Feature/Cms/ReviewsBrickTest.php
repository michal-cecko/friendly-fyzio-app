<?php

namespace Tests\Feature\Cms;

use App\Mason\Bricks\ReviewsBrick;
use App\Models\Course;
use App\Models\Review;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReviewsBrickTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_mode_renders_only_visible_reviews(): void
    {
        Review::factory()->create(['content' => 'VIDITELNA-RECENZE', 'visible' => true]);
        Review::factory()->create(['content' => 'SKRYTA-RECENZE', 'visible' => false]);

        $html = ReviewsBrick::toHtml(['source' => 'all', 'limit' => 10]);

        $this->assertStringContainsString('VIDITELNA-RECENZE', $html);
        $this->assertStringNotContainsString('SKRYTA-RECENZE', $html);
    }

    public function test_all_mode_respects_limit_and_shows_newest(): void
    {
        foreach (range(1, 4) as $i) {
            Review::factory()->create([
                'content' => "RECENZE-{$i}",
                'visible' => true,
                'created_at' => now()->subDays(5 - $i), // RECENZE-4 is newest
            ]);
        }

        $html = ReviewsBrick::toHtml(['source' => 'all', 'limit' => 2]);

        $this->assertStringContainsString('RECENZE-4', $html);
        $this->assertStringContainsString('RECENZE-3', $html);
        $this->assertStringNotContainsString('RECENZE-2', $html);
        $this->assertStringNotContainsString('RECENZE-1', $html);
    }

    public function test_specific_mode_preserves_picked_order(): void
    {
        $a = Review::factory()->create(['content' => 'AAA-RECENZE', 'visible' => true]);
        $b = Review::factory()->create(['content' => 'BBB-RECENZE', 'visible' => true]);
        Review::factory()->create(['content' => 'CCC-RECENZE', 'visible' => true]);

        $html = ReviewsBrick::toHtml([
            'source' => 'specific',
            'review_ids' => [$b->getKey(), $a->getKey()],
        ]);

        $this->assertStringContainsString('AAA-RECENZE', $html);
        $this->assertStringContainsString('BBB-RECENZE', $html);
        $this->assertStringNotContainsString('CCC-RECENZE', $html);
        // Picked order preserved: BBB chosen first, so it appears before AAA.
        $this->assertLessThan(strpos($html, 'AAA-RECENZE'), strpos($html, 'BBB-RECENZE'));
    }

    public function test_specific_mode_never_shows_hidden_reviews(): void
    {
        $hidden = Review::factory()->create(['content' => 'SKRYTA-VYBRANA', 'visible' => false]);

        $html = ReviewsBrick::toHtml([
            'source' => 'specific',
            'review_ids' => [$hidden->getKey()],
        ]);

        $this->assertStringNotContainsString('SKRYTA-VYBRANA', $html);
    }

    public function test_min_rating_filter_excludes_lower_ratings(): void
    {
        Review::factory()->create(['content' => 'PET-HVEZD', 'rating' => 5, 'visible' => true]);
        Review::factory()->create(['content' => 'TRI-HVEZDY', 'rating' => 3, 'visible' => true]);

        $html = ReviewsBrick::toHtml(['source' => 'all', 'min_rating' => 4, 'limit' => 10]);

        $this->assertStringContainsString('PET-HVEZD', $html);
        $this->assertStringNotContainsString('TRI-HVEZDY', $html);
    }

    public function test_reviewable_type_filter_limits_to_type(): void
    {
        $course = Course::factory()->create();
        Review::factory()->reviewing($course)->create(['content' => 'KURZOVA-RECENZE', 'visible' => true]);
        Review::factory()->create(['content' => 'OBECNA-RECENZE', 'visible' => true]);

        $html = ReviewsBrick::toHtml(['source' => 'all', 'reviewable_type' => 'course', 'limit' => 10]);

        $this->assertStringContainsString('KURZOVA-RECENZE', $html);
        $this->assertStringNotContainsString('OBECNA-RECENZE', $html);
    }
}

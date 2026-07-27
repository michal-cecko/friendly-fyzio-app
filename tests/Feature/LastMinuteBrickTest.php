<?php

namespace Tests\Feature;

use App\Enums\ServiceVisibility;
use App\Mason\Bricks\LastMinuteBrick;
use App\Models\Room;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\StaffProfile;
use App\Models\TherapistWorkBlock;
use App\Models\User;
use App\Support\Avatar;
use App\Support\Reservations\LastMinuteAvailability;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class LastMinuteBrickTest extends TestCase
{
    use RefreshDatabase;

    private Room $room;

    private Service $service;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::create(2026, 6, 1, 7, 0));

        $this->room = Room::factory()->create();
        $category = ServiceCategory::factory()->create(['published_at' => now()]);
        $this->service = Service::factory()->create([
            'category_id' => $category->id,
            'duration_minutes' => 60,
            'visibility' => ServiceVisibility::Public,
            'published_at' => now(),
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function scheduleTomorrow(StaffProfile $therapist): void
    {
        TherapistWorkBlock::factory()->create([
            'therapist_id' => $therapist->id,
            'room_id' => $this->room->id,
            'work_date' => Carbon::tomorrow()->toDateString(),
            'start_time' => '08:00',
            'end_time' => '16:00',
        ]);
    }

    private function availability(): LastMinuteAvailability
    {
        return app(LastMinuteAvailability::class);
    }

    public function test_lists_therapists_with_real_near_term_openings(): void
    {
        $therapist = StaffProfile::factory()->published()->create();
        $this->service->therapists()->attach($therapist);
        $this->scheduleTomorrow($therapist);

        $openings = $this->availability()->compute();

        $this->assertCount(1, $openings);
        $opening = $openings[0];
        $this->assertSame($therapist->id, $opening['id']);
        $this->assertContains($this->service->name, $opening['services']);
        $this->assertSame(Carbon::tomorrow()->toDateString(), $opening['days'][0]['date']);
        $this->assertContains('08:00', $opening['days'][0]['times']);
        // Published profile is linkable.
        $this->assertNotNull($opening['permalink']);
    }

    public function test_unpublished_therapist_appears_but_is_not_linked(): void
    {
        // Bookable regardless of publish state (last-minute mirrors the wizard), but
        // an unpublished profile has no public page to link to.
        $therapist = StaffProfile::factory()->unpublished()->create();
        $this->service->therapists()->attach($therapist);
        $this->scheduleTomorrow($therapist);

        $openings = $this->availability()->compute();

        $this->assertCount(1, $openings);
        $this->assertNull($openings[0]['permalink']);
    }

    public function test_therapist_without_near_term_availability_is_excluded(): void
    {
        $therapist = StaffProfile::factory()->published()->create();
        $this->service->therapists()->attach($therapist);
        // No work block at all -> nothing to offer.

        $this->assertSame([], $this->availability()->compute());
    }

    public function test_service_without_a_therapist_never_surfaces(): void
    {
        // The service has no therapists, so nobody can offer it and the brick is empty.
        $this->assertSame([], $this->availability()->compute());
    }

    public function test_brick_renders_nothing_on_the_public_site_when_there_are_no_openings(): void
    {
        // No therapists, no work blocks -> the section is hidden entirely
        // instead of showing the "nothing available" placeholder.
        $this->assertSame('', LastMinuteBrick::toHtml(['title' => 'Last-minute termíny']));
    }

    public function test_renders_initials_fallback_and_links_slots_to_the_wizard(): void
    {
        $user = User::factory()->therapist()->create(['name' => 'Xandra Ypsilonová']);
        $therapist = StaffProfile::factory()->published()->create(['user_id' => $user->id, 'photo' => null]);
        $this->service->therapists()->attach($therapist);
        $this->scheduleTomorrow($therapist);

        $html = LastMinuteBrick::toHtml(['title' => 'Last-minute termíny']);

        $this->assertStringContainsString(Avatar::initials('Xandra Ypsilonová'), $html); // "XY"
        $this->assertStringContainsString('terapeut='.$therapist->slug, $html);
        $this->assertStringContainsString($this->service->name, $html);
    }
}

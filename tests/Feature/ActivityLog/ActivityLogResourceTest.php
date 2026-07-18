<?php

namespace Tests\Feature\ActivityLog;

use App\Filament\Clusters\System\Resources\ActivityLog\ActivityLogResource;
use App\Filament\Clusters\System\Resources\ActivityLog\Pages\ListActivityLog;
use App\Models\Reservation;
use App\Models\Service;
use App\Models\TherapistProfile;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class ActivityLogResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');
    }

    public function test_updating_a_model_logs_the_change_with_the_causer(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);

        $service = Service::factory()->create();
        $service->update(['name' => 'Nový název']);

        $activity = Activity::query()
            ->where('subject_type', 'service')
            ->where('subject_id', $service->getKey())
            ->where('event', 'updated')
            ->latest('id')
            ->first();

        $this->assertNotNull($activity);
        $this->assertSame($admin->getKey(), $activity->causer_id);
        $this->assertSame('Nový název', $activity->attribute_changes['attributes']['name'] ?? null);
    }

    public function test_deleting_a_model_logs_the_full_snapshot_including_uuid(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $service = Service::factory()->create();
        $id = $service->getKey();
        $service->delete();

        $activity = Activity::query()
            ->where('subject_id', $id)
            ->where('event', 'deleted')
            ->latest('id')
            ->first();

        $this->assertNotNull($activity);
        // On delete the complete record state is preserved under `old`.
        $snapshot = $activity->attribute_changes['old'] ?? [];
        $this->assertSame($id, $snapshot['id'] ?? null);
        $this->assertArrayHasKey('name', $snapshot);
        $this->assertArrayHasKey('category_id', $snapshot);
    }

    public function test_resource_is_staff_only(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $this->assertTrue(ActivityLogResource::canAccess());

        $this->actingAs(User::factory()->therapist()->create());
        $this->assertTrue(ActivityLogResource::canAccess());

        $this->actingAs(User::factory()->customer()->create());
        $this->assertFalse(ActivityLogResource::canAccess());
    }

    public function test_admin_sees_all_activities_therapist_only_their_scoped(): void
    {
        $therapistUser = User::factory()->therapist()->create();
        $profile = TherapistProfile::factory()->create(['user_id' => $therapistUser->getKey()]);

        $mine = Reservation::factory()->create(['therapist_id' => $profile->getKey()]);
        $other = Reservation::factory()->create();

        $mine->update(['notes' => 'moje']);
        $other->update(['notes' => 'cizí']);

        $mineActivity = Activity::where('subject_id', $mine->getKey())->latest('id')->first();
        $otherActivity = Activity::where('subject_id', $other->getKey())->latest('id')->first();

        $this->actingAs(User::factory()->admin()->create());
        Livewire::test(ListActivityLog::class)
            ->assertCanSeeTableRecords([$mineActivity, $otherActivity]);

        $this->actingAs($therapistUser->fresh());
        Livewire::test(ListActivityLog::class)
            ->assertCanSeeTableRecords([$mineActivity])
            ->assertCanNotSeeTableRecords([$otherActivity]);
    }
}

<?php

namespace Tests\Feature\ActivityLog;

use App\Models\TherapistWorkBlock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class AuditCoverageTest extends TestCase
{
    use RefreshDatabase;

    public function test_work_block_created_via_the_model_is_logged(): void
    {
        $block = TherapistWorkBlock::factory()->create();

        $this->assertSame(
            1,
            Activity::query()
                ->where('subject_id', $block->getKey())
                ->where('event', 'created')
                ->count(),
        );
    }

    public function test_bulk_inserted_work_blocks_do_not_flood_the_log(): void
    {
        $block = TherapistWorkBlock::factory()->create();

        $createdBefore = Activity::query()->where('event', 'created')->count();

        // WorkBlockGenerator materializes rows with a query-builder insert, which
        // bypasses model events — so bulk generation must not produce log entries.
        TherapistWorkBlock::query()->insert([
            'id' => (string) Str::uuid(),
            'therapist_id' => $block->therapist_id,
            'room_id' => $block->room_id,
            'work_date' => '2030-01-01',
            'start_time' => '09:00:00',
            'end_time' => '10:00:00',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertSame($createdBefore, Activity::query()->where('event', 'created')->count());
    }
}

<?php

namespace Tests\Feature;

use Illuminate\Console\Scheduling\Schedule;
use Tests\TestCase;

/**
 * routes/console.php is guarded by a master switch so no background job can act
 * on — or e-mail about — the real client records already in the database before
 * the clinic goes live. The test suite runs with the switch on (phpunit.xml).
 */
class ScheduledTasksGuardTest extends TestCase
{
    public function test_the_schedule_is_registered_when_enabled(): void
    {
        $this->assertNotEmpty(
            app(Schedule::class)->events(),
            'With SCHEDULED_TASKS_ENABLED=true the jobs should be registered.',
        );
    }

    public function test_nothing_is_scheduled_when_the_switch_is_off(): void
    {
        config(['app.scheduled_tasks_enabled' => false]);

        // Re-evaluate routes/console.php against a fresh Schedule instance.
        $this->app->forgetInstance(Schedule::class);
        $schedule = $this->app->make(Schedule::class);
        require base_path('routes/console.php');

        $this->assertSame(
            [],
            $schedule->events(),
            'The pre-launch switch must keep every scheduled job from registering.',
        );
    }
}

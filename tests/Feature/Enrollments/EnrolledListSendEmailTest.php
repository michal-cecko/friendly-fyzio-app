<?php

namespace Tests\Feature\Enrollments;

use App\Enums\CourseEnrollmentStatus;
use App\Filament\Clusters\Kurzy\Resources\CourseSeries\Pages\ViewCourseSeries;
use App\Filament\Support\RelationManagers\CourseSeriesEnrollmentsRelationManager;
use App\Jobs\SendBulkParticipantEmailJob;
use App\Models\CourseEnrollment;
use App\Models\CourseSeries;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Livewire\Livewire;
use Tests\TestCase;

class EnrolledListSendEmailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');
        $this->actingAs(User::factory()->admin()->create());
    }

    private function seriesWithEnrollments(int $count): CourseSeries
    {
        $series = CourseSeries::factory()->create(['capacity' => 10]);

        CourseEnrollment::factory()
            ->count($count)
            ->for($series, 'series')
            ->create(['status' => CourseEnrollmentStatus::Active]);

        return $series;
    }

    public function test_single_send_email_action_is_available_on_a_participant_row(): void
    {
        $series = $this->seriesWithEnrollments(1);
        $enrollment = $series->enrollments()->sole();

        Livewire::test(CourseSeriesEnrollmentsRelationManager::class, [
            'ownerRecord' => $series,
            'pageClass' => ViewCourseSeries::class,
        ])
            ->assertActionVisible(TestAction::make('sendEmail')->table($enrollment));
    }

    public function test_table_selection_bulk_email_dispatches_the_participant_job(): void
    {
        Bus::fake();

        $series = $this->seriesWithEnrollments(2);
        $ids = $series->enrollments()->pluck('id')->all();

        Livewire::test(CourseSeriesEnrollmentsRelationManager::class, [
            'ownerRecord' => $series,
            'pageClass' => ViewCourseSeries::class,
        ])
            ->set('selectedTableRecords', $ids)
            ->callAction(TestAction::make('sendParticipantEmail')->table()->bulk(), [
                'mode' => 'custom',
                'subject' => 'Informace k běhu',
                'body' => '<p>Text.</p>',
            ])
            ->assertHasNoActionErrors();

        Bus::assertDispatched(
            SendBulkParticipantEmailJob::class,
            function (SendBulkParticipantEmailJob $job) use ($ids): bool {
                $sent = collect($job->signupIds)->map('strval')->sort()->values()->all();
                $expected = collect($ids)->map('strval')->sort()->values()->all();

                return $job->signupClass === CourseEnrollment::class && $sent === $expected;
            }
        );
    }
}

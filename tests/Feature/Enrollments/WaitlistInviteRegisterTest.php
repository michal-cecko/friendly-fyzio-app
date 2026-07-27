<?php

namespace Tests\Feature\Enrollments;

use App\Enums\CourseEnrollmentStatus;
use App\Enums\CourseSeriesStatus;
use App\Enums\CourseSeriesVisibility;
use App\Enums\EmailTemplateKey;
use App\Enums\PaymentStatus;
use App\Filament\Clusters\Kurzy\Resources\Courses\Pages\ViewCourse;
use App\Filament\Clusters\Kurzy\Resources\CourseSeries\Pages\ViewCourseSeries;
use App\Filament\Support\RelationManagers\WaitlistEntriesRelationManager;
use App\Jobs\SendBulkParticipantEmailJob;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\CourseSeries;
use App\Models\User;
use App\Models\WaitlistEntry;
use App\Notifications\EnrollmentTemplateNotification;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class WaitlistInviteRegisterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');
        $this->actingAs(User::factory()->admin()->create());
        Notification::fake();
    }

    private function openSeries(int $capacity, ?Course $course = null): CourseSeries
    {
        return CourseSeries::factory()
            ->for($course ?? Course::factory())
            ->create([
                'capacity' => $capacity,
                'price' => 2000,
                'status' => CourseSeriesStatus::Open,
                'visibility' => CourseSeriesVisibility::Public,
                'start_date' => today()->addWeeks(2)->toDateString(),
                'end_date' => today()->addWeeks(14)->toDateString(),
            ]);
    }

    /**
     * @return Collection<int, WaitlistEntry>
     */
    private function pendingEntries(CourseSeries $series, int $count)
    {
        return WaitlistEntry::factory()
            ->count($count)
            ->forWaitlistable($series)
            ->create(['client_id' => null]);
    }

    private function manager(CourseSeries $series)
    {
        return Livewire::test(WaitlistEntriesRelationManager::class, [
            'ownerRecord' => $series,
            'pageClass' => ViewCourseSeries::class,
        ]);
    }

    public function test_an_empty_waitlist_hides_the_header_actions(): void
    {
        $series = $this->openSeries(capacity: 2);

        $this->manager($series)
            ->assertActionHidden(TestAction::make('promote')->table())
            ->assertActionHidden(TestAction::make('invite')->table());
    }

    public function test_the_header_actions_appear_once_somebody_is_waiting(): void
    {
        $series = $this->openSeries(capacity: 2);
        $this->pendingEntries($series, 1);

        $this->manager($series)
            ->assertActionVisible(TestAction::make('promote')->table())
            ->assertActionEnabled(TestAction::make('promote')->table())
            ->assertActionVisible(TestAction::make('invite')->table());
    }

    public function test_a_full_run_keeps_the_header_actions_visible_but_disabled(): void
    {
        $series = $this->openSeries(capacity: 1);
        $this->pendingEntries($series, 1);

        CourseEnrollment::factory()->for($series, 'series')->create([
            'status' => CourseEnrollmentStatus::Active,
        ]);

        $this->manager($series)
            ->assertActionVisible(TestAction::make('promote')->table())
            ->assertActionDisabled(TestAction::make('promote')->table())
            ->assertActionDisabled(TestAction::make('invite')->table());
    }

    public function test_a_waitlist_of_already_notified_people_hides_the_header_actions(): void
    {
        $series = $this->openSeries(capacity: 2);
        $this->pendingEntries($series, 2)->each->update(['notified_at' => now()]);

        $this->manager($series)
            ->assertActionHidden(TestAction::make('promote')->table())
            ->assertActionHidden(TestAction::make('invite')->table());
    }

    public function test_hold_invite_creates_an_unpaid_signup_and_consumes_the_entry(): void
    {
        $series = $this->openSeries(capacity: 2);
        $entry = $this->pendingEntries($series, 1)->first();

        $this->manager($series)
            ->callAction(TestAction::make('inviteToCourse')->table($entry), ['mode' => 'hold'])
            ->assertHasNoActionErrors();

        $enrollment = $series->enrollments()->sole();
        $this->assertSame(CourseEnrollmentStatus::Active, $enrollment->status);
        $this->assertSame(PaymentStatus::Unpaid, $enrollment->payment_status);
        $this->assertSame(1, $enrollment->payments()->count());
        $this->assertNotNull($entry->fresh()->notified_at);

        Notification::assertSentTimes(EnrollmentTemplateNotification::class, 1);
    }

    public function test_hold_invite_stops_at_capacity(): void
    {
        $series = $this->openSeries(capacity: 2);
        $entries = $this->pendingEntries($series, 4);

        $this->manager($series)
            ->set('selectedTableRecords', $entries->pluck('id')->all())
            ->callAction(TestAction::make('inviteToCourse')->table()->bulk(), ['mode' => 'hold'])
            ->assertHasNoActionErrors();

        // Only the two free spots are offered; the other two stay waiting.
        $this->assertSame(2, $series->enrollments()->count());
        $this->assertSame(2, $series->waitlistEntries()->pending()->count());
    }

    public function test_race_invite_over_invites_past_capacity(): void
    {
        $series = $this->openSeries(capacity: 1);
        $entries = $this->pendingEntries($series, 3);

        $this->manager($series)
            ->set('selectedTableRecords', $entries->pluck('id')->all())
            ->callAction(TestAction::make('inviteToCourse')->table()->bulk(), ['mode' => 'race'])
            ->assertHasNoActionErrors();

        // Everyone is offered a spot even though there is only one.
        $this->assertSame(3, $series->enrollments()->where('status', CourseEnrollmentStatus::Active)->count());
        $this->assertSame(0, $series->fresh()->spotsLeft());
        $this->assertSame(0, $series->waitlistEntries()->pending()->count());
        Notification::assertSentTimes(EnrollmentTemplateNotification::class, 3);
    }

    public function test_register_enrolls_through_the_signup_flow_and_consumes_the_entry(): void
    {
        $series = $this->openSeries(capacity: 5);
        $entry = $this->pendingEntries($series, 1)->first();

        $this->manager($series)
            ->callAction(TestAction::make('registerToSeries')->table($entry))
            ->assertHasNoActionErrors();

        $enrollment = $series->enrollments()->sole();
        $this->assertSame(CourseEnrollmentStatus::Active, $enrollment->status);
        $this->assertNotNull($entry->fresh()->notified_at);
        $this->assertSame($enrollment->client_id, $entry->fresh()->client_id);

        // Register sends the confirmation, not the waitlist offer.
        Notification::assertSentTo(
            $enrollment->client,
            EnrollmentTemplateNotification::class,
            fn (EnrollmentTemplateNotification $n): bool => $n->key === EmailTemplateKey::CourseEnrollmentReceived
        );
    }

    public function test_register_is_refused_when_the_series_is_full(): void
    {
        $series = $this->openSeries(capacity: 1);
        CourseEnrollment::factory()->for($series, 'series')->create([
            'status' => CourseEnrollmentStatus::Active,
            'payment_status' => PaymentStatus::Paid,
        ]);
        $entry = $this->pendingEntries($series, 1)->first();

        $this->manager($series)
            ->callAction(TestAction::make('registerToSeries')->table($entry))
            ->assertHasNoActionErrors();

        // No new enrollment, and the entry stays on the list.
        $this->assertSame(1, $series->enrollments()->count());
        $this->assertNull($entry->fresh()->notified_at);
    }

    public function test_register_still_works_while_a_waitlist_invite_round_is_running(): void
    {
        $series = $this->openSeries(capacity: 1);

        // An invite round fences the free spot off from the public, which makes
        // offerState() report Full — staff must still be able to place someone.
        $series->update(['waitlist_invited_until' => now()->addHours(6)]);

        $entry = $this->pendingEntries($series, 1)->first();

        $this->manager($series)
            ->callAction(TestAction::make('registerToSeries')->table($entry))
            ->assertHasNoActionErrors();

        $this->assertSame(1, $series->enrollments()->count());
        $this->assertNotNull($entry->fresh()->notified_at);
    }

    public function test_bulk_email_dispatches_only_for_pending_entries_with_an_email(): void
    {
        Bus::fake();

        $series = $this->openSeries(capacity: 5);
        $pending = $this->pendingEntries($series, 2);
        $notified = WaitlistEntry::factory()->forWaitlistable($series)->create(['notified_at' => now()]);
        $noEmail = WaitlistEntry::factory()->forWaitlistable($series)->create(['email' => null, 'client_id' => null]);

        $ids = $pending->pluck('id')->push($notified->id)->push($noEmail->id)->all();

        $this->manager($series)
            ->set('selectedTableRecords', $ids)
            ->callAction(TestAction::make('sendWaitlistEmail')->table()->bulk(), [
                'mode' => 'custom',
                'subject' => 'Dobrá zpráva',
                'body' => '<p>Uvolnilo se místo.</p>',
            ])
            ->assertHasNoActionErrors();

        Bus::assertDispatched(
            SendBulkParticipantEmailJob::class,
            function (SendBulkParticipantEmailJob $job) use ($pending): bool {
                $ids = collect($job->signupIds)->map('strval')->sort()->values()->all();
                $expected = $pending->pluck('id')->map('strval')->sort()->values()->all();

                return $job->signupClass === WaitlistEntry::class && $ids === $expected;
            }
        );
    }

    /**
     * Somebody who rang up rather than using the site still has to get onto the
     * list — through the same engine, so an existing account is linked by
     * e-mail and the confirmation is the one the public form sends.
     */
    public function test_staff_can_add_an_entry_by_hand(): void
    {
        $series = $this->openSeries(capacity: 2);

        $this->manager($series)
            ->callAction(TestAction::make('addEntry')->table(), [
                'name' => 'Jan Novák',
                'email' => 'jan@example.com',
                'phone' => '+420777123456',
                'notify' => true,
            ])
            ->assertHasNoActionErrors();

        $entry = $series->waitlistEntries()->sole();

        $this->assertSame('Jan Novák', $entry->name);
        $this->assertSame('jan@example.com', $entry->email);
        $this->assertTrue($entry->isPending());
        Notification::assertSentTimes(EnrollmentTemplateNotification::class, 1);
    }

    public function test_adding_by_hand_can_skip_the_confirmation_email(): void
    {
        $series = $this->openSeries(capacity: 2);

        $this->manager($series)
            ->callAction(TestAction::make('addEntry')->table(), [
                'name' => 'Jan Novák',
                'email' => 'jan@example.com',
                'notify' => false,
            ])
            ->assertHasNoActionErrors();

        $this->assertSame(1, $series->waitlistEntries()->count());
        Notification::assertNothingSent();
    }

    public function test_adding_an_existing_e_mail_does_not_queue_the_same_person_twice(): void
    {
        $series = $this->openSeries(capacity: 2);
        WaitlistEntry::factory()->forWaitlistable($series)->create([
            'client_id' => null,
            'email' => 'jan@example.com',
        ]);

        $this->manager($series)
            ->callAction(TestAction::make('addEntry')->table(), [
                'name' => 'Jan Novák',
                'email' => 'jan@example.com',
                'notify' => true,
            ])
            ->assertHasNoActionErrors();

        $this->assertSame(1, $series->waitlistEntries()->count());
        Notification::assertNothingSent();
    }

    /**
     * A course's list is an interest sign-up that stays silent until a série
     * opens, so there is no confirmation to send and no toggle to offer.
     */
    public function test_adding_to_a_course_interest_list_sends_nothing(): void
    {
        $course = Course::factory()->create();

        Livewire::test(WaitlistEntriesRelationManager::class, [
            'ownerRecord' => $course,
            'pageClass' => ViewCourse::class,
        ])
            ->assertActionVisible(TestAction::make('addEntry')->table())
            ->callAction(TestAction::make('addEntry')->table(), [
                'name' => 'Jan Novák',
                'email' => 'jan@example.com',
            ])
            ->assertHasNoActionErrors();

        $this->assertSame(1, $course->waitlistEntries()->count());
        Notification::assertNothingSent();
    }

    /**
     * The waitlist is worked from a View page, where Filament marks relation
     * managers read-only by default — which would hide both delete actions.
     */
    public function test_staff_can_remove_a_single_entry(): void
    {
        $series = $this->openSeries(capacity: 2);
        $entry = $this->pendingEntries($series, 1)->first();

        $this->manager($series)
            ->callAction(TestAction::make('delete')->table($entry))
            ->assertHasNoActionErrors();

        $this->assertSame(0, $series->waitlistEntries()->count());
    }

    public function test_staff_can_remove_selected_entries_in_bulk(): void
    {
        $series = $this->openSeries(capacity: 2);
        $entries = $this->pendingEntries($series, 3);

        $this->manager($series)
            ->set('selectedTableRecords', $entries->take(2)->pluck('id')->all())
            ->callAction(TestAction::make('delete')->table()->bulk())
            ->assertHasNoActionErrors();

        $this->assertSame(1, $series->waitlistEntries()->count());
    }

    public function test_course_owner_invite_targets_the_chosen_series(): void
    {
        $course = Course::factory()->create();
        $series = $this->openSeries(capacity: 3, course: $course);
        $entry = WaitlistEntry::factory()->forWaitlistable($course)->create(['client_id' => null]);

        Livewire::test(WaitlistEntriesRelationManager::class, [
            'ownerRecord' => $course,
            'pageClass' => ViewCourse::class,
        ])
            ->callAction(TestAction::make('inviteToCourse')->table($entry), [
                'series_id' => $series->getKey(),
                'mode' => 'hold',
            ])
            ->assertHasNoActionErrors();

        $this->assertSame(1, $series->enrollments()->count());
        $this->assertNotNull($entry->fresh()->notified_at);
    }
}

<?php

namespace Tests\Feature;

use App\Enums\CourseEnrollmentStatus;
use App\Enums\EmailTemplateKey;
use App\Filament\Clusters\Kurzy\Resources\CourseSeries\Pages\ViewCourseSeries;
use App\Filament\Support\Actions\SendBulkParticipantEmailAction;
use App\Jobs\SendBulkParticipantEmailJob;
use App\Models\CourseEnrollment;
use App\Models\CourseSeries;
use App\Models\User;
use App\Notifications\CustomEmailNotification;
use App\Notifications\EnrollmentTemplateNotification;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Writing to a whole série: the action collects recipients and hands them to a
 * queued job, so the assertions split between what gets dispatched and what the
 * job then sends.
 */
class BulkParticipantEmailTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');

        $this->admin = User::factory()->admin()->create();
        $this->actingAs($this->admin);
    }

    private function seriesWithParticipants(int $count = 3): CourseSeries
    {
        $series = CourseSeries::factory()->create();

        CourseEnrollment::factory()
            ->count($count)
            ->for($series, 'series')
            ->create(['status' => CourseEnrollmentStatus::Active]);

        return $series;
    }

    public function test_all_mode_targets_every_active_participant_and_nobody_else(): void
    {
        Bus::fake();

        $series = $this->seriesWithParticipants(3);
        $control = $this->seriesWithParticipants(2);

        Livewire::test(ViewCourseSeries::class, ['record' => $series->getKey()])
            ->callAction('emailParticipants', [
                'audience' => 'all',
                'mode' => 'custom',
                'subject' => 'Změna místa',
                'body' => '<p>Sejdeme se jinde.</p>',
            ])
            ->assertHasNoActionErrors();

        $expected = $series->activeTakers()->pluck('id')->map('strval')->sort()->values()->all();

        Bus::assertDispatched(
            SendBulkParticipantEmailJob::class,
            function (SendBulkParticipantEmailJob $job) use ($expected, $control): bool {
                $ids = collect($job->signupIds)->map('strval')->sort()->values()->all();
                $controlIds = $control->activeTakers()->pluck('id')->map('strval')->all();

                return $ids === $expected
                    && collect($job->signupIds)->map('strval')->intersect($controlIds)->isEmpty();
            },
        );
    }

    public function test_selected_mode_targets_only_the_chosen_participants(): void
    {
        Bus::fake();

        $series = $this->seriesWithParticipants(3);
        $chosen = $series->activeTakers()->first();

        Livewire::test(ViewCourseSeries::class, ['record' => $series->getKey()])
            ->callAction('emailParticipants', [
                'audience' => 'selected',
                'recipient_ids' => [(string) $chosen->getKey()],
                'mode' => 'custom',
                'subject' => 'Jen vám',
                'body' => '<p>Osobní zpráva.</p>',
            ])
            ->assertHasNoActionErrors();

        Bus::assertDispatched(
            SendBulkParticipantEmailJob::class,
            fn (SendBulkParticipantEmailJob $job): bool => $job->signupIds === [(string) $chosen->getKey()],
        );
    }

    /**
     * `client_id` is never null, so the unreachable case is a client that has
     * since been deleted — the relation resolves to null and the sign-up has no
     * address left to write to.
     */
    public function test_participants_without_an_email_are_skipped(): void
    {
        Bus::fake();

        $series = $this->seriesWithParticipants(2);
        $orphan = CourseEnrollment::factory()
            ->for($series, 'series')
            ->create(['status' => CourseEnrollmentStatus::Active]);

        $orphan->client->delete();
        $orphan->unsetRelation('client');

        Livewire::test(ViewCourseSeries::class, ['record' => $series->getKey()])
            ->callAction('emailParticipants', [
                'audience' => 'all',
                'mode' => 'custom',
                'subject' => 'Všem',
                'body' => '<p>Text.</p>',
            ])
            ->assertHasNoActionErrors();

        Bus::assertDispatched(
            SendBulkParticipantEmailJob::class,
            fn (SendBulkParticipantEmailJob $job): bool => ! in_array((string) $orphan->getKey(), array_map('strval', $job->signupIds), true)
                && count($job->signupIds) === 2,
        );
    }

    public function test_job_sends_a_custom_email_to_each_recipient(): void
    {
        Notification::fake();

        $series = $this->seriesWithParticipants(2);
        $ids = $series->activeTakers()->pluck('id')->map('strval')->all();

        (new SendBulkParticipantEmailJob(
            signupClass: CourseEnrollment::class,
            signupIds: $ids,
            templateKey: null,
            subject: 'Změna místa',
            bodyHtml: '<p>Sejdeme se jinde.</p>',
            senderId: $this->admin->getKey(),
        ))->handle();

        Notification::assertSentTimes(CustomEmailNotification::class, 2);
    }

    public function test_job_sends_the_chosen_template_to_each_recipient(): void
    {
        Notification::fake();

        $series = $this->seriesWithParticipants(2);
        $ids = $series->activeTakers()->pluck('id')->map('strval')->all();

        (new SendBulkParticipantEmailJob(
            signupClass: CourseEnrollment::class,
            signupIds: $ids,
            templateKey: EmailTemplateKey::EnrollmentCancelledByClinic->value,
            subject: null,
            bodyHtml: null,
            senderId: $this->admin->getKey(),
        ))->handle();

        Notification::assertSentTimes(EnrollmentTemplateNotification::class, 2);
    }

    /**
     * Most enrollment templates are per-person receipts; offering them here
     * would produce nonsense addressed to a whole group.
     */
    public function test_only_broadcast_safe_templates_are_offered(): void
    {
        $series = $this->seriesWithParticipants(1);

        $options = SendBulkParticipantEmailAction::broadcastTemplateOptions();

        $this->assertArrayHasKey(EmailTemplateKey::EnrollmentCancelledByClinic->value, $options);
        $this->assertArrayHasKey(EmailTemplateKey::LessonScheduleChanged->value, $options);
        $this->assertArrayNotHasKey(EmailTemplateKey::CourseEnrollmentReceived->value, $options);
        $this->assertArrayNotHasKey(EmailTemplateKey::WaitlistJoined->value, $options);
        $this->assertArrayNotHasKey(EmailTemplateKey::PaymentReceived->value, $options);

        // And the picker really enforces it: a per-person key is rejected.
        Bus::fake();

        Livewire::test(ViewCourseSeries::class, ['record' => $series->getKey()])
            ->callAction('emailParticipants', [
                'audience' => 'all',
                'mode' => 'template',
                'template_key' => EmailTemplateKey::WaitlistJoined->value,
            ])
            ->assertHasActionErrors(['template_key']);

        Bus::assertNotDispatched(SendBulkParticipantEmailJob::class);
    }

    /**
     * No notification fake here — the receipt itself is a notification, and the
     * suite mails to the array transport anyway.
     */
    public function test_the_sender_gets_a_database_receipt_when_the_job_finishes(): void
    {
        $series = $this->seriesWithParticipants(2);
        $ids = $series->activeTakers()->pluck('id')->map('strval')->all();

        config(['mail.suppress_non_admin' => false]);

        (new SendBulkParticipantEmailJob(
            signupClass: CourseEnrollment::class,
            signupIds: $ids,
            templateKey: null,
            subject: 'Změna místa',
            bodyHtml: '<p>Text.</p>',
            senderId: $this->admin->getKey(),
        ))->handle();

        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $this->admin->getKey(),
            'notifiable_type' => $this->admin->getMorphClass(),
        ]);
    }

    public function test_the_action_is_hidden_when_nobody_is_enrolled(): void
    {
        $series = CourseSeries::factory()->create();

        Livewire::test(ViewCourseSeries::class, ['record' => $series->getKey()])
            ->assertActionHidden(SendBulkParticipantEmailAction::getDefaultName());
    }
}

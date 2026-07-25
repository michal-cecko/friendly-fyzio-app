<?php

namespace Tests\Feature\Enrollments;

use App\Enums\EmailTemplateKey;
use App\Models\Course;
use App\Models\CourseSeries;
use App\Models\User;
use App\Models\WaitlistEntry;
use App\Notifications\EnrollmentTemplateNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class WaitlistEntryEmailableTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
    }

    private function seriesEntry(array $attributes = []): WaitlistEntry
    {
        $series = CourseSeries::factory()->create([
            'start_date' => today()->addWeeks(2)->toDateString(),
            'end_date' => today()->addWeeks(14)->toDateString(),
        ]);

        return WaitlistEntry::factory()
            ->forWaitlistable($series)
            ->create(array_merge(['client_id' => null], $attributes));
    }

    public function test_only_bare_renderable_templates_are_offered(): void
    {
        $groups = $this->seriesEntry()->emailTemplateGroups();

        $this->assertArrayHasKey('Klient', $groups);
        $this->assertArrayHasKey(EmailTemplateKey::WaitlistJoined->value, $groups['Klient']);
        $this->assertArrayHasKey(EmailTemplateKey::OfferInvitation->value, $groups['Klient']);
        // Payment/enrollment templates would render broken {{ castka }} / {{ qr }}.
        $this->assertArrayNotHasKey(EmailTemplateKey::WaitlistSpotAvailable->value, $groups['Klient']);
        $this->assertArrayNotHasKey(EmailTemplateKey::CourseEnrollmentReceived->value, $groups['Klient']);
    }

    public function test_a_course_interest_entry_also_exposes_templates(): void
    {
        $course = Course::factory()->create();
        $entry = WaitlistEntry::factory()->forWaitlistable($course)->create(['client_id' => null]);

        $this->assertArrayHasKey('Klient', $entry->emailTemplateGroups());
    }

    public function test_an_entry_without_an_email_offers_no_templates(): void
    {
        $entry = $this->seriesEntry(['email' => null]);

        $this->assertSame([], $entry->emailTemplateGroups());
    }

    public function test_waitlist_joined_renders_from_the_bare_entry_and_carries_no_payment_tokens(): void
    {
        $entry = $this->seriesEntry(['email' => 'klara@example.cz']);

        $entry->sendTemplateEmail(EmailTemplateKey::WaitlistJoined);

        Notification::assertSentOnDemand(
            EnrollmentTemplateNotification::class,
            function (EnrollmentTemplateNotification $notification, array $channels, object $notifiable): bool {
                return $notification->key === EmailTemplateKey::WaitlistJoined
                    && ($notifiable->routes['mail'] ?? null) === 'klara@example.cz'
                    && $notification->tokens['poradi'] === '1'
                    && filled($notification->tokens['nazev'])
                    && ! array_key_exists('castka', $notification->tokens)
                    && ! array_key_exists('qr', $notification->tokens);
            }
        );
    }

    public function test_offer_invitation_carries_the_signup_link(): void
    {
        $entry = $this->seriesEntry(['email' => 'klara@example.cz']);

        $entry->sendTemplateEmail(EmailTemplateKey::OfferInvitation);

        Notification::assertSentOnDemand(
            EnrollmentTemplateNotification::class,
            fn (EnrollmentTemplateNotification $notification): bool => $notification->key === EmailTemplateKey::OfferInvitation
                && filled($notification->tokens['odkaz'])
        );
    }

    public function test_a_linked_client_receives_the_template_directly(): void
    {
        $client = User::factory()->customer()->create(['email' => 'znama@example.cz']);
        $entry = $this->seriesEntry(['client_id' => $client->id]);

        $entry->sendTemplateEmail(EmailTemplateKey::WaitlistJoined);

        Notification::assertSentTo(
            $client,
            EnrollmentTemplateNotification::class,
            fn (EnrollmentTemplateNotification $notification): bool => $notification->key === EmailTemplateKey::WaitlistJoined
        );
    }
}

<?php

namespace Tests\Feature\Enrollments;

use App\Enums\BookingStatus;
use App\Enums\EmailTemplateKey;
use App\Enums\OfferState;
use App\Enums\OfferVisibility;
use App\Filament\Clusters\Workshopy\Resources\Workshops\Pages\EditWorkshop;
use App\Livewire\CourseArchive;
use App\Livewire\WorkshopArchive;
use App\Models\Course;
use App\Models\OneTimeLesson;
use App\Models\User;
use App\Models\Workshop;
use App\Notifications\EnrollmentTemplateNotification;
use App\Support\Enrollments\EnrollmentData;
use App\Support\Enrollments\OfferClosedException;
use App\Support\Enrollments\SignUpForOffer;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class PrivateOfferAccessTest extends TestCase
{
    use RefreshDatabase;

    private function privateWorkshop(): Workshop
    {
        return Workshop::factory()->create([
            'name' => 'Tajný workshop',
            'visibility' => OfferVisibility::Private,
            'published_at' => now(),
            'workshop_date' => today()->addWeeks(2)->toDateString(),
            'capacity' => 10,
        ]);
    }

    public function test_private_offer_reports_preparing_but_presale_state_opens(): void
    {
        $workshop = $this->privateWorkshop();

        $this->assertTrue($workshop->isPrivate());
        $this->assertSame(OfferState::Preparing, $workshop->offerState());
        $this->assertSame(OfferState::Open, $workshop->offerStateForPresale());
        $this->assertStringContainsString('predprodej=', $workshop->presaleUrl());
    }

    public function test_private_workshop_hidden_from_guest_archive_but_shown_to_logged_in_customer(): void
    {
        $workshop = $this->privateWorkshop();

        Livewire::test(WorkshopArchive::class)->assertDontSee('Tajný workshop');

        $this->actingAs(User::factory()->customer()->create());
        Livewire::test(WorkshopArchive::class)->assertSee('Tajný workshop');
    }

    public function test_private_lesson_hidden_from_guest_archive_but_shown_to_logged_in_customer(): void
    {
        $course = Course::factory()->create(['published_at' => now()]);
        OneTimeLesson::factory()->create([
            'course_id' => $course->getKey(),
            'visibility' => OfferVisibility::Private,
            'published_at' => now(),
            'lesson_date' => today()->addWeek()->toDateString(),
        ]);

        Livewire::test(CourseArchive::class)->set('type', 'lekce')->assertDontSee($course->name);

        $this->actingAs(User::factory()->customer()->create());
        Livewire::test(CourseArchive::class)->set('type', 'lekce')->assertSee($course->name);
    }

    public function test_private_workshop_detail_is_404_for_guest_but_ok_for_customer_and_token(): void
    {
        $workshop = $this->privateWorkshop();

        $this->get('/workshopy/'.$workshop->slug)->assertNotFound();

        // Shared hidden link unlocks it for anyone.
        $this->get('/workshopy/'.$workshop->slug.'?predprodej='.$workshop->ensurePresaleToken())
            ->assertOk()
            ->assertSee('Přihlásit se a zaplatit');

        // A logged-in customer sees it without the token.
        $this->actingAs(User::factory()->customer()->create());
        $this->get('/workshopy/'.$workshop->slug)
            ->assertOk()
            ->assertSee('Přihlásit se a zaplatit');
    }

    public function test_customer_can_enrol_in_a_private_workshop_via_presale_but_not_without(): void
    {
        Notification::fake();

        $workshop = $this->privateWorkshop();
        $data = new EnrollmentData('Jana Nová', 'jana.private@example.cz', '+420 604 000 111', null, null);

        $this->expectException(OfferClosedException::class);
        app(SignUpForOffer::class)->forWorkshop($workshop, $data);
    }

    public function test_presale_enrolment_in_a_private_workshop_succeeds(): void
    {
        Notification::fake();

        $workshop = $this->privateWorkshop();
        $data = new EnrollmentData('Jana Nová', 'jana.private2@example.cz', '+420 604 000 222', null, null);

        $registration = app(SignUpForOffer::class)->forWorkshop($workshop, $data, viaPresale: true);

        $this->assertSame(BookingStatus::Confirmed, $registration->status);
        $this->assertTrue($workshop->registrations()->whereKey($registration->getKey())->exists());
    }

    public function test_invite_action_emails_selected_customers_and_is_hidden_for_public_offers(): void
    {
        Notification::fake();
        Filament::setCurrentPanel('admin');
        $this->actingAs(User::factory()->admin()->create());

        $workshop = $this->privateWorkshop();
        $recipients = User::factory()->customer()->count(2)->create();

        Livewire::test(EditWorkshop::class, ['record' => $workshop->getKey()])
            ->callAction('sendInvitation', [
                'recipient_ids' => $recipients->pluck('id')->all(),
                'zprava' => 'Vítejte v předprodeji.',
            ])
            ->assertHasNoActionErrors();

        foreach ($recipients as $recipient) {
            Notification::assertSentTo(
                $recipient,
                EnrollmentTemplateNotification::class,
                fn (EnrollmentTemplateNotification $n): bool => $n->key === EmailTemplateKey::OfferInvitation,
            );
        }

        $public = Workshop::factory()->create([
            'visibility' => OfferVisibility::Public,
            'published_at' => now(),
            'workshop_date' => today()->addWeeks(2)->toDateString(),
        ]);

        Livewire::test(EditWorkshop::class, ['record' => $public->getKey()])
            ->assertActionHidden('sendInvitation');
    }
}

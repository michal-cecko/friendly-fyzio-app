<?php

namespace Tests\Feature\Enrollments;

use App\Enums\BookingStatus;
use App\Enums\EmailTemplateKey;
use App\Enums\OfferState;
use App\Enums\OfferVisibility;
use App\Filament\Clusters\Kurzy\Resources\OneOffEvents\Pages\EditOneOffEvent;
use App\Livewire\OneOffEventArchive;
use App\Models\EventCategory;
use App\Models\OneOffEvent;
use App\Models\User;
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

    private function privateEvent(): OneOffEvent
    {
        return OneOffEvent::factory()
            ->forCategory(EventCategory::query()->where('slug', 'workshopy')->firstOrFail())
            ->create([
                'name' => 'Tajný workshop',
                'visibility' => OfferVisibility::Private,
                'published_at' => now(),
                'event_date' => today()->addWeeks(2)->toDateString(),
                'capacity' => 10,
            ]);
    }

    public function test_private_offer_reports_preparing_but_presale_state_opens(): void
    {
        $event = $this->privateEvent();

        $this->assertTrue($event->isPrivate());
        $this->assertSame(OfferState::Preparing, $event->offerState());
        $this->assertSame(OfferState::Open, $event->offerStateForPresale());
        $this->assertStringContainsString('predprodej=', $event->presaleUrl());
    }

    public function test_private_event_hidden_from_guest_archive_but_shown_to_logged_in_customer(): void
    {
        $this->privateEvent();

        Livewire::test(OneOffEventArchive::class)->assertDontSee('Tajný workshop');

        $this->actingAs(User::factory()->customer()->create());
        Livewire::test(OneOffEventArchive::class)->assertSee('Tajný workshop');
    }

    public function test_private_event_detail_is_404_for_guest_but_ok_for_customer_and_token(): void
    {
        $event = $this->privateEvent();

        $this->get('/workshopy/'.$event->slug)->assertNotFound();

        // Shared hidden link unlocks it for anyone.
        $this->get('/workshopy/'.$event->slug.'?predprodej='.$event->ensurePresaleToken())
            ->assertOk()
            ->assertSee('Přihlásit se a zaplatit');

        // A logged-in customer sees it without the token.
        $this->actingAs(User::factory()->customer()->create());
        $this->get('/workshopy/'.$event->slug)
            ->assertOk()
            ->assertSee('Přihlásit se a zaplatit');
    }

    public function test_customer_cannot_enrol_in_a_private_event_without_presale(): void
    {
        Notification::fake();

        $event = $this->privateEvent();
        $data = new EnrollmentData('Jana Nová', 'jana.private@example.cz', '+420 604 000 111', null, null);

        $this->expectException(OfferClosedException::class);
        app(SignUpForOffer::class)->forEvent($event, $data);
    }

    public function test_presale_enrolment_in_a_private_event_succeeds(): void
    {
        Notification::fake();

        $event = $this->privateEvent();
        $data = new EnrollmentData('Jana Nová', 'jana.private2@example.cz', '+420 604 000 222', null, null);

        $booking = app(SignUpForOffer::class)->forEvent($event, $data, viaPresale: true);

        $this->assertSame(BookingStatus::Confirmed, $booking->status);
        $this->assertTrue($event->bookings()->whereKey($booking->getKey())->exists());
    }

    public function test_invite_action_emails_selected_customers_and_is_hidden_for_public_offers(): void
    {
        Notification::fake();
        Filament::setCurrentPanel('admin');
        $this->actingAs(User::factory()->admin()->create());

        $event = $this->privateEvent();
        $recipients = User::factory()->customer()->count(2)->create();

        Livewire::test(EditOneOffEvent::class, ['record' => $event->getKey()])
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

        $public = OneOffEvent::factory()->create([
            'visibility' => OfferVisibility::Public,
            'published_at' => now(),
            'event_date' => today()->addWeeks(2)->toDateString(),
        ]);

        Livewire::test(EditOneOffEvent::class, ['record' => $public->getKey()])
            ->assertActionHidden('sendInvitation');
    }
}

<?php

namespace Tests\Feature;

use App\Enums\CourseSeriesVisibility;
use App\Enums\OfferVisibility;
use App\Filament\Clusters\Kurzy\Resources\CourseSeries\Pages\ViewCourseSeries;
use App\Filament\Clusters\Kurzy\Resources\Lessons\Pages\ViewLesson;
use App\Models\CourseSeries;
use App\Models\Lesson;
use App\Models\User;
use App\Notifications\EnrollmentTemplateNotification;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Both offer detail pages expose the hidden sign-up link and the invitation
 * action, which previously lived only on their edit pages. Both stay gated to
 * Private offers.
 */
class OfferInvitationActionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');

        $this->actingAs(User::factory()->admin()->create());
    }

    public function test_series_detail_offers_the_link_and_the_invitation(): void
    {
        $series = CourseSeries::factory()->create(['visibility' => CourseSeriesVisibility::Private]);

        Livewire::test(ViewCourseSeries::class, ['record' => $series->getKey()])
            ->assertActionExists('presaleLink')
            ->assertActionExists('sendInvitation');
    }

    public function test_event_detail_offers_the_link_and_the_invitation(): void
    {
        $event = Lesson::factory()->create([
            'visibility' => OfferVisibility::Private,
            'lesson_date' => today()->addWeeks(2)->toDateString(),
        ]);

        Livewire::test(ViewLesson::class, ['record' => $event->getKey()])
            ->assertActionExists('presaleLink')
            ->assertActionExists('sendInvitation')
            ->assertActionExists('delete')
            ->assertActionExists('activityLog');
    }

    /**
     * A public offer takes sign-ups from anyone, so neither the hidden link nor
     * the invitation it carries has anything to add.
     */
    public function test_link_and_invitation_stay_hidden_on_public_offers(): void
    {
        $series = CourseSeries::factory()->create(['visibility' => CourseSeriesVisibility::Public]);

        Livewire::test(ViewCourseSeries::class, ['record' => $series->getKey()])
            ->assertActionHidden('presaleLink')
            ->assertActionHidden('sendInvitation');

        $event = Lesson::factory()->create([
            'visibility' => OfferVisibility::Public,
            'lesson_date' => today()->addWeeks(2)->toDateString(),
        ]);

        Livewire::test(ViewLesson::class, ['record' => $event->getKey()])
            ->assertActionHidden('presaleLink')
            ->assertActionHidden('sendInvitation');
    }

    /**
     * The link text is copyable on click, but the modal also carries a labelled
     * copy button so the affordance is obvious.
     */
    public function test_presale_modal_carries_a_copy_button(): void
    {
        $series = CourseSeries::factory()->create(['visibility' => CourseSeriesVisibility::Private]);

        Livewire::test(ViewCourseSeries::class, ['record' => $series->getKey()])
            ->mountAction('presaleLink')
            ->assertActionExists(TestAction::make('copyPresaleLink'));
    }

    /**
     * A CC/BCC on the invitation is the sender's single archive of the send-out,
     * not a copy for every guest — it rides on exactly one message.
     */
    public function test_invitation_copy_rides_on_only_one_message(): void
    {
        Notification::fake();
        config(['mail.suppress_non_admin' => false]);

        $series = CourseSeries::factory()->create(['visibility' => CourseSeriesVisibility::Private]);
        $guests = User::factory()->customer()->count(3)->create();

        Livewire::test(ViewCourseSeries::class, ['record' => $series->getKey()])
            ->callAction('sendInvitation', [
                'recipient_ids' => $guests->modelKeys(),
                'bcc' => ['archiv@friendlyfyzio.cz'],
            ])
            ->assertHasNoActionErrors();

        $withCopies = 0;

        Notification::assertSentTimes(EnrollmentTemplateNotification::class, 3);

        foreach ($guests as $guest) {
            Notification::assertSentTo(
                $guest,
                EnrollmentTemplateNotification::class,
                function (EnrollmentTemplateNotification $notification) use (&$withCopies): bool {
                    if ($notification->copies !== null) {
                        $withCopies++;
                    }

                    return true;
                },
            );
        }

        $this->assertSame(1, $withCopies);
    }
}

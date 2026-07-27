<?php

namespace Tests\Feature\ActivityLog;

use App\Enums\EmailTemplateKey;
use App\Enums\ReservationStatus;
use App\Models\Lesson;
use App\Models\LessonAttendance;
use App\Models\Reservation;
use App\Models\Service;
use App\Notifications\ReservationTemplateNotification;
use App\Support\ActivityLog\ActivityPresenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class ActivitySummaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_created_event_reads_as_a_gendered_sentence(): void
    {
        $service = Service::factory()->create(['name' => 'Masáž zad']);

        $activity = Activity::query()
            ->where('subject_id', $service->getKey())
            ->where('event', 'created')
            ->latest('id')
            ->firstOrFail();

        $this->assertSame('Vytvořena služba Masáž zad', ActivityPresenter::summary($activity));
    }

    public function test_single_field_update_shows_the_value_change_with_an_arrow(): void
    {
        $service = Service::factory()->create(['name' => 'Původní název']);
        $service->update(['name' => 'Nový název']);

        $activity = Activity::query()
            ->where('subject_id', $service->getKey())
            ->where('event', 'updated')
            ->latest('id')
            ->firstOrFail();

        $summary = ActivityPresenter::summary($activity);

        $this->assertStringContainsString('Služba Nový název: Název', $summary);
        $this->assertStringContainsString('Původní název', $summary);
        $this->assertStringContainsString('→', $summary);
    }

    public function test_enum_values_are_resolved_to_their_czech_labels(): void
    {
        $reservation = Reservation::factory()->create();

        $activity = Activity::create([
            'log_name' => 'default',
            'description' => 'Rezervace',
            'subject_type' => 'reservation',
            'subject_id' => $reservation->getKey(),
            'event' => 'updated',
            'attribute_changes' => [
                'attributes' => ['status' => ReservationStatus::Confirmed->value],
                'old' => ['status' => ReservationStatus::Pending->value],
            ],
        ]);

        $summary = ActivityPresenter::summary($activity);

        $this->assertStringContainsString('Stav', $summary);
        $this->assertStringContainsString('Čeká na potvrzení', $summary);
        $this->assertStringContainsString('Potvrzeno', $summary);
    }

    public function test_multi_field_update_lists_the_changed_field_labels(): void
    {
        $service = Service::factory()->create(['name' => 'Test']);

        $activity = Activity::create([
            'log_name' => 'default',
            'description' => 'Služba',
            'subject_type' => 'service',
            'subject_id' => $service->getKey(),
            'event' => 'updated',
            'attribute_changes' => [
                'attributes' => ['name' => 'Test', 'price' => 500],
                'old' => ['name' => 'Původní', 'price' => 400],
            ],
        ]);

        $summary = ActivityPresenter::summary($activity);

        $this->assertStringContainsString('Upravena služba', $summary);
        $this->assertStringContainsString('Název', $summary);
        $this->assertStringContainsString('Cena', $summary);
    }

    public function test_deleted_event_reads_as_a_sentence(): void
    {
        $service = Service::factory()->create(['name' => 'Ke smazání']);
        $service->delete();

        $activity = Activity::query()
            ->where('subject_id', $service->getKey())
            ->where('event', 'deleted')
            ->latest('id')
            ->firstOrFail();

        $this->assertStringContainsString('Smazána služba', ActivityPresenter::summary($activity));
        $this->assertStringContainsString('Ke smazání', ActivityPresenter::summary($activity));
    }

    public function test_email_sent_event_summarises_subject_and_recipient(): void
    {
        $reservation = Reservation::factory()->create();
        $reservation->client->notify(
            new ReservationTemplateNotification($reservation, EmailTemplateKey::ReservationConfirmed)
        );

        $activity = Activity::query()
            ->where('event', 'email_sent')
            ->where('subject_id', $reservation->getKey())
            ->latest('id')
            ->firstOrFail();

        $this->assertStringStartsWith('Odeslán e-mail', ActivityPresenter::summary($activity));
    }

    /**
     * A presence event is about a person at a place: the client leads, the
     * lesson follows. The subject is the seat, whose own title is the client
     * again — repeating it would read as "Jana — Účast na lekci · Jana".
     */
    public function test_a_presence_event_names_the_client_and_the_lesson(): void
    {
        $attendance = LessonAttendance::factory()->create();

        $activity = Activity::create([
            'log_name' => 'default',
            'description' => 'Klient odhlášen z lekce',
            'subject_type' => $attendance->getMorphClass(),
            'subject_id' => $attendance->getKey(),
            'event' => 'lesson_absence',
            'properties' => ['client' => 'Jana Nováková', 'lesson_id' => $attendance->lesson_id],
        ]);

        $summary = ActivityPresenter::summary($activity);

        $this->assertStringStartsWith('Odhlášen z lekce: Jana Nováková — ', $summary);
        $this->assertStringContainsString($attendance->lesson->logTitle(), $summary);
        $this->assertStringNotContainsString('Účast na lekci', $summary);
    }

    /**
     * These events used to be filed against the lesson itself; those rows have
     * to keep reading properly.
     */
    public function test_a_legacy_presence_event_on_a_lesson_still_reads(): void
    {
        $lesson = Lesson::factory()->create();

        $activity = Activity::create([
            'log_name' => 'default',
            'description' => 'Klient vrácen do lekce',
            'subject_type' => $lesson->getMorphClass(),
            'subject_id' => $lesson->getKey(),
            'event' => 'lesson_absence_reverted',
            'properties' => ['client' => 'Jana Nováková'],
        ]);

        $this->assertSame(
            'Vrácen do lekce: Jana Nováková — '.$lesson->logTitle(),
            ActivityPresenter::summary($activity),
        );
    }

    public function test_generic_semantic_event_uses_the_event_label(): void
    {
        $service = Service::factory()->create();

        $activity = Activity::create([
            'log_name' => 'default',
            'description' => 'Platba',
            'subject_type' => 'service',
            'subject_id' => $service->getKey(),
            'event' => 'payment_received',
        ]);

        $this->assertStringContainsString('Platba přijata', ActivityPresenter::summary($activity));
    }
}

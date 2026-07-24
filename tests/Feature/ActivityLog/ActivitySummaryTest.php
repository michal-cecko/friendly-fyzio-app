<?php

namespace Tests\Feature\ActivityLog;

use App\Enums\EmailTemplateKey;
use App\Enums\ReservationStatus;
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

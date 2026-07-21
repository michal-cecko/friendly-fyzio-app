<?php

namespace Tests\Feature\Reservations;

use App\Enums\EmailTemplateKey;
use App\Models\ReservationDayWaitlistEntry;
use App\Models\Service;
use App\Models\StaffProfile;
use App\Models\User;
use App\Notifications\ReservationDayWaitlistNotification;
use App\Support\Reservations\JoinReservationDayWaitlist;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class DayWaitlistJoinTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
    }

    private function join(): JoinReservationDayWaitlist
    {
        return app(JoinReservationDayWaitlist::class);
    }

    public function test_a_guest_can_join_and_gets_a_confirmation_email(): void
    {
        $therapist = StaffProfile::factory()->create();
        $date = today()->addWeek()->toDateString();

        $entry = $this->join()->handle($therapist->id, $date, 'Jana Nováková', 'jana@example.cz', '+420604793255');

        $this->assertNull($entry->client_id);
        $this->assertSame($therapist->id, $entry->therapist_id);
        $this->assertSame($date, $entry->reservation_date->toDateString());

        Notification::assertSentOnDemand(
            ReservationDayWaitlistNotification::class,
            fn (ReservationDayWaitlistNotification $notification): bool => $notification->key === EmailTemplateKey::ReservationDayWaitlistJoined,
        );
    }

    public function test_joining_is_idempotent_per_therapist_day(): void
    {
        $therapist = StaffProfile::factory()->create();
        $date = today()->addWeek()->toDateString();

        $first = $this->join()->handle($therapist->id, $date, 'Jana', 'jana@example.cz', null);
        // Same person, same therapist-day, different browsed service context — no duplicate.
        $second = $this->join()->handle($therapist->id, $date, 'Jana', 'jana@example.cz', null, Service::factory()->create());

        $this->assertTrue($first->is($second));
        $this->assertSame(1, ReservationDayWaitlistEntry::query()->count());
    }

    public function test_an_existing_account_is_linked_by_email(): void
    {
        $client = User::factory()->customer()->create(['email' => 'jana@example.cz']);
        $therapist = StaffProfile::factory()->create();

        $entry = $this->join()->handle($therapist->id, today()->addWeek()->toDateString(), 'Jana', 'jana@example.cz', null);

        $this->assertSame($client->id, $entry->client_id);
        Notification::assertSentTo($client, ReservationDayWaitlistNotification::class);
    }

    public function test_account_linking_by_email_is_case_insensitive(): void
    {
        $client = User::factory()->customer()->create(['email' => 'jana@example.cz']);
        $therapist = StaffProfile::factory()->create();

        $entry = $this->join()->handle($therapist->id, today()->addWeek()->toDateString(), 'Jana', 'Jana@Example.CZ', null);

        $this->assertSame($client->id, $entry->client_id);
    }

    public function test_any_therapist_scope_is_stored_as_null(): void
    {
        $entry = $this->join()->handle(null, today()->addWeek()->toDateString(), 'Jana', 'jana@example.cz', null);

        $this->assertNull($entry->therapist_id);
        $this->assertSame('Libovolný terapeut', $entry->therapistLabel());
    }

    public function test_past_dated_entries_are_prunable(): void
    {
        $past = ReservationDayWaitlistEntry::factory()->create(['reservation_date' => today()->subDay()->toDateString()]);
        $future = ReservationDayWaitlistEntry::factory()->create(['reservation_date' => today()->addDay()->toDateString()]);

        $prunable = (new ReservationDayWaitlistEntry)->prunable()->pluck('id');

        $this->assertTrue($prunable->contains($past->id));
        $this->assertFalse($prunable->contains($future->id));
    }
}

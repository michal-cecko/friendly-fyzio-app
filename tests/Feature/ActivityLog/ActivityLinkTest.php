<?php

namespace Tests\Feature\ActivityLog;

use App\Models\Reservation;
use App\Models\User;
use App\Support\ActivityLog\ActivityPresenter;
use App\Support\ActivityLog\ActivityValue;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActivityLinkTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');
        $this->actingAs(User::factory()->admin()->create());
    }

    public function test_a_named_client_in_a_semantic_event_links_to_their_file(): void
    {
        $client = User::factory()->customer()->create(['name' => 'Vlasta Buriánková']);

        $rows = ActivityValue::rows($client->name, 'client');

        $this->assertSame('Vlasta Buriánková', $rows[0]['value']);
        $this->assertNotNull($rows[0]['url']);
        $this->assertStringContainsString($client->getKey(), $rows[0]['url']);
    }

    public function test_an_ambiguous_name_is_not_linked(): void
    {
        User::factory()->customer()->count(2)->create(['name' => 'Jan Novák']);

        $rows = ActivityValue::rows('Jan Novák', 'client');

        $this->assertSame('Jan Novák', $rows[0]['value']);
        $this->assertNull($rows[0]['url']);
    }

    public function test_an_unknown_name_stays_plain_text(): void
    {
        $rows = ActivityValue::rows('neznámý klient', 'client');

        $this->assertSame('neznámý klient', $rows[0]['value']);
        $this->assertNull($rows[0]['url']);
    }

    public function test_a_foreign_key_resolves_through_the_subjects_relation(): void
    {
        $reservation = Reservation::factory()->create();
        $client = $reservation->client;

        // The raw UUID a diff would show becomes the client's name, linked.
        $rows = ActivityValue::rows($client->getKey(), 'client_id', $reservation);

        $this->assertSame($client->name, $rows[0]['value']);
        $this->assertNotNull($rows[0]['url']);
        $this->assertStringContainsString($client->getKey(), $rows[0]['url']);
    }

    public function test_a_foreign_key_pointing_at_a_deleted_record_still_links(): void
    {
        $reservation = Reservation::factory()->create();
        $client = $reservation->client;
        $client->delete();

        $rows = ActivityValue::rows($client->getKey(), 'client_id', $reservation->fresh());

        $this->assertSame($client->name, $rows[0]['value']);
        $this->assertNotNull($rows[0]['url']);
    }

    public function test_a_foreign_key_with_no_matching_record_stays_plain(): void
    {
        $reservation = Reservation::factory()->create();

        $rows = ActivityValue::rows('019f0000-0000-7000-8000-000000000000', 'client_id', $reservation);

        $this->assertNull($rows[0]['url']);
    }

    public function test_semantic_event_property_keys_are_localized(): void
    {
        $labels = [
            'client' => 'Klient',
            'substitute_token' => 'Poukaz na náhradu',
            'substitute_token_withdrawn' => 'Poukaz na náhradu odebrán',
            'override' => 'Poukaz nad rámec pravidel',
            'past' => 'Lekce už proběhla',
            'notified' => 'Zákazník upozorněn',
            'cc' => 'Kopie',
            'bcc' => 'Skrytá kopie',
            'excuse_reason' => 'Důvod omluvy',
            'excused_by_id' => 'Omluvil',
        ];

        foreach ($labels as $key => $expected) {
            $this->assertSame($expected, ActivityPresenter::attributeLabel($key), "Label for {$key}");
        }
    }
}

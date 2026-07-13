<?php

namespace Tests\Feature;

use App\Enums\ReservationStatus;
use App\Filament\Support\Search\AdminSearchGroup;
use App\Filament\Support\Search\AdminSearchResult;
use App\Filament\Support\Search\AdminSearchService;
use App\Filament\Support\Search\SearchHighlighter;
use App\Models\Reservation;
use App\Models\Service;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

class AdminSearchServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');

        $this->actingAs(User::factory()->admin()->create());
    }

    protected function service(): AdminSearchService
    {
        return app(AdminSearchService::class);
    }

    /**
     * @param  Collection<int, AdminSearchGroup>  $groups
     * @return Collection<int, AdminSearchResult>
     */
    protected function group(Collection $groups, string $label): Collection
    {
        $group = $groups->first(fn (AdminSearchGroup $group): bool => $group->label === $label);

        $this->assertNotNull($group, "Expected a '{$label}' result group, got: ".$groups->pluck('label')->implode(', '));

        return $group->results;
    }

    public function test_finds_reservation_by_client_name_with_composed_title_and_details(): void
    {
        $client = User::factory()->customer()->create(['name' => 'Zdislava Vyhledávaná']);
        $service = Service::factory()->create(['name' => 'Vstupní fyzioterapie']);

        Reservation::factory()->create([
            'client_id' => $client->getKey(),
            'service_id' => $service->getKey(),
            'status' => ReservationStatus::Confirmed,
        ]);

        $reservations = $this->group($this->service()->search('Zdislava'), 'Rezervace');

        $this->assertCount(1, $reservations);

        $result = $reservations->first();

        $this->assertSame('Zdislava Vyhledávaná — Vstupní fyzioterapie', $result->title);
        $this->assertArrayHasKey('Termín', $result->details);
        $this->assertSame('Potvrzeno', $result->details['Stav']);
        $this->assertFalse($result->isTrashed);
        $this->assertNotNull($result->url);
    }

    public function test_finds_reservation_by_service_name(): void
    {
        Reservation::factory()->create([
            'service_id' => Service::factory()->create(['name' => 'Kraniosakrální terapie'])->getKey(),
        ]);

        $this->group($this->service()->search('Kraniosakrální'), 'Rezervace');
    }

    public function test_trashed_reservations_are_flagged_and_can_be_excluded(): void
    {
        $client = User::factory()->customer()->create(['name' => 'Zdislava Smazaná']);

        Reservation::factory()->create(['client_id' => $client->getKey()])->delete();

        $withTrashed = $this->group($this->service()->search('Zdislava Smazaná'), 'Rezervace');

        $this->assertTrue($withTrashed->first()->isTrashed);

        $withoutTrashed = $this->service()->search('Zdislava Smazaná', includeTrashed: false);

        $this->assertNull($withoutTrashed->first(fn (AdminSearchGroup $group): bool => $group->label === 'Rezervace'));
    }

    public function test_results_carry_group_icon_and_highlighted_title(): void
    {
        User::factory()->customer()->create(['name' => 'Zdislava Zvýrazněná']);

        $groups = $this->service()->search('Zvýrazněná');

        $group = $groups->first(fn (AdminSearchGroup $group): bool => $group->label === 'Klienti');

        $this->assertNotNull($group);
        $this->assertNotNull($group->icon);

        $titleHtml = (string) $group->results->first()->titleHtml;

        $this->assertStringContainsString('<mark', $titleHtml);
        $this->assertStringContainsString('Zvýrazněná</mark>', $titleHtml);
    }

    public function test_highlighter_escapes_html_and_marks_matches_case_insensitively(): void
    {
        $highlighter = app(SearchHighlighter::class);

        $html = (string) $highlighter->highlight('Jan <script>alert(1)</script> NOVÁK', 'novák');

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringContainsString('NOVÁK</mark>', $html);

        $plain = (string) $highlighter->highlight('Jan Novák', '');

        $this->assertSame('Jan Novák', $plain);
    }

    public function test_short_queries_return_nothing(): void
    {
        User::factory()->customer()->create(['name' => 'Zdislava']);

        $this->assertTrue($this->service()->search('Z')->isEmpty());
        $this->assertTrue($this->service()->search('  ')->isEmpty());
    }

    public function test_results_per_resource_are_limited(): void
    {
        User::factory()->customer()->count(AdminSearchService::RESULTS_PER_RESOURCE + 3)
            ->sequence(fn ($sequence) => ['name' => "Zdislava Hromadná {$sequence->index}"])
            ->create();

        $clients = $this->group($this->service()->search('Zdislava Hromadná'), 'Klienti');

        $this->assertCount(AdminSearchService::RESULTS_PER_RESOURCE, $clients);
    }

    public function test_finds_client_by_phone_number(): void
    {
        User::factory()->customer()->create([
            'name' => 'Telefonní Klient',
            'phone' => '+420777123456',
        ]);

        $clients = $this->group($this->service()->search('777123456'), 'Klienti');

        $this->assertSame('Telefonní Klient', $clients->first()->title);
        $this->assertSame('+420777123456', $clients->first()->details['Telefon']);
    }
}

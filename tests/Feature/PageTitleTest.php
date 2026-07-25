<?php

namespace Tests\Feature;

use App\Filament\Clusters\Provoz\Resources\Clients\Pages\EditClient;
use App\Filament\Clusters\Provoz\Resources\Clients\Pages\ViewClient;
use App\Filament\Clusters\Provoz\Resources\Reservations\Pages\EditReservation;
use App\Filament\Clusters\Provoz\Resources\Reservations\Pages\ViewReservation;
use App\Filament\Clusters\Provoz\Resources\Services\Pages\CreateService;
use App\Models\Reservation;
use App\Models\Service;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Guards the hand-written Czech page titles/headings across the admin panel:
 * View pages drop "Zobrazit", Edit pages use the accusative, Create pages use a
 * gendered nominative. A few representative resources cover each shape.
 */
class PageTitleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');

        $this->actingAs(User::factory()->admin()->create());
    }

    public function test_view_page_shows_type_and_record_name_without_zobrazit(): void
    {
        $client = User::factory()->customer()->create(['name' => 'Jan Novák']);

        $title = Livewire::test(ViewClient::class, ['record' => $client->id])
            ->instance()
            ->getTitle();

        $this->assertSame('Klient Jan Novák', $title);
    }

    public function test_edit_page_uses_accusative_with_record_name(): void
    {
        $client = User::factory()->customer()->create(['name' => 'Jan Novák']);

        $title = Livewire::test(EditClient::class, ['record' => $client->id])
            ->instance()
            ->getTitle();

        $this->assertSame('Upravit klienta Jan Novák', $title);
    }

    public function test_create_page_uses_gendered_nominative(): void
    {
        $title = Livewire::test(CreateService::class)
            ->instance()
            ->getTitle();

        $this->assertSame('Nová služba', $title);
    }

    public function test_nameless_reservation_view_shows_client_name(): void
    {
        $client = User::factory()->customer()->create(['name' => 'Eva Malá']);
        $service = Service::factory()->create();
        $reservation = Reservation::factory()->create([
            'client_id' => $client->id,
            'service_id' => $service->id,
        ]);

        $title = Livewire::test(ViewReservation::class, ['record' => $reservation->id])
            ->instance()
            ->getTitle();

        $this->assertSame('Rezervace Eva Malá', $title);
    }

    public function test_reservation_edit_uses_accusative_with_client_name(): void
    {
        $client = User::factory()->customer()->create(['name' => 'Eva Malá']);
        $service = Service::factory()->create();
        $reservation = Reservation::factory()->create([
            'client_id' => $client->id,
            'service_id' => $service->id,
        ]);

        $title = Livewire::test(EditReservation::class, ['record' => $reservation->id])
            ->instance()
            ->getTitle();

        $this->assertSame('Upravit rezervaci Eva Malá', $title);
    }
}

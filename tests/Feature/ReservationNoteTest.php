<?php

namespace Tests\Feature;

use App\Filament\Clusters\Provoz\Resources\Reservations\Pages\ViewReservation;
use App\Filament\Clusters\Provoz\Resources\Reservations\RelationManagers\NotesRelationManager;
use App\Models\Reservation;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ReservationNoteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');
    }

    public function test_note_created_from_a_reservation_is_linked_to_the_reservation_and_its_client(): void
    {
        $admin = User::factory()->admin()->create();
        $reservation = Reservation::factory()->create(['notes' => null]);

        $this->actingAs($admin);

        Livewire::test(NotesRelationManager::class, [
            'ownerRecord' => $reservation,
            'pageClass' => ViewReservation::class,
        ])
            ->callAction(TestAction::make('create')->table(), data: [
                'content' => 'Terapie proběhla dobře.',
            ])
            ->assertHasNoActionErrors();

        $this->assertDatabaseHas('client_notes', [
            'reservation_id' => $reservation->getKey(),
            'client_id' => $reservation->client_id,
            'author_id' => $admin->getKey(),
            'content' => '<p>Terapie proběhla dobře.</p>',
        ]);
    }

    public function test_reservation_note_appears_on_the_client_profile_list_too(): void
    {
        $admin = User::factory()->admin()->create();
        $reservation = Reservation::factory()->create(['notes' => null]);

        $this->actingAs($admin);

        $note = $reservation->clientNotes()->create([
            'client_id' => $reservation->client_id,
            'author_id' => $admin->getKey(),
            'content' => '<p>Poznámka z rezervace.</p>',
        ]);

        $this->assertTrue(
            $reservation->client->clientNotes()->whereKey($note->getKey())->exists(),
        );
    }
}

<?php

namespace Tests\Feature\Clients;

use App\Filament\Clusters\Provoz\Resources\Clients\Pages\ViewClient;
use App\Filament\Clusters\Provoz\Resources\Clients\RelationManagers\InvoicesRelationManager;
use App\Filament\Clusters\Provoz\Resources\Clients\RelationManagers\SubstituteTokensRelationManager;
use App\Filament\Support\RelationManagers\PaymentsRelationManager;
use App\Models\CourseLesson;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\SubstituteToken;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ClientRelationManagersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');

        $this->actingAs(User::factory()->admin()->create());
    }

    public function test_payments_tab_shows_the_clients_payments_only(): void
    {
        $client = User::factory()->customer()->create();
        $mine = Payment::factory()->create(['client_id' => $client->getKey()]);
        $foreign = Payment::factory()->create();

        Livewire::test(PaymentsRelationManager::class, [
            'ownerRecord' => $client,
            'pageClass' => ViewClient::class,
        ])
            ->assertCanSeeTableRecords([$mine])
            ->assertCanNotSeeTableRecords([$foreign]);
    }

    public function test_invoices_tab_shows_the_clients_invoices_only(): void
    {
        $client = User::factory()->customer()->create();
        $mine = Invoice::factory()->create(['client_id' => $client->getKey()]);
        $foreign = Invoice::factory()->create();

        Livewire::test(InvoicesRelationManager::class, [
            'ownerRecord' => $client,
            'pageClass' => ViewClient::class,
        ])
            ->assertCanSeeTableRecords([$mine])
            ->assertCanNotSeeTableRecords([$foreign]);
    }

    public function test_substitute_tokens_tab_shows_the_clients_tokens_only(): void
    {
        $client = User::factory()->customer()->create();
        $lesson = CourseLesson::factory()->create();

        $mine = SubstituteToken::factory()->create([
            'client_id' => $client->getKey(),
            'source_lesson_id' => $lesson->getKey(),
            'expires_at' => now()->addDays(30),
        ]);
        $foreign = SubstituteToken::factory()->create([
            'client_id' => User::factory()->customer()->create()->getKey(),
            'source_lesson_id' => $lesson->getKey(),
            'expires_at' => now()->addDays(30),
        ]);

        Livewire::test(SubstituteTokensRelationManager::class, [
            'ownerRecord' => $client,
            'pageClass' => ViewClient::class,
        ])
            ->assertCanSeeTableRecords([$mine])
            ->assertCanNotSeeTableRecords([$foreign]);
    }
}

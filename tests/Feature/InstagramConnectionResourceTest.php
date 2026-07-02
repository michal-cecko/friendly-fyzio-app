<?php

namespace Tests\Feature;

use App\Filament\Clusters\Obsah\Resources\InstagramConnections\Pages\EditInstagramConnection;
use App\Filament\Clusters\Obsah\Resources\InstagramConnections\Pages\ListInstagramConnections;
use App\Jobs\SyncInstagramConnectionJob;
use App\Models\InstagramConnection;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class InstagramConnectionResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');
    }

    public function test_admin_can_see_connections_in_the_list(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $connection = InstagramConnection::factory()->create();

        Livewire::test(ListInstagramConnections::class)
            ->assertCanSeeTableRecords([$connection]);
    }

    public function test_sync_action_dispatches_job(): void
    {
        Queue::fake();

        $this->actingAs(User::factory()->admin()->create());

        $connection = InstagramConnection::factory()->create();

        Livewire::test(EditInstagramConnection::class, ['record' => $connection->getKey()])
            ->callAction('sync');

        Queue::assertPushed(SyncInstagramConnectionJob::class);
    }

    public function test_authorize_action_is_present_on_edit_page(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $connection = InstagramConnection::factory()->pending()->create();

        Livewire::test(EditInstagramConnection::class, ['record' => $connection->getKey()])
            ->assertActionExists('authorize');
    }
}

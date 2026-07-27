<?php

namespace Tests\Feature;

use App\Filament\Clusters\Obsah\Resources\InstagramConnections\Pages\EditInstagramConnection;
use App\Filament\Clusters\Obsah\Resources\InstagramConnections\Pages\ListInstagramConnections;
use App\Filament\Clusters\Obsah\Resources\InstagramConnections\RelationManagers\PostsRelationManager;
use App\Jobs\SyncInstagramConnectionJob;
use App\Models\InstagramConnection;
use App\Models\InstagramPost;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
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

    public function test_edit_header_keeps_only_save_outside_the_dropdown(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $connection = InstagramConnection::factory()->create();

        $headerActions = Livewire::test(EditInstagramConnection::class, ['record' => $connection->getKey()])
            ->assertActionExists('saveHeader')
            ->instance()
            ->getCachedHeaderActions();

        $this->assertCount(2, $headerActions);
        $this->assertInstanceOf(Action::class, $headerActions[0]);
        $this->assertSame('saveHeader', $headerActions[0]->getName());

        $this->assertInstanceOf(ActionGroup::class, $headerActions[1]);
        $this->assertSame('Další akce', $headerActions[1]->getLabel());

        $this->assertSame(
            ['authorize', 'sync', 'activityLog', 'delete', 'forceDelete', 'restore'],
            array_map(
                fn (Action $action): string => $action->getName(),
                array_values($headerActions[1]->getFlatActions()),
            ),
        );
    }

    public function test_posts_relation_manager_lists_synced_posts(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $connection = InstagramConnection::factory()->create();
        $posts = InstagramPost::factory()->count(3)->create([
            'instagram_connection_id' => $connection->getKey(),
        ]);

        Livewire::test(PostsRelationManager::class, [
            'ownerRecord' => $connection,
            'pageClass' => EditInstagramConnection::class,
        ])
            ->assertCanSeeTableRecords($posts);
    }
}

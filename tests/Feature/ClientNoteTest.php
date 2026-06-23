<?php

namespace Tests\Feature;

use App\Filament\Clusters\Provoz\Resources\Clients\Pages\ViewClient;
use App\Filament\Clusters\Provoz\Resources\Clients\RelationManagers\NotesRelationManager;
use App\Models\ClientNote;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ClientNoteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');

        foreach (['ViewAny:User', 'View:User'] as $name) {
            Permission::findOrCreate($name);
        }

        foreach (['ViewAny', 'View', 'Create', 'Update', 'Delete'] as $action) {
            Permission::findOrCreate("{$action}:ClientNote");
        }
    }

    /**
     * @param  array<int, string>  $permissions
     */
    private function makeTherapistWithNotePermissions(array $permissions = ['ViewAny:ClientNote', 'View:ClientNote', 'Create:ClientNote', 'Update:ClientNote', 'Delete:ClientNote']): User
    {
        $therapist = User::factory()->therapist()->create();
        $therapist->givePermissionTo($permissions);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $therapist->fresh();
    }

    public function test_note_can_be_created_from_client_view_with_author_set_automatically(): void
    {
        $admin = User::factory()->admin()->create();
        $client = User::factory()->customer()->create();

        $this->actingAs($admin);

        Livewire::test(NotesRelationManager::class, [
            'ownerRecord' => $client,
            'pageClass' => ViewClient::class,
        ])
            ->callAction(TestAction::make('create')->table(), data: [
                'content' => 'Klient reaguje dobře na cvičení.',
            ])
            ->assertHasNoActionErrors();

        $this->assertDatabaseHas('client_notes', [
            'client_id' => $client->getKey(),
            'author_id' => $admin->getKey(),
            'content' => 'Klient reaguje dobře na cvičení.',
        ]);
    }

    public function test_note_content_is_required(): void
    {
        $client = User::factory()->customer()->create();

        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(NotesRelationManager::class, [
            'ownerRecord' => $client,
            'pageClass' => ViewClient::class,
        ])
            ->callAction(TestAction::make('create')->table(), data: [
                'content' => null,
            ])
            ->assertHasActionErrors(['content' => 'required']);

        $this->assertDatabaseCount('client_notes', 0);
    }

    public function test_author_can_update_own_note_but_not_someone_elses(): void
    {
        $author = $this->makeTherapistWithNotePermissions();
        $otherTherapist = $this->makeTherapistWithNotePermissions();

        $ownNote = ClientNote::factory()->create(['author_id' => $author->getKey()]);
        $foreignNote = ClientNote::factory()->create(['author_id' => $otherTherapist->getKey()]);

        $this->assertTrue($author->can('update', $ownNote));
        $this->assertTrue($author->can('delete', $ownNote));
        $this->assertFalse($author->can('update', $foreignNote));
        $this->assertFalse($author->can('delete', $foreignNote));
    }

    public function test_admin_can_update_and_delete_any_note(): void
    {
        // Account-type admins are super admins and bypass all gates.
        $superAdmin = User::factory()->admin()->create();

        // Users holding the Shield "admin" role pass the policy's explicit admin branch.
        Role::findOrCreate('admin');
        $manager = $this->makeTherapistWithNotePermissions();
        $manager->assignRole('admin');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $note = ClientNote::factory()->create();

        $this->assertTrue($superAdmin->can('update', $note));
        $this->assertTrue($superAdmin->can('delete', $note));
        $this->assertTrue($manager->can('update', $note));
        $this->assertTrue($manager->can('delete', $note));
    }

    public function test_therapist_without_permission_cannot_create_notes(): void
    {
        $therapist = User::factory()->therapist()->create();

        $this->assertFalse($therapist->can('create', ClientNote::class));
    }
}

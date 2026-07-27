<?php

namespace Tests\Feature;

use App\Filament\Clusters\Provoz\Resources\Clients\Pages\ViewClient;
use App\Filament\Clusters\Provoz\Resources\Clients\RelationManagers\NotesRelationManager;
use App\Models\ClientNote;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
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
            'content' => '<p>Klient reaguje dobře na cvičení.</p>',
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
            // The RichEditor replaces the `required` rule with its own closure
            // (an empty TipTap doc is not blank), so assert any error on the field.
            ->assertHasActionErrors(['content']);

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

    public function test_long_note_is_truncated_in_the_table(): void
    {
        $client = User::factory()->customer()->create();
        $longText = str_repeat('Klient reaguje dobře na cvičení. ', 10);

        ClientNote::factory()->create([
            'client_id' => $client->getKey(),
            'content' => '<p>'.$longText.'</p>',
        ]);

        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(NotesRelationManager::class, [
            'ownerRecord' => $client,
            'pageClass' => ViewClient::class,
        ])
            ->assertSee(Str::limit(trim($longText), 80))
            ->assertDontSee(trim($longText));
    }

    public function test_note_can_be_viewed_in_a_modal_with_its_formatting(): void
    {
        $client = User::factory()->customer()->create();

        $note = ClientNote::factory()->create([
            'client_id' => $client->getKey(),
            'content' => '<p>Klient reaguje <strong>velmi dobře</strong> na cvičení.</p>',
        ]);

        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(NotesRelationManager::class, [
            'ownerRecord' => $client,
            'pageClass' => ViewClient::class,
        ])
            ->mountAction(TestAction::make('view')->table($note))
            // The modal body is rendered by the parent page, so assert the action
            // itself; mounting resolves the infolist schema against the record.
            ->assertActionMounted(TestAction::make('view')->table($note));
    }

    public function test_therapist_can_read_but_not_change_a_note_written_by_someone_else(): void
    {
        $therapist = $this->makeTherapistWithNotePermissions();
        $client = User::factory()->customer()->create();

        $ownNote = ClientNote::factory()->create([
            'client_id' => $client->getKey(),
            'author_id' => $therapist->getKey(),
        ]);
        $foreignNote = ClientNote::factory()->create([
            'client_id' => $client->getKey(),
            'author_id' => User::factory()->therapist()->create()->getKey(),
        ]);

        $this->actingAs($therapist);

        Livewire::test(NotesRelationManager::class, [
            'ownerRecord' => $client,
            'pageClass' => ViewClient::class,
        ])
            ->assertCanSeeTableRecords([$ownNote, $foreignNote])
            ->assertActionVisible(TestAction::make('view')->table($foreignNote))
            ->assertActionHidden(TestAction::make('edit')->table($foreignNote))
            ->assertActionHidden(TestAction::make('delete')->table($foreignNote))
            ->assertActionVisible(TestAction::make('view')->table($ownNote))
            ->assertActionVisible(TestAction::make('edit')->table($ownNote))
            ->assertActionVisible(TestAction::make('delete')->table($ownNote));
    }

    public function test_notes_can_be_filtered_by_author(): void
    {
        $client = User::factory()->customer()->create();
        $author = User::factory()->therapist()->create();
        $otherAuthor = User::factory()->therapist()->create();

        $ownNote = ClientNote::factory()->create([
            'client_id' => $client->getKey(),
            'author_id' => $author->getKey(),
            'content' => '<p>Poznámka od prvního terapeuta.</p>',
        ]);
        $foreignNote = ClientNote::factory()->create([
            'client_id' => $client->getKey(),
            'author_id' => $otherAuthor->getKey(),
            'content' => '<p>Poznámka od druhého terapeuta.</p>',
        ]);

        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(NotesRelationManager::class, [
            'ownerRecord' => $client,
            'pageClass' => ViewClient::class,
        ])
            ->assertCanSeeTableRecords([$ownNote, $foreignNote])
            ->filterTable('author_id', $author->getKey())
            ->assertCanSeeTableRecords([$ownNote])
            ->assertCanNotSeeTableRecords([$foreignNote]);
    }
}

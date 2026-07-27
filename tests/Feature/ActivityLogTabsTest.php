<?php

namespace Tests\Feature;

use App\Filament\Resources\ActivityLog\Pages\ListActivityLog;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ActivityLogTabsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');
    }

    public function test_tabs_expose_all_system_and_one_tab_per_causing_user(): void
    {
        $author = User::factory()->create(['name' => 'Petra Nováková']);

        // Logged out so the entry has no causer — i.e. a "system" entry.
        activity()->log('system action');
        activity()->causedBy($author)->log('user action');

        $this->actingAs(User::factory()->admin()->create());

        $tabs = Livewire::test(ListActivityLog::class)
            ->instance()
            ->getTabs();

        $this->assertSame(
            ['all', 'system', 'user_'.$author->getKey()],
            array_keys($tabs),
        );
        $this->assertSame('Petra Nováková', $tabs['user_'.$author->getKey()]->getLabel());
    }

    public function test_non_admin_therapist_sees_no_tabs(): void
    {
        $author = User::factory()->create(['name' => 'Petra Nováková']);
        activity()->causedBy($author)->log('user action');

        $this->actingAs(User::factory()->therapist()->create());

        $tabs = Livewire::test(ListActivityLog::class)
            ->instance()
            ->getTabs();

        $this->assertSame([], $tabs);
    }

    public function test_system_tab_shows_only_entries_without_a_causer(): void
    {
        $author = User::factory()->create();

        $systemActivity = activity()->log('system action');
        $userActivity = activity()->causedBy($author)->log('user action');

        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(ListActivityLog::class)
            ->set('activeTab', 'system')
            ->assertCanSeeTableRecords([$systemActivity])
            ->assertCanNotSeeTableRecords([$userActivity]);
    }

    public function test_user_tab_shows_only_that_users_entries(): void
    {
        $author = User::factory()->create();
        $other = User::factory()->create();

        $mine = activity()->causedBy($author)->log('mine');
        $theirs = activity()->causedBy($other)->log('theirs');
        $system = activity()->log('system');

        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(ListActivityLog::class)
            ->set('activeTab', 'user_'.$author->getKey())
            ->assertCanSeeTableRecords([$mine])
            ->assertCanNotSeeTableRecords([$theirs, $system]);
    }
}

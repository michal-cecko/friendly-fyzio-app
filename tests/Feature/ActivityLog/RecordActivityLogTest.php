<?php

namespace Tests\Feature\ActivityLog;

use App\Filament\Support\Actions\ActivityLogAction;
use App\Livewire\Admin\RecordActivityLog;
use App\Models\Service;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class RecordActivityLogTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Service $service;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');

        $this->admin = User::factory()->admin()->create(['name' => 'Alena Adminová']);
        $this->actingAs($this->admin);

        // 1 × created + 12 × updated, all caused by the admin.
        $this->service = Service::factory()->create(['name' => 'Kineziologie']);

        foreach (range(1, 12) as $i) {
            $this->service->update(['name' => "Verze {$i}"]);
        }

        // A causer-less row, as scheduled commands and the public flow produce.
        Activity::create([
            'log_name' => 'default',
            'description' => 'Verze 12',
            'subject_type' => $this->service->getMorphClass(),
            'subject_id' => $this->service->getKey(),
            'event' => 'payment_received',
            'properties' => ['amount' => 500],
        ]);
    }

    private function logComponent(): Testable
    {
        return Livewire::test(RecordActivityLog::class, [
            'subjectType' => $this->service->getMorphClass(),
            'subjectId' => (string) $this->service->getKey(),
        ]);
    }

    public function test_it_lists_the_records_activity_newest_first_and_paginates(): void
    {
        $this->logComponent()
            ->assertViewHas('activities', fn (LengthAwarePaginator $activities): bool => $activities->total() === 14
                && $activities->count() === 10
                && $activities->first()->event === 'payment_received')
            ->assertSee('Platba přijata')
            ->call('nextPage')
            ->assertViewHas('activities', fn (LengthAwarePaginator $activities): bool => $activities->count() === 4
                && $activities->last()->event === 'created');
    }

    public function test_search_narrows_the_list(): void
    {
        $this->logComponent()
            ->set('search', 'Verze 3')
            // The rename to "Verze 3" and the rename away from it.
            ->assertViewHas('activities', fn (LengthAwarePaginator $activities): bool => $activities->total() === 2)
            ->set('search', 'naprostý nesmysl bez shody')
            ->assertViewHas('activities', fn (LengthAwarePaginator $activities): bool => $activities->total() === 0)
            ->assertSee('Žádné záznamy neodpovídají filtru.');
    }

    public function test_event_filter_narrows_the_list(): void
    {
        $this->logComponent()
            ->set('event', 'created')
            ->assertViewHas('activities', fn (LengthAwarePaginator $activities): bool => $activities->total() === 1
                && $activities->first()->event === 'created');
    }

    public function test_causer_filter_separates_users_from_system_activity(): void
    {
        $this->logComponent()
            ->set('causer', 'system')
            ->assertViewHas('activities', fn (LengthAwarePaginator $activities): bool => $activities->total() === 1
                && $activities->first()->causer_id === null)
            ->set('causer', (string) $this->admin->getKey())
            ->assertViewHas('activities', fn (LengthAwarePaginator $activities): bool => $activities->total() === 13);
    }

    public function test_date_range_filter_narrows_the_list(): void
    {
        Activity::query()
            ->where('subject_id', $this->service->getKey())
            ->where('event', 'created')
            ->update(['created_at' => now()->subDays(10)]);

        $this->logComponent()
            ->set('from', now()->subDay()->toDateString())
            ->assertViewHas('activities', fn (LengthAwarePaginator $activities): bool => $activities->total() === 13)
            ->set('from', null)
            ->set('to', now()->subDays(5)->toDateString())
            ->assertViewHas('activities', fn (LengthAwarePaginator $activities): bool => $activities->total() === 1);
    }

    public function test_reset_filters_restores_the_full_list(): void
    {
        $this->logComponent()
            ->set('search', 'Verze 3')
            ->set('event', 'updated')
            ->call('resetFilters')
            ->assertSet('search', '')
            ->assertSet('event', null)
            ->assertViewHas('activities', fn (LengthAwarePaginator $activities): bool => $activities->total() === 14);
    }

    public function test_indicator_chips_describe_the_filters_and_clear_them_one_by_one(): void
    {
        $this->logComponent()
            ->set('search', 'Verze 3')
            ->set('event', 'updated')
            ->assertViewHas('indicators', fn (array $indicators): bool => $indicators === [
                ['key' => 'search', 'label' => 'Hledání: Verze 3'],
                ['key' => 'event', 'label' => 'Akce: Upraveno'],
            ])
            ->assertViewHas('panelFilterCount', 1)
            ->call('clearFilter', 'event')
            ->assertSet('event', null)
            ->assertSet('search', 'Verze 3')
            ->assertViewHas('activities', fn (LengthAwarePaginator $activities): bool => $activities->total() === 2);
    }

    public function test_the_header_action_mounts_the_log_for_its_record(): void
    {
        $content = ActivityLogAction::make()->record($this->service)->getModalContent();

        $this->assertNotNull($content);

        $html = $content->render();

        $this->assertStringContainsString('Hledat v historii', $html);
        $this->assertStringContainsString('Platba přijata', $html);
    }

    public function test_a_customer_cannot_open_the_log(): void
    {
        $this->actingAs(User::factory()->customer()->create());

        $this->logComponent()->assertStatus(403);
    }

    /**
     * Some records answer for others — a lesson's history is not much use
     * without what happened to the people on its list, and those events are
     * filed against their own seats.
     */
    public function test_it_folds_in_the_history_of_related_records(): void
    {
        $other = Service::factory()->create(['name' => 'Masáž']);

        Activity::create([
            'log_name' => 'default',
            'description' => 'Masáž',
            'subject_type' => $other->getMorphClass(),
            'subject_id' => $other->getKey(),
            'event' => 'lesson_absence',
            'properties' => ['client' => 'Jana Nováková'],
        ]);

        $withRelated = fn (): Testable => Livewire::test(RecordActivityLog::class, [
            'subjectType' => $this->service->getMorphClass(),
            'subjectId' => (string) $this->service->getKey(),
            'relatedSubjects' => [[
                'type' => $other->getMorphClass(),
                'ids' => [(string) $other->getKey()],
            ]],
        ]);

        $this->logComponent()
            ->assertViewHas('activities', fn (LengthAwarePaginator $activities): bool => $activities->total() === 14);

        // The related service brings its own `created` row along with the
        // absence, so the folded-in log is two entries longer.
        $withRelated()
            ->assertViewHas('activities', fn (LengthAwarePaginator $activities): bool => $activities->total() === 16)
            ->assertViewHas('eventOptions', fn (array $options): bool => array_key_exists('lesson_absence', $options));

        // The filters still narrow the whole set, not just the record's own half.
        $withRelated()
            ->set('event', 'lesson_absence')
            ->assertViewHas('activities', fn (LengthAwarePaginator $activities): bool => $activities->total() === 1);
    }

    public function test_related_subjects_are_ignored_when_malformed(): void
    {
        Livewire::test(RecordActivityLog::class, [
            'subjectType' => $this->service->getMorphClass(),
            'subjectId' => (string) $this->service->getKey(),
            'relatedSubjects' => [['type' => 'App\Models\Nothing', 'ids' => []], []],
        ])
            ->assertSet('relatedSubjects', [])
            ->assertViewHas('activities', fn (LengthAwarePaginator $activities): bool => $activities->total() === 14);
    }
}

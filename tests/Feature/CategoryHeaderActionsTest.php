<?php

namespace Tests\Feature;

use App\Filament\Clusters\Kurzy\Resources\CourseCategories\Pages\EditCourseCategory;
use App\Filament\Clusters\Kurzy\Resources\CourseCategories\Pages\ViewCourseCategory;
use App\Filament\Clusters\Kurzy\Resources\EventCategories\Pages\EditEventCategory;
use App\Filament\Clusters\Kurzy\Resources\EventCategories\Pages\ViewEventCategory;
use App\Models\CourseCategory;
use App\Models\EventCategory;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Both category resources share one header layout: the primary call to action
 * stays a standalone button — Save when editing, Upravit when viewing — while
 * the secondary record actions collapse into a "Další akce" dropdown that
 * always sits last. Grouping them must not break the actions themselves.
 */
class CategoryHeaderActionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');

        $this->actingAs(User::factory()->admin()->create());
    }

    /**
     * @return array<string, array{class-string<Model>, class-string, array<string>}>
     */
    public static function viewPages(): array
    {
        return [
            'event category' => [EventCategory::class, ViewEventCategory::class, ['visit']],
            'course category' => [CourseCategory::class, ViewCourseCategory::class, []],
        ];
    }

    /**
     * @return array<string, array{class-string<Model>, class-string, array<string>}>
     */
    public static function editPages(): array
    {
        return [
            'event category' => [EventCategory::class, EditEventCategory::class, ['visit']],
            'course category' => [CourseCategory::class, EditCourseCategory::class, []],
        ];
    }

    /**
     * The same pages without their resource-specific extra actions, for the
     * tests that only need to reach a record.
     *
     * @return array<string, array{class-string<Model>, class-string}>
     */
    public static function viewPageRecords(): array
    {
        return array_map(fn (array $set): array => [$set[0], $set[1]], static::viewPages());
    }

    /**
     * @return array<string, array{class-string<Model>, class-string}>
     */
    public static function editPageRecords(): array
    {
        return array_map(fn (array $set): array => [$set[0], $set[1]], static::editPages());
    }

    /**
     * @param  class-string<Model>  $model
     * @param  class-string  $page
     * @param  array<string>  $extraActions
     */
    #[DataProvider('viewPages')]
    public function test_view_page_keeps_edit_out_of_the_dropdown(string $model, string $page, array $extraActions): void
    {
        $record = $model::factory()->create();

        $component = Livewire::test($page, ['record' => $record->getKey()])
            ->assertActionExists('edit')
            ->assertActionExists('delete')
            ->assertActionExists('activityLog');

        foreach ($extraActions as $action) {
            $component->assertActionExists($action);
        }
    }

    /**
     * @param  class-string<Model>  $model
     * @param  class-string  $page
     * @param  array<string>  $extraActions
     */
    #[DataProvider('editPages')]
    public function test_edit_page_keeps_save_out_of_the_dropdown(string $model, string $page, array $extraActions): void
    {
        $record = $model::factory()->create();

        $component = Livewire::test($page, ['record' => $record->getKey()])
            ->assertActionExists('saveHeader')
            ->assertActionExists('view')
            ->assertActionExists('delete')
            ->assertActionExists('activityLog');

        foreach ($extraActions as $action) {
            $component->assertActionExists($action);
        }
    }

    /**
     * The header save button is wired straight to the page's `save` method via
     * a string action, so it renders as a Livewire click handler rather than a
     * mounted action. That makes `call('save')` — not `callAction()` — the way
     * to exercise what the button actually triggers.
     *
     * @param  class-string<Model>  $model
     * @param  class-string  $page
     */
    #[DataProvider('editPageRecords')]
    public function test_edit_page_still_saves(string $model, string $page): void
    {
        $record = $model::factory()->create(['name' => 'Původní název']);

        Livewire::test($page, ['record' => $record->getKey()])
            ->fillForm(['name' => 'Nový název'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('Nový název', $record->refresh()->name);
    }

    /**
     * @param  class-string<Model>  $model
     * @param  class-string  $page
     */
    #[DataProvider('viewPageRecords')]
    public function test_grouped_delete_action_still_deletes_the_record(string $model, string $page): void
    {
        $record = $model::factory()->create();

        Livewire::test($page, ['record' => $record->getKey()])
            ->callAction('delete');

        $this->assertDatabaseMissing($record->getTable(), ['id' => $record->getKey()]);
    }
}

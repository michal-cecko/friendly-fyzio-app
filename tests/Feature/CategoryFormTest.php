<?php

namespace Tests\Feature;

use App\Filament\Clusters\Kurzy\Resources\CourseCategories\Pages\CreateCourseCategory;
use App\Filament\Clusters\Kurzy\Resources\CourseCategories\Pages\EditCourseCategory;
use App\Filament\Clusters\Kurzy\Resources\EventCategories\Pages\CreateEventCategory;
use App\Filament\Clusters\Kurzy\Resources\EventCategories\Pages\EditEventCategory;
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
 * Both category forms derive the slug from the name while creating, then freeze
 * it: an existing record's address must survive a rename, and the derived value
 * has to be unique on its own so creating never stalls on a clash.
 */
class CategoryFormTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');

        $this->actingAs(User::factory()->admin()->create());
    }

    /**
     * @return array<string, array{class-string<Model>, class-string, class-string}>
     */
    public static function categories(): array
    {
        return [
            'event category' => [EventCategory::class, CreateEventCategory::class, EditEventCategory::class],
            'course category' => [CourseCategory::class, CreateCourseCategory::class, EditCourseCategory::class],
        ];
    }

    /**
     * The same categories without their edit page, for the tests that only
     * exercise creation.
     *
     * @return array<string, array{class-string<Model>, class-string}>
     */
    public static function createPages(): array
    {
        return array_map(fn (array $set): array => [$set[0], $set[1]], static::categories());
    }

    /**
     * @param  class-string<Model>  $model
     * @param  class-string  $createPage
     */
    #[DataProvider('createPages')]
    public function test_slug_is_derived_from_the_name(string $model, string $createPage): void
    {
        Livewire::test($createPage)
            ->fillForm(['name' => 'Letní semináře'])
            ->assertFormSet(['slug' => 'letni-seminare'])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas($model::query()->getModel()->getTable(), ['slug' => 'letni-seminare']);
    }

    /**
     * A name that would collide resolves to a free slug rather than stalling
     * the form on a unique-rule error.
     *
     * @param  class-string<Model>  $model
     * @param  class-string  $createPage
     */
    #[DataProvider('createPages')]
    public function test_colliding_name_derives_a_unique_slug(string $model, string $createPage): void
    {
        $model::factory()->create(['name' => 'Semináře', 'slug' => 'seminare']);

        Livewire::test($createPage)
            ->fillForm(['name' => 'Semináře'])
            ->assertFormSet(['slug' => 'seminare-2'])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas($model::query()->getModel()->getTable(), ['slug' => 'seminare-2']);
    }

    /**
     * Renaming an existing category must leave its address alone — links
     * already shared keep working.
     *
     * @param  class-string<Model>  $model
     * @param  class-string  $createPage
     * @param  class-string  $editPage
     */
    #[DataProvider('categories')]
    public function test_renaming_does_not_change_an_existing_slug(string $model, string $createPage, string $editPage): void
    {
        $record = $model::factory()->create(['name' => 'Semináře', 'slug' => 'seminare']);

        Livewire::test($editPage, ['record' => $record->getKey()])
            ->fillForm(['name' => 'Workshopy'])
            ->assertFormSet(['slug' => 'seminare'])
            ->call('save')
            ->assertHasNoFormErrors();

        $record->refresh();

        $this->assertSame('Workshopy', $record->name);
        $this->assertSame('seminare', $record->slug);
    }

    /**
     * @param  class-string<Model>  $model
     * @param  class-string  $createPage
     * @param  class-string  $editPage
     */
    #[DataProvider('categories')]
    public function test_slug_is_editable_only_while_creating(string $model, string $createPage, string $editPage): void
    {
        $record = $model::factory()->create(['name' => 'Semináře', 'slug' => 'seminare']);

        Livewire::test($createPage)
            ->assertFormFieldIsEnabled('slug');

        Livewire::test($editPage, ['record' => $record->getKey()])
            ->assertFormFieldIsDisabled('slug');
    }
}

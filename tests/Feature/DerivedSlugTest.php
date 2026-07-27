<?php

namespace Tests\Feature;

use App\Filament\Clusters\Kurzy\Resources\CourseCategories\Pages\CreateCourseCategory;
use App\Filament\Clusters\Kurzy\Resources\CourseCategories\Pages\EditCourseCategory;
use App\Filament\Clusters\Kurzy\Resources\Courses\Pages\CreateCourse;
use App\Filament\Clusters\Kurzy\Resources\Courses\Pages\EditCourse;
use App\Filament\Clusters\Kurzy\Resources\EventCategories\Pages\CreateEventCategory;
use App\Filament\Clusters\Kurzy\Resources\EventCategories\Pages\EditEventCategory;
use App\Filament\Clusters\Kurzy\Resources\Lessons\Pages\CreateLesson;
use App\Filament\Clusters\Kurzy\Resources\Lessons\Pages\EditLesson;
use App\Filament\Clusters\Obsah\Resources\Pages\Pages\CreatePage;
use App\Filament\Clusters\Obsah\Resources\Pages\Pages\EditPage;
use App\Filament\Clusters\Provoz\Resources\ServiceCategories\Pages\CreateServiceCategory;
use App\Filament\Clusters\Provoz\Resources\ServiceCategories\Pages\EditServiceCategory;
use App\Filament\Clusters\Provoz\Resources\Services\Pages\CreateService;
use App\Filament\Clusters\Provoz\Resources\Services\Pages\EditService;
use App\Models\Course;
use App\Models\CourseCategory;
use App\Models\EventCategory;
use App\Models\Lesson;
use App\Models\Page;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Every record that carries a public address derives its slug from a name while
 * being created, then freezes it. Renaming later must not silently move a page
 * out from under links that have already been shared.
 */
class DerivedSlugTest extends TestCase
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
    public static function sluggedResources(): array
    {
        return [
            'event category' => [EventCategory::class, CreateEventCategory::class, EditEventCategory::class],
            'course category' => [CourseCategory::class, CreateCourseCategory::class, EditCourseCategory::class],
            'course' => [Course::class, CreateCourse::class, EditCourse::class],
            'one-off event' => [Lesson::class, CreateLesson::class, EditLesson::class],
            'page' => [Page::class, CreatePage::class, EditPage::class],
            'service category' => [ServiceCategory::class, CreateServiceCategory::class, EditServiceCategory::class],
            'service' => [Service::class, CreateService::class, EditService::class],
        ];
    }

    /**
     * The same resources without their edit page, for the create-only test.
     *
     * @return array<string, array{class-string<Model>, class-string}>
     */
    public static function sluggedCreatePages(): array
    {
        return array_map(fn (array $set): array => [$set[0], $set[1]], static::sluggedResources());
    }

    /**
     * @param  class-string<Model>  $model
     * @param  class-string  $createPage
     */
    #[DataProvider('sluggedCreatePages')]
    public function test_slug_can_be_adjusted_while_creating(string $model, string $createPage): void
    {
        Livewire::test($createPage)
            ->assertFormFieldIsEnabled('slug');
    }

    /**
     * @param  class-string<Model>  $model
     * @param  class-string  $createPage
     * @param  class-string  $editPage
     */
    #[DataProvider('sluggedResources')]
    public function test_slug_is_frozen_once_the_record_exists(string $model, string $createPage, string $editPage): void
    {
        $record = $model::factory()->create();

        Livewire::test($editPage, ['record' => $record->getKey()])
            ->assertFormFieldIsDisabled('slug');
    }

    /**
     * A disabled field is still dehydrated, so saving an untouched edit form
     * must write the stored slug back rather than blanking it.
     *
     * @param  class-string<Model>  $model
     * @param  class-string  $createPage
     * @param  class-string  $editPage
     */
    #[DataProvider('sluggedResources')]
    public function test_saving_an_untouched_edit_form_keeps_the_slug(string $model, string $createPage, string $editPage): void
    {
        $record = $model::factory()->create();
        $slug = $record->slug;

        Livewire::test($editPage, ['record' => $record->getKey()])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame($slug, $record->refresh()->slug);
    }
}

<?php

namespace Tests\Feature;

use App\Filament\Clusters\Kurzy\Resources\CourseCategories\CourseCategoryResource;
use App\Filament\Clusters\Kurzy\Resources\CourseSeries\Pages\ViewCourseSeries;
use App\Filament\Clusters\Kurzy\Resources\CourseSeries\RelationManagers\LessonsRelationManager;
use App\Filament\Clusters\Kurzy\Resources\EventCategories\EventCategoryResource;
use App\Filament\Clusters\Kurzy\Resources\Lessons\LessonResource;
use App\Filament\Clusters\Provoz\Resources\Clients\ClientResource;
use App\Filament\Clusters\Provoz\Resources\Clients\Pages\ListClients;
use App\Filament\Clusters\Provoz\Resources\Users\Pages\ListUsers;
use App\Filament\Clusters\Provoz\Resources\Users\UserResource;
use App\Filament\Support\RecordLinks;
use App\Models\CourseSeries;
use App\Models\Lesson;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Clicking a record leads to its detail page, and links rendered for the whole
 * team never point at a form the viewer may not open.
 */
class RecordDetailLinksTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');
    }

    public function test_clicking_a_team_member_opens_their_detail_page(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $colleague = User::factory()->therapist()->create();

        $table = Livewire::test(ListUsers::class)->instance()->getTable();

        // Even for an admin, who may edit: the row leads to the infolist, and the
        // Edit action next to it is how the form is reached.
        $this->assertSame(
            UserResource::getUrl('view', ['record' => $colleague]),
            $table->getRecordUrl($colleague),
        );
    }

    public function test_clicking_a_client_opens_their_detail_page_even_for_staff_who_may_edit_them(): void
    {
        $this->seedRolesAndPermissions();

        $this->actingAs(User::factory()->therapist()->create());

        $client = User::factory()->customer()->create();

        $table = Livewire::test(ListClients::class)->instance()->getTable();

        $this->assertSame(
            ClientResource::getUrl('view', ['record' => $client]),
            $table->getRecordUrl($client),
        );
    }

    public function test_a_therapist_is_linked_to_a_colleagues_detail_page_not_their_edit_form(): void
    {
        $this->seedRolesAndPermissions();

        $colleague = User::factory()->therapist()->create();

        $this->actingAs(User::factory()->therapist()->create());

        $this->assertSame(
            UserResource::getUrl('view', ['record' => $colleague]),
            RecordLinks::detailUrl(UserResource::class, $colleague),
        );
    }

    public function test_staff_who_may_only_read_lessons_get_no_edit_link_on_them(): void
    {
        $this->seedRolesAndPermissions();

        // Lecturers may edit the lessons they teach; therapists only read them.
        $therapist = User::factory()->therapist()->create();
        $series = CourseSeries::factory()->create();
        $lesson = Lesson::factory()->create(['series_id' => $series->getKey()]);

        $this->actingAs($therapist);

        $this->assertFalse(LessonResource::canEdit($lesson));

        Livewire::test(LessonsRelationManager::class, [
            'ownerRecord' => $series,
            'pageClass' => ViewCourseSeries::class,
        ])
            ->assertActionHidden(TestAction::make('edit')->table($lesson))
            ->assertActionVisible(TestAction::make('detail')->table($lesson));
    }

    /**
     * @return array<string, array{0: class-string<\Filament\Resources\Resource>}>
     */
    public static function categoryResourceProvider(): array
    {
        return [
            'course categories' => [CourseCategoryResource::class],
            'event categories' => [EventCategoryResource::class],
        ];
    }

    /**
     * @param  class-string<\Filament\Resources\Resource>  $resource
     */
    #[DataProvider('categoryResourceProvider')]
    public function test_categories_leave_the_sidebar_below_admin_but_stay_reachable(string $resource): void
    {
        $this->seedRolesAndPermissions();

        $this->actingAs(User::factory()->lecturer()->create());

        $this->assertFalse($resource::shouldRegisterNavigation());

        // Hidden from the sidebar only — links from a course and direct URLs still work.
        $this->get($resource::getUrl('index'))->assertSuccessful();
    }

    /**
     * @param  class-string<\Filament\Resources\Resource>  $resource
     */
    #[DataProvider('categoryResourceProvider')]
    public function test_admins_keep_the_category_sidebar_items(string $resource): void
    {
        $this->seedRolesAndPermissions();

        $this->actingAs(User::factory()->admin()->create());

        $this->assertTrue($resource::shouldRegisterNavigation());
    }

    /**
     * Roles created by the factory carry no permissions of their own — only the
     * seeder wires them up, so any permission-driven assertion needs it first.
     */
    private function seedRolesAndPermissions(): void
    {
        // Through Artisan rather than $this->seed(): the seeder shells out to
        // shield:generate, which needs a real console output.
        $this->artisan('db:seed', ['--class' => RolePermissionSeeder::class])->assertSuccessful();
    }
}

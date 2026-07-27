<?php

namespace Tests\Feature;

use App\Filament\Clusters\Finance\FinanceCluster;
use App\Filament\Clusters\Finance\Resources\Payments\PaymentResource;
use App\Filament\Clusters\Kurzy\KurzyCluster;
use App\Filament\Clusters\Kurzy\Resources\Courses\CourseResource;
use App\Filament\Clusters\Kurzy\Resources\Lessons\LessonResource;
use App\Filament\Clusters\Obsah\ObsahCluster;
use App\Filament\Clusters\Obsah\Pages\MediaLibrary;
use App\Filament\Clusters\Provoz\Resources\Clients\Pages\ViewClient;
use App\Filament\Clusters\Provoz\Resources\Clients\RelationManagers\CourseEnrollmentsRelationManager;
use App\Filament\Support\PayableLinks;
use App\Models\CourseEnrollment;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Filament\Navigation\NavigationItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The sidebar an administrator sees is not the one a therapist or a lecturer
 * needs: Obsah is closed to them entirely, Finance narrows to Platby, and the
 * Kurzy cluster unwraps into the two items a lecturer actually opens.
 */
class StaffSidebarTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Therapists and lecturers reach the panel through role permissions, so
        // what they see only means anything once those rows exist.
        $this->artisan('db:seed', ['--class' => RolePermissionSeeder::class])->assertSuccessful();
    }

    /**
     * @return array<string, string> label => URL
     */
    private function sidebarFor(User $user): array
    {
        Filament::setCurrentPanel('admin');

        $this->actingAs($user);

        $items = [];

        foreach (Filament::getNavigation() as $group) {
            foreach ($group->getItems() as $item) {
                /** @var NavigationItem $item */
                $items[$item->getLabel()] = $item->getUrl();
            }
        }

        return $items;
    }

    public function test_an_administrator_keeps_the_full_clustered_sidebar(): void
    {
        $sidebar = $this->sidebarFor(User::factory()->admin()->create());

        $this->assertContains(ObsahCluster::getUrl(), $sidebar);
        $this->assertContains(FinanceCluster::getUrl(), $sidebar);
        $this->assertContains(KurzyCluster::getUrl(), $sidebar);

        // Clustered resources stay inside their cluster for an administrator.
        $this->assertNotContains(PaymentResource::getUrl(), $sidebar);
        $this->assertNotContains(CourseResource::getUrl(), $sidebar);
        $this->assertNotContains(LessonResource::getUrl(), $sidebar);
    }

    public function test_a_therapist_gets_payments_instead_of_finance_and_no_obsah(): void
    {
        $sidebar = $this->sidebarFor(User::factory()->therapist()->create());

        $this->assertSame(PaymentResource::getUrl(), $sidebar['Platby'] ?? null);
        $this->assertNotContains(FinanceCluster::getUrl(), $sidebar);
        $this->assertNotContains(ObsahCluster::getUrl(), $sidebar);
        $this->assertArrayNotHasKey('Obsah', $sidebar);
    }

    public function test_a_therapist_who_does_not_teach_has_no_courses_at_all(): void
    {
        $sidebar = $this->sidebarFor(User::factory()->therapist()->create());

        $this->assertNotContains(KurzyCluster::getUrl(), $sidebar);
        $this->assertNotContains(CourseResource::getUrl(), $sidebar);
        $this->assertNotContains(LessonResource::getUrl(), $sidebar);

        $this->assertFalse(CourseResource::canAccess());
        $this->assertFalse(LessonResource::canAccess());
        $this->assertFalse(KurzyCluster::canAccess());
    }

    public function test_a_lecturer_gets_courses_and_lessons_outside_the_cluster(): void
    {
        $sidebar = $this->sidebarFor(User::factory()->lecturer()->create());

        $this->assertSame(CourseResource::getUrl(), $sidebar['Kurzy'] ?? null);
        $this->assertSame(LessonResource::getUrl(), $sidebar['Lekce'] ?? null);
        $this->assertNotContains(KurzyCluster::getUrl(), $sidebar);

        $this->assertTrue(CourseResource::canAccess());
        $this->assertTrue(LessonResource::canAccess());
    }

    public function test_the_promoted_items_keep_the_running_order_of_the_clusters_they_left(): void
    {
        $lecturer = User::factory()->lecturer()->create();

        $this->assertSame(FinanceCluster::getNavigationSort(), PaymentResource::getEscapedNavigationSort());
        $this->assertSame(KurzyCluster::getNavigationSort(), CourseResource::getEscapedNavigationSort());
        $this->assertSame(
            KurzyCluster::getNavigationSort() + 1,
            LessonResource::getEscapedNavigationSort(),
        );

        $labels = array_keys($this->sidebarFor($lecturer));

        $this->assertLessThan(array_search('Lekce', $labels), array_search('Kurzy', $labels));
    }

    public function test_the_media_library_is_closed_to_staff_scoped_to_their_own_work(): void
    {
        $therapist = User::factory()->therapist()->create();

        Filament::setCurrentPanel('admin');
        $this->actingAs($therapist);

        $this->assertFalse(MediaLibrary::canAccess());
        $this->assertFalse(ObsahCluster::canAccess());

        $this->get(MediaLibrary::getUrl())->assertForbidden();
    }

    public function test_the_pages_behind_the_narrowed_sidebar_open_and_close_as_advertised(): void
    {
        Filament::setCurrentPanel('admin');

        $this->actingAs(User::factory()->therapist()->create());
        $this->get(PaymentResource::getUrl('index'))->assertSuccessful();
        $this->get(CourseResource::getUrl('index'))->assertForbidden();
        $this->get(LessonResource::getUrl('index'))->assertForbidden();
        $this->get(KurzyCluster::getUrl())->assertForbidden();

        $this->actingAs(User::factory()->lecturer()->create());
        $this->get(CourseResource::getUrl('index'))->assertSuccessful();
        $this->get(LessonResource::getUrl('index'))->assertSuccessful();
    }

    public function test_a_client_shows_no_courses_tab_to_a_therapist_who_does_not_teach(): void
    {
        $client = User::factory()->customer()->create();

        Filament::setCurrentPanel('admin');

        $this->actingAs(User::factory()->therapist()->create());
        $this->assertFalse(CourseEnrollmentsRelationManager::canViewForRecord($client, ViewClient::class));

        $this->actingAs(User::factory()->lecturer()->create());
        $this->assertTrue(CourseEnrollmentsRelationManager::canViewForRecord($client, ViewClient::class));
    }

    public function test_a_payment_does_not_link_to_an_enrollment_a_therapist_may_not_open(): void
    {
        $enrollment = CourseEnrollment::factory()->create();

        Filament::setCurrentPanel('admin');

        $this->actingAs(User::factory()->therapist()->create());
        $this->assertNull(PayableLinks::url($enrollment));

        $this->actingAs(User::factory()->lecturer()->create());
        $this->assertNotNull(PayableLinks::url($enrollment));
    }

    public function test_an_admin_who_also_teaches_is_never_narrowed(): void
    {
        $sidebar = $this->sidebarFor(User::factory()->admin()->lecturer()->create());

        $this->assertContains(ObsahCluster::getUrl(), $sidebar);
        $this->assertContains(FinanceCluster::getUrl(), $sidebar);
        $this->assertContains(KurzyCluster::getUrl(), $sidebar);
    }
}

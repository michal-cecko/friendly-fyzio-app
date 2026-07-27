<?php

namespace Tests\Feature;

use App\Enums\CourseEnrollmentStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Filament\Clusters\Kurzy\Resources\CourseEnrollments\CourseEnrollmentResource;
use App\Filament\Clusters\Kurzy\Resources\CourseEnrollments\Pages\CreateCourseEnrollment;
use App\Filament\Clusters\Kurzy\Resources\CourseEnrollments\Pages\ListCourseEnrollments;
use App\Filament\Clusters\Kurzy\Resources\CourseEnrollments\Pages\ViewCourseEnrollment;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\CourseSeries;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CourseEnrollmentResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');

        $this->actingAs(User::factory()->admin()->create());
    }

    public function test_admin_can_list_records(): void
    {
        $records = CourseEnrollment::factory()->count(3)->create();

        Livewire::test(ListCourseEnrollments::class)
            ->assertCanSeeTableRecords($records);
    }

    public function test_admin_can_create_record(): void
    {
        $series = CourseSeries::factory()->create();
        $client = User::factory()->customer()->create();

        Livewire::test(CreateCourseEnrollment::class)
            ->fillForm([
                'series_id' => $series->id,
                'client_id' => $client->id,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        // Status defaults to Active and the payment fields are derived, so they
        // are never part of the create form.
        $this->assertDatabaseHas(CourseEnrollment::class, [
            'series_id' => $series->id,
            'client_id' => $client->id,
            'status' => CourseEnrollmentStatus::Active->value,
            'payment_status' => PaymentStatus::Unpaid->value,
        ]);
    }

    public function test_create_validates_required_fields(): void
    {
        Livewire::test(CreateCourseEnrollment::class)
            ->fillForm([
                'series_id' => null,
                'client_id' => null,
            ])
            ->call('create')
            ->assertHasFormErrors([
                'series_id' => 'required',
                'client_id' => 'required',
            ]);
    }

    public function test_enrollment_is_not_editable(): void
    {
        $this->assertFalse(CourseEnrollmentResource::hasPage('edit'));
    }

    public function test_payment_status_and_paid_at_are_derived_from_payments(): void
    {
        $series = CourseSeries::factory()->create(['price' => 1500]);
        $enrollment = CourseEnrollment::factory()->for($series, 'series')->create([
            'status' => CourseEnrollmentStatus::Active,
            'payment_status' => PaymentStatus::Unpaid,
            'paid_at' => null,
        ]);

        // A received payment covering the price flips the enrollment to Paid and
        // stamps paid_at — without anyone touching those columns by hand.
        $payment = $enrollment->payments()->create([
            'client_id' => $enrollment->client_id,
            'amount' => 1500,
            'method' => PaymentMethod::Cash,
            'status' => PaymentStatus::Paid,
            'paid_at' => now(),
        ]);

        $enrollment->refresh();
        $this->assertSame(PaymentStatus::Paid, $enrollment->payment_status);
        $this->assertNotNull($enrollment->paid_at);

        // Removing the covering payment reverts the derived cache.
        $payment->delete();

        $enrollment->refresh();
        $this->assertSame(PaymentStatus::Unpaid, $enrollment->payment_status);
        $this->assertNull($enrollment->paid_at);
    }

    /**
     * An enrollment is reached through its course and série, so the trail retraces
     * that path instead of stopping at the flat enrollment list.
     */
    public function test_enrollment_breadcrumbs_lead_through_its_course_and_series(): void
    {
        $course = Course::factory()->create(['name' => 'Zdravá záda']);
        $series = CourseSeries::factory()->for($course, 'course')->create(['name' => 'Podzim 2026']);
        $record = CourseEnrollment::factory()->for($series, 'series')->create();

        $breadcrumbs = Livewire::test(ViewCourseEnrollment::class, ['record' => $record->getKey()])
            ->instance()
            ->getBreadcrumbs();

        $this->assertContains('Zdravá záda', $breadcrumbs);
        $this->assertContains('Podzim 2026', $breadcrumbs);
        $this->assertNotContains(CourseEnrollmentResource::getUrl(), array_keys($breadcrumbs));

        $courseIndex = array_search('Zdravá záda', array_values($breadcrumbs), strict: true);
        $seriesIndex = array_search('Podzim 2026', array_values($breadcrumbs), strict: true);
        $this->assertLessThan($seriesIndex, $courseIndex, 'The course has to precede its série.');
    }
}

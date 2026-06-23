<?php

namespace Tests\Feature;

use App\Enums\CourseEnrollmentStatus;
use App\Enums\PaymentStatus;
use App\Filament\Clusters\Kurzy\Resources\CourseEnrollments\Pages\CreateCourseEnrollment;
use App\Filament\Clusters\Kurzy\Resources\CourseEnrollments\Pages\EditCourseEnrollment;
use App\Filament\Clusters\Kurzy\Resources\CourseEnrollments\Pages\ListCourseEnrollments;
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
                'status' => CourseEnrollmentStatus::Active->value,
                'payment_status' => PaymentStatus::Unpaid->value,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas(CourseEnrollment::class, [
            'series_id' => $series->id,
            'client_id' => $client->id,
            'status' => CourseEnrollmentStatus::Active->value,
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

    public function test_admin_can_edit_record(): void
    {
        $record = CourseEnrollment::factory()->create();

        Livewire::test(EditCourseEnrollment::class, ['record' => $record->getKey()])
            ->fillForm(['payment_status' => PaymentStatus::Paid->value])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas(CourseEnrollment::class, [
            'id' => $record->id,
            'payment_status' => PaymentStatus::Paid->value,
        ]);
    }
}

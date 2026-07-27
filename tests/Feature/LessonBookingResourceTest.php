<?php

namespace Tests\Feature;

use App\Enums\PaymentStatus;
use App\Filament\Clusters\Kurzy\Resources\LessonBookings\Pages\CreateLessonBooking;
use App\Filament\Clusters\Kurzy\Resources\LessonBookings\Pages\EditLessonBooking;
use App\Filament\Clusters\Kurzy\Resources\LessonBookings\Pages\ListLessonBookings;
use App\Models\Lesson;
use App\Models\LessonBooking;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class LessonBookingResourceTest extends TestCase
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
        $records = LessonBooking::factory()->count(3)->create();

        Livewire::test(ListLessonBookings::class)
            ->assertCanSeeTableRecords($records);
    }

    public function test_admin_can_create_record(): void
    {
        $event = Lesson::factory()->create();
        $client = User::factory()->customer()->create();

        Livewire::test(CreateLessonBooking::class)
            ->fillForm([
                'lesson_id' => $event->id,
                'client_id' => $client->id,
                'status' => 'confirmed',
                'payment_status' => PaymentStatus::Unpaid->value,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas(LessonBooking::class, [
            'lesson_id' => $event->id,
            'client_id' => $client->id,
            'status' => 'confirmed',
        ]);
    }

    public function test_create_validates_required_fields(): void
    {
        Livewire::test(CreateLessonBooking::class)
            ->fillForm([
                'lesson_id' => null,
                'client_id' => null,
            ])
            ->call('create')
            ->assertHasFormErrors([
                'lesson_id' => 'required',
                'client_id' => 'required',
            ]);
    }

    public function test_admin_can_edit_record(): void
    {
        $record = LessonBooking::factory()->create();

        Livewire::test(EditLessonBooking::class, ['record' => $record->getKey()])
            ->fillForm(['payment_status' => PaymentStatus::Paid->value])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas(LessonBooking::class, [
            'id' => $record->id,
            'payment_status' => PaymentStatus::Paid->value,
        ]);
    }
}

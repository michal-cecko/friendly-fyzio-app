<?php

namespace Tests\Feature;

use App\Enums\PaymentStatus;
use App\Filament\Clusters\LekceWorkshopy\Resources\OneTimeLessonBookings\Pages\CreateOneTimeLessonBooking;
use App\Filament\Clusters\LekceWorkshopy\Resources\OneTimeLessonBookings\Pages\EditOneTimeLessonBooking;
use App\Filament\Clusters\LekceWorkshopy\Resources\OneTimeLessonBookings\Pages\ListOneTimeLessonBookings;
use App\Models\OneTimeLesson;
use App\Models\OneTimeLessonBooking;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class OneTimeLessonBookingResourceTest extends TestCase
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
        $records = OneTimeLessonBooking::factory()->count(3)->create();

        Livewire::test(ListOneTimeLessonBookings::class)
            ->assertCanSeeTableRecords($records);
    }

    public function test_admin_can_create_record(): void
    {
        $lesson = OneTimeLesson::factory()->create();
        $client = User::factory()->customer()->create();

        Livewire::test(CreateOneTimeLessonBooking::class)
            ->fillForm([
                'lesson_id' => $lesson->id,
                'client_id' => $client->id,
                'status' => 'confirmed',
                'payment_status' => PaymentStatus::Unpaid->value,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas(OneTimeLessonBooking::class, [
            'lesson_id' => $lesson->id,
            'client_id' => $client->id,
            'status' => 'confirmed',
        ]);
    }

    public function test_create_validates_required_fields(): void
    {
        Livewire::test(CreateOneTimeLessonBooking::class)
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
        $record = OneTimeLessonBooking::factory()->create();

        Livewire::test(EditOneTimeLessonBooking::class, ['record' => $record->getKey()])
            ->fillForm(['payment_status' => PaymentStatus::Paid->value])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas(OneTimeLessonBooking::class, [
            'id' => $record->id,
            'payment_status' => PaymentStatus::Paid->value,
        ]);
    }
}

<?php

namespace Tests\Feature;

use App\Enums\PaymentStatus;
use App\Filament\Resources\WorkshopRegistrations\Pages\CreateWorkshopRegistration;
use App\Filament\Resources\WorkshopRegistrations\Pages\EditWorkshopRegistration;
use App\Filament\Resources\WorkshopRegistrations\Pages\ListWorkshopRegistrations;
use App\Models\User;
use App\Models\Workshop;
use App\Models\WorkshopRegistration;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class WorkshopRegistrationResourceTest extends TestCase
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
        $records = WorkshopRegistration::factory()->count(3)->create();

        Livewire::test(ListWorkshopRegistrations::class)
            ->assertCanSeeTableRecords($records);
    }

    public function test_admin_can_create_record(): void
    {
        $workshop = Workshop::factory()->create();
        $client = User::factory()->customer()->create();

        Livewire::test(CreateWorkshopRegistration::class)
            ->fillForm([
                'workshop_id' => $workshop->id,
                'client_id' => $client->id,
                'status' => 'confirmed',
                'payment_status' => PaymentStatus::Unpaid->value,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas(WorkshopRegistration::class, [
            'workshop_id' => $workshop->id,
            'client_id' => $client->id,
            'status' => 'confirmed',
        ]);
    }

    public function test_create_validates_required_fields(): void
    {
        Livewire::test(CreateWorkshopRegistration::class)
            ->fillForm([
                'workshop_id' => null,
                'client_id' => null,
            ])
            ->call('create')
            ->assertHasFormErrors([
                'workshop_id' => 'required',
                'client_id' => 'required',
            ]);
    }

    public function test_admin_can_edit_record(): void
    {
        $record = WorkshopRegistration::factory()->create();

        Livewire::test(EditWorkshopRegistration::class, ['record' => $record->getKey()])
            ->fillForm(['payment_status' => PaymentStatus::Paid->value])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas(WorkshopRegistration::class, [
            'id' => $record->id,
            'payment_status' => PaymentStatus::Paid->value,
        ]);
    }
}

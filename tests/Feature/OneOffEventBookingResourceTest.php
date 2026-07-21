<?php

namespace Tests\Feature;

use App\Enums\PaymentStatus;
use App\Filament\Clusters\Kurzy\Resources\OneOffEventBookings\Pages\CreateOneOffEventBooking;
use App\Filament\Clusters\Kurzy\Resources\OneOffEventBookings\Pages\EditOneOffEventBooking;
use App\Filament\Clusters\Kurzy\Resources\OneOffEventBookings\Pages\ListOneOffEventBookings;
use App\Models\OneOffEvent;
use App\Models\OneOffEventBooking;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class OneOffEventBookingResourceTest extends TestCase
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
        $records = OneOffEventBooking::factory()->count(3)->create();

        Livewire::test(ListOneOffEventBookings::class)
            ->assertCanSeeTableRecords($records);
    }

    public function test_admin_can_create_record(): void
    {
        $event = OneOffEvent::factory()->create();
        $client = User::factory()->customer()->create();

        Livewire::test(CreateOneOffEventBooking::class)
            ->fillForm([
                'one_off_event_id' => $event->id,
                'client_id' => $client->id,
                'status' => 'confirmed',
                'payment_status' => PaymentStatus::Unpaid->value,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas(OneOffEventBooking::class, [
            'one_off_event_id' => $event->id,
            'client_id' => $client->id,
            'status' => 'confirmed',
        ]);
    }

    public function test_create_validates_required_fields(): void
    {
        Livewire::test(CreateOneOffEventBooking::class)
            ->fillForm([
                'one_off_event_id' => null,
                'client_id' => null,
            ])
            ->call('create')
            ->assertHasFormErrors([
                'one_off_event_id' => 'required',
                'client_id' => 'required',
            ]);
    }

    public function test_admin_can_edit_record(): void
    {
        $record = OneOffEventBooking::factory()->create();

        Livewire::test(EditOneOffEventBooking::class, ['record' => $record->getKey()])
            ->fillForm(['payment_status' => PaymentStatus::Paid->value])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas(OneOffEventBooking::class, [
            'id' => $record->id,
            'payment_status' => PaymentStatus::Paid->value,
        ]);
    }
}

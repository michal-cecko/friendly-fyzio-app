<?php

namespace Tests\Feature\Clients;

use App\Enums\CreditTransactionType;
use App\Enums\ReservationStatus;
use App\Filament\Clusters\Finance\Resources\Invoices\Actions\DownloadInvoicePdfAction;
use App\Filament\Clusters\Finance\Resources\Invoices\Actions\MarkInvoicePaidAction;
use App\Filament\Clusters\Finance\Resources\Invoices\Actions\SendInvoiceAction;
use App\Filament\Clusters\Kurzy\Resources\CourseEnrollments\CourseEnrollmentResource;
use App\Filament\Clusters\Provoz\Resources\Clients\Actions\AdjustCreditAction;
use App\Filament\Clusters\Provoz\Resources\Clients\Pages\ViewClient;
use App\Filament\Clusters\Provoz\Resources\Clients\RelationManagers\CourseEnrollmentsRelationManager;
use App\Filament\Clusters\Provoz\Resources\Clients\RelationManagers\CreditTransactionsRelationManager;
use App\Filament\Clusters\Provoz\Resources\Clients\RelationManagers\InvoicesRelationManager;
use App\Filament\Clusters\Provoz\Resources\Clients\RelationManagers\ReservationsRelationManager;
use App\Filament\Clusters\Provoz\Resources\Clients\RelationManagers\SubstituteTokensRelationManager;
use App\Filament\Clusters\Provoz\Resources\Reservations\Actions\CreateReservationAction;
use App\Filament\Clusters\Provoz\Resources\Reservations\ReservationResource;
use App\Filament\Support\RelationManagers\PaymentsRelationManager;
use App\Models\CourseEnrollment;
use App\Models\CourseLesson;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\Service;
use App\Models\StaffProfile;
use App\Models\SubstituteToken;
use App\Models\User;
use App\Support\Credits\CreditLedger;
use Filament\Actions\EditAction;
use Filament\Actions\Testing\TestAction;
use Filament\Actions\ViewAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ClientRelationManagersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');

        $this->actingAs(User::factory()->admin()->create());
    }

    public function test_payments_tab_shows_the_clients_payments_only(): void
    {
        $client = User::factory()->customer()->create();
        $mine = Payment::factory()->create(['client_id' => $client->getKey()]);
        $foreign = Payment::factory()->create();

        Livewire::test(PaymentsRelationManager::class, [
            'ownerRecord' => $client,
            'pageClass' => ViewClient::class,
        ])
            ->assertCanSeeTableRecords([$mine])
            ->assertCanNotSeeTableRecords([$foreign]);
    }

    public function test_invoices_tab_shows_the_clients_invoices_only(): void
    {
        $client = User::factory()->customer()->create();
        $mine = Invoice::factory()->create(['client_id' => $client->getKey()]);
        $foreign = Invoice::factory()->create();

        Livewire::test(InvoicesRelationManager::class, [
            'ownerRecord' => $client,
            'pageClass' => ViewClient::class,
        ])
            ->assertCanSeeTableRecords([$mine])
            ->assertCanNotSeeTableRecords([$foreign]);
    }

    public function test_substitute_tokens_tab_shows_the_clients_tokens_only(): void
    {
        $client = User::factory()->customer()->create();
        $lesson = CourseLesson::factory()->create();

        $mine = SubstituteToken::factory()->create([
            'client_id' => $client->getKey(),
            'source_lesson_id' => $lesson->getKey(),
            'expires_at' => now()->addDays(30),
        ]);
        $foreign = SubstituteToken::factory()->create([
            'client_id' => User::factory()->customer()->create()->getKey(),
            'source_lesson_id' => $lesson->getKey(),
            'expires_at' => now()->addDays(30),
        ]);

        Livewire::test(SubstituteTokensRelationManager::class, [
            'ownerRecord' => $client,
            'pageClass' => ViewClient::class,
        ])
            ->assertCanSeeTableRecords([$mine])
            ->assertCanNotSeeTableRecords([$foreign]);
    }

    public function test_reservation_rows_link_to_the_reservation_detail(): void
    {
        $client = User::factory()->customer()->create();
        $reservation = Reservation::factory()->create([
            'client_id' => $client->getKey(),
            'status' => ReservationStatus::Cancelled,
        ]);

        Livewire::test(ReservationsRelationManager::class, [
            'ownerRecord' => $client,
            'pageClass' => ViewClient::class,
        ])
            ->assertActionVisible(TestAction::make(ViewAction::class)->table($reservation))
            ->assertActionVisible(TestAction::make(EditAction::class)->table($reservation))
            // Proves the "Další akce" group from the Rezervace list is wired up here too.
            ->assertActionVisible(TestAction::make('restoreReservation')->table($reservation))
            ->assertSeeHtml(ReservationResource::getUrl('view', ['record' => $reservation]));
    }

    public function test_reservations_can_be_searched(): void
    {
        $client = User::factory()->customer()->create();

        $massage = Reservation::factory()->create([
            'client_id' => $client->getKey(),
            'service_id' => Service::factory()->create(['name' => 'Klasická masáž'])->getKey(),
        ]);
        $physio = Reservation::factory()->create([
            'client_id' => $client->getKey(),
            'service_id' => Service::factory()->create(['name' => 'Vstupní fyzioterapie'])->getKey(),
        ]);

        Livewire::test(ReservationsRelationManager::class, [
            'ownerRecord' => $client,
            'pageClass' => ViewClient::class,
        ])
            ->searchTable('Klasická')
            ->assertCanSeeTableRecords([$massage])
            ->assertCanNotSeeTableRecords([$physio]);
    }

    public function test_a_reservation_can_be_created_for_the_owning_client(): void
    {
        $client = User::factory()->customer()->create();
        $service = Service::factory()->create();
        $therapist = StaffProfile::factory()->create();
        $room = Room::factory()->create();

        Livewire::test(ReservationsRelationManager::class, [
            'ownerRecord' => $client,
            'pageClass' => ViewClient::class,
        ])
            ->callAction(TestAction::make(CreateReservationAction::class)->table(), [
                'reservation_date' => now()->addWeek()->format('Y-m-d'),
                'start_time' => '09:00',
                'end_time' => '10:00',
                'service_id' => $service->getKey(),
                'therapist_id' => $therapist->getKey(),
                'room_id' => $room->getKey(),
            ])
            ->assertHasNoActionErrors();

        $this->assertDatabaseHas(Reservation::class, [
            'client_id' => $client->getKey(),
            'service_id' => $service->getKey(),
            'room_id' => $room->getKey(),
        ]);
    }

    public function test_the_client_page_header_books_a_reservation_in_a_modal(): void
    {
        $client = User::factory()->customer()->create();
        $service = Service::factory()->create();
        $therapist = StaffProfile::factory()->create();
        $room = Room::factory()->create();

        Livewire::test(ViewClient::class, ['record' => $client->getKey()])
            ->callAction(TestAction::make(CreateReservationAction::class), [
                'reservation_date' => now()->addWeek()->format('Y-m-d'),
                'start_time' => '11:00',
                'end_time' => '12:00',
                'service_id' => $service->getKey(),
                'therapist_id' => $therapist->getKey(),
                'room_id' => $room->getKey(),
            ])
            ->assertHasNoActionErrors()
            ->assertDispatched(CreateReservationAction::CREATED);

        $this->assertDatabaseHas(Reservation::class, [
            'client_id' => $client->getKey(),
            'service_id' => $service->getKey(),
            'start_time' => '11:00',
        ]);
    }

    public function test_course_rows_link_to_the_enrollment_detail(): void
    {
        $client = User::factory()->customer()->create();
        $enrollment = CourseEnrollment::factory()->create(['client_id' => $client->getKey()]);

        Livewire::test(CourseEnrollmentsRelationManager::class, [
            'ownerRecord' => $client,
            'pageClass' => ViewClient::class,
        ])
            ->assertCanSeeTableRecords([$enrollment])
            ->assertSeeHtml(CourseEnrollmentResource::getUrl('view', ['record' => $enrollment]));
    }

    public function test_invoice_rows_offer_the_finance_servicing_actions(): void
    {
        $client = User::factory()->customer()->create();
        $invoice = Invoice::factory()->create(['client_id' => $client->getKey()]);

        Livewire::test(InvoicesRelationManager::class, [
            'ownerRecord' => $client,
            'pageClass' => ViewClient::class,
        ])
            ->assertActionVisible(TestAction::make(DownloadInvoicePdfAction::class)->table($invoice))
            ->assertActionVisible(TestAction::make(SendInvoiceAction::class)->table($invoice))
            ->assertActionVisible(TestAction::make(MarkInvoicePaidAction::class)->table($invoice));
    }

    public function test_credit_tab_tops_up_the_balance_and_summarises_it(): void
    {
        $client = User::factory()->customer()->create();

        CreditLedger::record($client, 500, CreditTransactionType::TopUp, 'Dárkový poukaz');
        CreditLedger::record($client, -200, CreditTransactionType::Deduction, 'Čerpáno');

        Livewire::test(CreditTransactionsRelationManager::class, [
            'ownerRecord' => $client,
            'pageClass' => ViewClient::class,
        ])
            // The footer shows the ledger balance (300), not the sum of the rows.
            ->assertSee('300 Kč')
            ->callAction(TestAction::make(AdjustCreditAction::class)->table(), [
                'direction' => 'add',
                'amount' => 250,
                'description' => 'Dobití na pobočce',
            ])
            ->assertHasNoActionErrors();

        $this->assertSame(550, CreditLedger::balanceFor($client->refresh()));
    }
}

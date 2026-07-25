<?php

namespace Tests\Feature;

use App\Filament\Clusters\Finance\Resources\CashReceipts\CashReceiptResource;
use App\Filament\Clusters\Finance\Resources\Invoices\InvoiceResource;
use App\Filament\Clusters\Finance\Resources\InvoiceSeries\InvoiceSeriesResource;
use App\Filament\Clusters\Finance\Resources\Payments\PaymentResource;
use App\Filament\Clusters\Kurzy\Resources\CourseEnrollments\CourseEnrollmentResource;
use App\Filament\Clusters\Kurzy\Resources\CourseEnrollments\Pages\ListCourseEnrollments;
use App\Filament\Clusters\Kurzy\Resources\Courses\Pages\ViewCourse;
use App\Filament\Clusters\Kurzy\Resources\Courses\RelationManagers\SeriesRelationManager;
use App\Filament\Clusters\Kurzy\Resources\CourseSeries\Pages\ViewCourseSeries;
use App\Filament\Clusters\Kurzy\Resources\CourseSeries\RelationManagers\LessonsRelationManager;
use App\Filament\Clusters\Kurzy\Resources\CourseSeries\RelationManagers\SubstituteRulesRelationManager;
use App\Filament\Clusters\Kurzy\Resources\LessonAttendances\LessonAttendanceResource;
use App\Filament\Clusters\Kurzy\Resources\OneOffEventBookings\OneOffEventBookingResource;
use App\Filament\Clusters\Provoz\Resources\Buildings\Pages\ViewBuilding;
use App\Filament\Clusters\Provoz\Resources\Buildings\RelationManagers\RoomsRelationManager;
use App\Filament\Clusters\Provoz\Resources\Clients\Pages\ViewClient;
use App\Filament\Clusters\Provoz\Resources\Clients\RelationManagers\NotesRelationManager;
use App\Filament\Clusters\Provoz\Resources\Reservations\Pages\ViewReservation;
use App\Filament\Clusters\Provoz\Resources\Reservations\ReservationResource;
use App\Models\Building;
use App\Models\CashReceipt;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\CourseSeries;
use App\Models\Invoice;
use App\Models\InvoiceSeries;
use App\Models\LessonAttendance;
use App\Models\OneOffEventBooking;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use RalphJSmit\Filament\MediaLibrary\Filament\Forms\Components\MediaPicker;
use Tests\TestCase;

/**
 * Guards the Czech naming of admin modals and table empty states. Filament falls
 * back to the humanised model class name ("client note") whenever a table has no
 * model label, which leaks English into headings such as "Vytvořit Client Note".
 */
class AdminModalLabelsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');

        $this->actingAs(User::factory()->admin()->create());
    }

    public function test_client_notes_relation_manager_is_named_in_czech(): void
    {
        $client = User::factory()->customer()->create();

        $table = $this->relationManagerTable(NotesRelationManager::class, $client, ViewClient::class);

        $this->assertSame('Zatím žádné poznámky', $table->getEmptyStateHeading());
        $this->assertSame('Přidejte první poznámku z terapie.', $table->getEmptyStateDescription());
        $this->assertSame('Nová poznámka', $table->getAction('create')->getModalHeading());
        $this->assertSame('Smazat poznámku', $table->getAction('delete')->getModalHeading());
        $this->assertSame('Upravit poznámku', $table->getAction('edit')->getModalHeading());
    }

    public function test_reservation_notes_relation_manager_is_named_in_czech(): void
    {
        $reservation = Reservation::factory()->create();

        $table = $this->relationManagerTable(
            \App\Filament\Clusters\Provoz\Resources\Reservations\RelationManagers\NotesRelationManager::class,
            $reservation,
            ViewReservation::class,
        );

        $this->assertSame('Nová poznámka', $table->getAction('create')->getModalHeading());
        $this->assertSame('Smazat poznámku', $table->getAction('delete')->getModalHeading());
    }

    public function test_rooms_relation_manager_is_named_in_czech(): void
    {
        $building = Building::factory()->create();

        $table = $this->relationManagerTable(RoomsRelationManager::class, $building, ViewBuilding::class);

        $this->assertSame('Zatím žádné místnosti', $table->getEmptyStateHeading());
        $this->assertSame('Přidejte první místnost této budovy.', $table->getEmptyStateDescription());
        $this->assertSame('Nová místnost', $table->getAction('create')->getModalHeading());
    }

    public function test_course_series_relation_managers_are_named_in_czech(): void
    {
        $course = Course::factory()->create();

        $table = $this->relationManagerTable(SeriesRelationManager::class, $course, ViewCourse::class);

        $this->assertSame('Přidat sérii', $table->getAction('create')->getModalHeading());
        $this->assertSame('Smazat sérii', $table->getAction('delete')->getModalHeading());
        $this->assertSame('Přidejte sérii kurzu s termínem, cenou a kapacitou.', $table->getEmptyStateDescription());

        $series = CourseSeries::factory()->create();

        $lessons = $this->relationManagerTable(LessonsRelationManager::class, $series, ViewCourseSeries::class);
        $this->assertSame('Smazat lekci', $lessons->getAction('delete')->getModalHeading());

        $substitutes = $this->relationManagerTable(SubstituteRulesRelationManager::class, $series, ViewCourseSeries::class);
        $this->assertSame('Smazat náhradní sérii', $substitutes->getAction('delete')->getModalHeading());
    }

    public function test_record_titles_read_as_the_object_of_modal_headings(): void
    {
        $invoice = Invoice::factory()->create(['invoice_number' => '2026-0001']);
        $this->assertSame('fakturu 2026-0001', InvoiceResource::getRecordTitle($invoice));

        $receipt = CashReceipt::factory()->create(['receipt_number' => 'P-0001']);
        $this->assertSame('pokladní doklad P-0001', CashReceiptResource::getRecordTitle($receipt));

        $series = InvoiceSeries::factory()->create(['name' => 'Faktury']);
        $this->assertSame('číselnou řadu Faktury', InvoiceSeriesResource::getRecordTitle($series));

        $payment = Payment::factory()->create();
        $this->assertSame('platbu č. '.$payment->number, PaymentResource::getRecordTitle($payment));

        $client = User::factory()->customer()->create(['name' => 'Jan Novák']);

        $reservation = Reservation::factory()->create(['client_id' => $client->getKey()]);
        $this->assertSame('rezervaci Jan Novák', ReservationResource::getRecordTitle($reservation));

        $enrollment = CourseEnrollment::factory()->create(['client_id' => $client->getKey()]);
        $this->assertSame('přihlášku Jan Novák', CourseEnrollmentResource::getRecordTitle($enrollment));

        $booking = OneOffEventBooking::factory()->create(['client_id' => $client->getKey()]);
        $this->assertSame('přihlášku Jan Novák', OneOffEventBookingResource::getRecordTitle($booking));

        $attendance = LessonAttendance::factory()->create([
            'enrollment_id' => CourseEnrollment::factory()->create(['client_id' => $client->getKey()])->getKey(),
        ]);
        $this->assertSame('docházku Jan Novák', LessonAttendanceResource::getRecordTitle($attendance));
    }

    public function test_mounted_delete_modal_names_the_record_in_the_accusative(): void
    {
        $client = User::factory()->customer()->create(['name' => 'Jan Novák']);
        $enrollment = CourseEnrollment::factory()->create(['client_id' => $client->getKey()]);

        $heading = Livewire::test(ListCourseEnrollments::class)
            ->mountAction(TestAction::make('delete')->table($enrollment))
            ->instance()
            ->getMountedAction()
            ?->getModalHeading();

        $this->assertSame('Smazat přihlášku Jan Novák', $heading);
    }

    public function test_media_picker_clear_button_is_named_in_czech(): void
    {
        $this->assertSame('Zrušit výběr', MediaPicker::make('image')->getClearAction()->getLabel());
    }

    public function test_generic_empty_state_description_does_not_mangle_the_model_label(): void
    {
        $this->assertSame(
            'Začněte vytvořením prvního záznamu.',
            __('filament-tables::table.empty.description', ['model' => 'poznámku']),
        );
    }

    /**
     * @param  class-string<RelationManager>  $relationManager
     */
    private function relationManagerTable(string $relationManager, object $ownerRecord, string $pageClass): Table
    {
        return Livewire::test($relationManager, [
            'ownerRecord' => $ownerRecord,
            'pageClass' => $pageClass,
        ])->instance()->getTable();
    }
}

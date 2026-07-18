<?php

namespace Database\Seeders;

use App\Enums\BookingStatus;
use App\Enums\CourseEnrollmentStatus;
use App\Enums\CourseSeriesStatus;
use App\Enums\CreditTransactionType;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Enums\UserRole;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\CourseLesson;
use App\Models\CourseSeries;
use App\Models\LessonAttendance;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\Service;
use App\Models\SubstituteRule;
use App\Models\SubstituteToken;
use App\Models\TherapistProfile;
use App\Models\User;
use App\Models\Workshop;
use App\Models\WorkshopRegistration;
use App\Support\Credits\CreditLedger;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * A ready-to-click client zone: one demo customer (klient@friendly-fyzio.test /
 * "password") carrying every state the zone can show — all seven reservation
 * detail states, a running course with an excused lesson and a used/expired
 * substitute token, a workshop registration, credit history, and invoices.
 *
 * Safe to re-run: everything is keyed off the demo client, whose data is wiped
 * and rebuilt on each run.
 */
class ClientZoneDemoSeeder extends Seeder
{
    public const EMAIL = 'klient@friendly-fyzio.test';

    public function run(): void
    {
        $client = $this->client();

        $this->reset($client);
        $this->reservations($client);
        $this->courses($client);
        $this->credit($client);
    }

    protected function client(): User
    {
        $client = User::query()->withTrashed()->firstOrNew(['email' => self::EMAIL]);

        $client->fill([
            'name' => 'Jana Nováková',
            'phone' => '+420 604 111 222',
            'password' => 'password',
            'role' => UserRole::Customer,
            'email_verified_at' => now(),
            'deactivated_at' => null,
            'deleted_at' => null,
        ])->save();

        $client->clientProfile()->updateOrCreate([], [
            'billing_name' => 'Nováková Consulting s.r.o.',
            'company_ico' => '12345678',
            'company_dic' => 'CZ12345678',
            'billing_address' => 'Zednická 1109/2, 700 30 Ostrava',
        ]);

        return $client;
    }

    /**
     * Drop what a previous run created so states never pile up.
     */
    protected function reset(User $client): void
    {
        Payment::query()->where('client_id', $client->getKey())->whereNull('invoice_id')->forceDelete();
        SubstituteToken::query()->where('client_id', $client->getKey())->delete();

        $client->courseEnrollments()->each(function (CourseEnrollment $enrollment): void {
            LessonAttendance::query()->where('enrollment_id', $enrollment->getKey())->delete();
            $enrollment->forceDelete();
        });

        $client->workshopRegistrations()->forceDelete();
        $client->oneTimeLessonBookings()->forceDelete();
        $client->reservations()->forceDelete();
    }

    /**
     * One reservation per client-zone detail state.
     */
    protected function reservations(User $client): void
    {
        $service = Service::query()->where('price', '>', 0)->first() ?? Service::factory()->create(['price' => 900]);
        $therapist = TherapistProfile::query()->first() ?? TherapistProfile::factory()->create();
        $room = Room::query()->first() ?? Room::factory()->create();

        $base = [
            'client_id' => $client->getKey(),
            'service_id' => $service->getKey(),
            'therapist_id' => $therapist->getKey(),
            'room_id' => $room->getKey(),
            'is_control_therapy' => false,
        ];

        // Čeká na potvrzení — far enough out to be reschedulable.
        Reservation::create([
            ...$base,
            'reservation_date' => today()->addDays(12)->toDateString(),
            'start_time' => '09:00:00',
            'end_time' => '10:00:00',
            'status' => ReservationStatus::Pending,
            'payment_status' => PaymentStatus::Unpaid,
        ]);

        // Potvrzeno — reschedulable.
        Reservation::create([
            ...$base,
            'reservation_date' => today()->addDays(9)->toDateString(),
            'start_time' => '11:00:00',
            'end_time' => '12:00:00',
            'status' => ReservationStatus::Confirmed,
            'confirmed_at' => now()->subDay(),
            'payment_status' => PaymentStatus::Unpaid,
        ]);

        // Potvrzeno, už uvnitř storno lhůty — přesun zakázán, storno se poplatkem.
        Reservation::create([
            ...$base,
            'reservation_date' => today()->toDateString(),
            'start_time' => '16:00:00',
            'end_time' => '17:00:00',
            'status' => ReservationStatus::Confirmed,
            'confirmed_at' => now()->subDays(3),
            'payment_status' => PaymentStatus::Unpaid,
        ]);

        // Dokončeno (zaplaceno).
        $completed = Reservation::create([
            ...$base,
            'reservation_date' => today()->subDays(14)->toDateString(),
            'start_time' => '10:00:00',
            'end_time' => '11:00:00',
            'status' => ReservationStatus::Confirmed,
            'confirmed_at' => now()->subDays(16),
            'payment_status' => PaymentStatus::Paid,
        ]);
        $completed->payments()->create([
            'client_id' => $client->getKey(),
            'amount' => $service->price,
            'method' => PaymentMethod::Cash,
            'status' => PaymentStatus::Paid,
            'paid_at' => now()->subDays(14),
        ]);

        // Čeká na platbu (hotově) — proběhlá návštěva bez platebního požadavku.
        Reservation::create([
            ...$base,
            'reservation_date' => today()->subDays(3)->toDateString(),
            'start_time' => '13:00:00',
            'end_time' => '14:00:00',
            'status' => ReservationStatus::Confirmed,
            'confirmed_at' => now()->subDays(5),
            'payment_status' => PaymentStatus::Unpaid,
        ]);

        // Čeká na platbu (kredit).
        $creditVisit = Reservation::create([
            ...$base,
            'reservation_date' => today()->subDays(5)->toDateString(),
            'start_time' => '15:00:00',
            'end_time' => '16:00:00',
            'status' => ReservationStatus::Confirmed,
            'confirmed_at' => now()->subDays(7),
            'payment_status' => PaymentStatus::Unpaid,
        ]);
        $creditVisit->payments()->create([
            'client_id' => $client->getKey(),
            'amount' => $service->price,
            'method' => PaymentMethod::Credit,
            'status' => PaymentStatus::Unpaid,
            'due_at' => today()->addDays(5),
        ]);

        // Stornováno s nezaplaceným storno poplatkem (QR) — "Čeká na platbu" + QR panel.
        $cancelled = Reservation::create([
            ...$base,
            'reservation_date' => today()->subDays(2)->toDateString(),
            'start_time' => '08:00:00',
            'end_time' => '09:00:00',
            'status' => ReservationStatus::Cancelled,
            'cancellation_reason' => 'Pozdní storno – klient zaplatí',
            'payment_status' => PaymentStatus::Unpaid,
        ]);
        $cancelled->payments()->create([
            'client_id' => $client->getKey(),
            'amount' => (int) round($service->price / 2),
            'method' => PaymentMethod::Qr,
            'status' => PaymentStatus::Unpaid,
            'due_at' => today()->addDays(5),
        ]);
    }

    /**
     * A running course with an excused lesson + substitute tokens (active, used,
     * expired), a second cancellable enrollment, and a workshop registration.
     */
    protected function courses(User $client): void
    {
        [$source, $target] = $this->substituteCoursePair();

        $series = $this->runningSeries($source);
        $targetSeries = $this->runningSeries($target);

        // Link the two runs so a token minted in $series can be redeemed in $targetSeries.
        SubstituteRule::query()->firstOrCreate([
            'source_series_id' => $series->getKey(),
            'target_series_id' => $targetSeries->getKey(),
        ]);

        $enrollment = CourseEnrollment::create([
            'client_id' => $client->getKey(),
            'series_id' => $series->getKey(),
            'status' => CourseEnrollmentStatus::Active,
            'payment_status' => PaymentStatus::Paid,
            'paid_at' => now()->subWeeks(5),
        ]);

        $lessons = $series->lessons()->orderBy('lesson_date')->get();
        $targetLessons = $targetSeries->lessons()->orderBy('lesson_date')->get();

        // Timely excuse → active token, ready to redeem.
        if ($missed = $lessons->firstWhere(fn (CourseLesson $lesson): bool => $lesson->lesson_date->isFuture())) {
            LessonAttendance::create([
                'enrollment_id' => $enrollment->getKey(),
                'lesson_id' => $missed->getKey(),
                'attended' => false,
                'cancelled_at' => now(),
                'token_generated' => true,
            ]);

            SubstituteToken::create([
                'client_id' => $client->getKey(),
                'source_lesson_id' => $missed->getKey(),
                'expires_at' => now()->addDays(30),
            ]);
        }

        // Already redeemed token (+ its substitute attendance in the other course).
        $past = $lessons->firstWhere(fn (CourseLesson $lesson): bool => $lesson->lesson_date->isPast());
        $usedFor = $targetLessons->firstWhere(fn (CourseLesson $lesson): bool => $lesson->lesson_date->isFuture());

        if ($past && $usedFor) {
            LessonAttendance::create([
                'enrollment_id' => $enrollment->getKey(),
                'lesson_id' => $past->getKey(),
                'attended' => false,
                'cancelled_at' => now()->subWeeks(2),
                'token_generated' => true,
            ]);

            SubstituteToken::create([
                'client_id' => $client->getKey(),
                'source_lesson_id' => $past->getKey(),
                'expires_at' => now()->addDays(16),
                'used_at' => now()->subDays(3),
                'used_for_lesson_id' => $usedFor->getKey(),
            ]);

            LessonAttendance::create([
                'enrollment_id' => $enrollment->getKey(),
                'lesson_id' => $usedFor->getKey(),
                'attended' => false,
            ]);
        }

        // Expired token.
        if ($expiredSource = $lessons->last()) {
            SubstituteToken::create([
                'client_id' => $client->getKey(),
                'source_lesson_id' => $expiredSource->getKey(),
                'expires_at' => now()->subDays(4),
            ]);
        }

        // A future run the client can still cancel themselves.
        $upcoming = CourseSeries::query()->firstOrCreate(
            ['course_id' => $target->getKey(), 'name' => 'Demo série – jaro'],
            [
                'start_date' => today()->addMonth(),
                'end_date' => today()->addMonths(4),
                'capacity' => 12,
                'price' => 2400,
                'status' => CourseSeriesStatus::Open,
            ],
        );

        $futureEnrollment = CourseEnrollment::create([
            'client_id' => $client->getKey(),
            'series_id' => $upcoming->getKey(),
            'status' => CourseEnrollmentStatus::Active,
            'payment_status' => PaymentStatus::Unpaid,
        ]);
        $futureEnrollment->payments()->create([
            'client_id' => $client->getKey(),
            'amount' => $upcoming->price,
            'method' => PaymentMethod::Qr,
            'status' => PaymentStatus::Unpaid,
            'due_at' => today()->addDays(2),
        ]);

        // Workshop registration (unpaid, QR).
        $workshop = Workshop::query()->whereDate('workshop_date', '>=', today()->addWeeks(2))->first();

        if ($workshop !== null) {
            $registration = WorkshopRegistration::create([
                'client_id' => $client->getKey(),
                'workshop_id' => $workshop->getKey(),
                'status' => BookingStatus::Confirmed,
                'payment_status' => PaymentStatus::Unpaid,
            ]);
            $registration->payments()->create([
                'client_id' => $client->getKey(),
                'amount' => $workshop->price,
                'method' => PaymentMethod::Qr,
                'status' => PaymentStatus::Unpaid,
                'due_at' => today()->addDays(3),
            ]);
        }
    }

    /**
     * Two published courses whose running runs are linked by a substitute rule
     * (created in {@see courses()} once both séries exist), so redeeming a token
     * has somewhere to go.
     *
     * @return array{0: Course, 1: Course}
     */
    protected function substituteCoursePair(): array
    {
        $courses = Course::query()->published()->orderBy('name')->take(2)->get();

        while ($courses->count() < 2) {
            $courses->push(Course::factory()->create(['published_at' => now()]));
        }

        [$source, $target] = [$courses[0], $courses[1]];

        return [$source, $target];
    }

    /**
     * A course run that started a month ago and still has lessons ahead —
     * weekly lessons materialised so the excuse flow has something to act on.
     */
    protected function runningSeries(Course $course): CourseSeries
    {
        $series = CourseSeries::query()->firstOrCreate(
            ['course_id' => $course->getKey(), 'name' => 'Demo série – probíhá'],
            [
                'start_date' => today()->subWeeks(4),
                'end_date' => today()->addWeeks(8),
                'capacity' => 12,
                'price' => 2400,
                'status' => CourseSeriesStatus::Open,
            ],
        );

        if ($series->lessons()->exists()) {
            return $series;
        }

        $room = Room::query()->first() ?? Room::factory()->create();
        $instructor = $course->instructor_id ?? User::query()->therapists()->value('id');

        foreach (range(0, 11) as $week) {
            $date = Carbon::parse($series->start_date)->addWeeks($week);

            CourseLesson::create([
                'series_id' => $series->getKey(),
                'instructor_id' => $instructor,
                'room_id' => $room->getKey(),
                'lesson_date' => $date->toDateString(),
                'start_time' => '18:00:00',
                'end_time' => '19:00:00',
            ]);
        }

        return $series->refresh();
    }

    /**
     * Credit history: a live top-up, a deduction, and an expired top-up with
     * its matching expiration row.
     */
    protected function credit(User $client): void
    {
        $client->creditTransactions()->delete();
        $client->creditAccount()->update(['balance' => 0]);

        CreditLedger::record($client, 1000, CreditTransactionType::TopUp, 'Dárkový poukaz č. 2026-014', now()->addMonths(6));
        CreditLedger::record($client, -300, CreditTransactionType::Deduction, 'Terapie pánevního dna');

        $expired = CreditLedger::record($client, 200, CreditTransactionType::TopUp, 'Dárkový poukaz č. 2025-088', now()->subWeek());
        CreditLedger::record($client, -200, CreditTransactionType::Expiration, 'Propadnutí kreditu z dobití', related: $expired);
    }
}

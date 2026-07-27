<?php

namespace App\Livewire\Zone;

use App\Enums\BookingStatus;
use App\Enums\CourseEnrollmentStatus;
use App\Enums\PaymentMethod;
use App\Models\CourseEnrollment;
use App\Models\Lesson;
use App\Models\LessonAttendance;
use App\Models\LessonBooking;
use App\Models\Payment;
use App\Models\User;
use App\Support\Enrollments\CancellationWindowClosedException;
use App\Support\Enrollments\CancelSignupAsClient;
use App\Support\Substitutes\ExcuseFromLesson;
use App\Support\Substitutes\SubstituteException;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * "Moje kurzy": everything the client signed up for — course runs and one-off
 * events (workshopy, jednorázové lekce) — with self-cancellation inside the
 * configured window and, for course runs, the per-lesson excuse that mints
 * substitute entries (pencil frame Profile/My Courses).
 */
class Courses extends Component
{
    #[Url(as: 'zalozka')]
    public string $tab = 'aktualni';

    public ?string $expandedEnrollmentId = null;

    public ?string $confirmingCancelId = null;

    public ?string $confirmingCancelType = null;

    public ?string $confirmingExcuseEnrollmentId = null;

    public ?string $confirmingExcuseLessonId = null;

    public function selectTab(string $tab): void
    {
        $this->tab = in_array($tab, ['aktualni', 'minule'], true) ? $tab : 'aktualni';
        $this->expandedEnrollmentId = null;
    }

    public function toggleLessons(string $enrollmentId): void
    {
        $this->expandedEnrollmentId = $this->expandedEnrollmentId === $enrollmentId ? null : $enrollmentId;
    }

    public function confirmCancel(string $type, string $id): void
    {
        $this->confirmingCancelType = $type;
        $this->confirmingCancelId = $id;
    }

    public function closeCancel(): void
    {
        $this->confirmingCancelType = null;
        $this->confirmingCancelId = null;
    }

    public function cancelSignup(CancelSignupAsClient $cancel): void
    {
        $signup = $this->signupBeingCancelled();

        if ($signup === null) {
            return;
        }

        try {
            $cancel($signup);
        } catch (CancellationWindowClosedException $exception) {
            $this->addError('cancel', $exception->getMessage());
            $this->closeCancel();

            return;
        }

        $this->closeCancel();

        session()->flash('status', 'Odhlášení proběhlo. Potvrzení jsme vám poslali e-mailem.');
    }

    public function confirmExcuse(string $enrollmentId, string $lessonId): void
    {
        $this->confirmingExcuseEnrollmentId = $enrollmentId;
        $this->confirmingExcuseLessonId = $lessonId;
    }

    public function closeExcuse(): void
    {
        $this->confirmingExcuseEnrollmentId = null;
        $this->confirmingExcuseLessonId = null;
    }

    public function excuseFromLesson(ExcuseFromLesson $excuse): mixed
    {
        [$enrollment, $lesson] = $this->lessonBeingExcused();

        if ($enrollment === null || $lesson === null) {
            $this->closeExcuse();

            return null;
        }

        try {
            $token = $excuse($enrollment, $lesson);
        } catch (SubstituteException $exception) {
            $this->addError('excuse', $exception->getMessage());
            $this->closeExcuse();

            return null;
        }

        $this->closeExcuse();

        if ($token !== null) {
            session()->flash('status', 'Z lekce jsme vás odhlásili a vystavili náhradní vstup — uplatníte ho níže.');

            return $this->redirectRoute('zone.tokens', navigate: true);
        }

        session()->flash('status', 'Z lekce jsme vás odhlásili. Na náhradní vstup už bohužel nevzniká nárok.');

        return null;
    }

    /**
     * The enrollment + lesson pending an excuse confirmation, if any.
     *
     * @return array{0: ?CourseEnrollment, 1: ?Lesson}
     */
    protected function lessonBeingExcused(): array
    {
        if (blank($this->confirmingExcuseEnrollmentId) || blank($this->confirmingExcuseLessonId)) {
            return [null, null];
        }

        $enrollment = $this->enrollments()->firstWhere('id', $this->confirmingExcuseEnrollmentId);
        $lesson = $enrollment?->series?->lessons->firstWhere('id', $this->confirmingExcuseLessonId);

        return [$enrollment, $lesson];
    }

    protected function signupBeingCancelled(): CourseEnrollment|LessonBooking|null
    {
        if (blank($this->confirmingCancelId)) {
            return null;
        }

        $user = $this->user();

        return match ($this->confirmingCancelType) {
            'enrollment' => $user->courseEnrollments()->with('series.course')->find($this->confirmingCancelId),
            'booking' => $user->lessonBookings()->with('lesson.category')->find($this->confirmingCancelId),
            default => null,
        };
    }

    /**
     * @return Collection<int, CourseEnrollment>
     */
    protected function enrollments(): Collection
    {
        $upcoming = $this->tab === 'aktualni';

        return $this->user()->courseEnrollments()
            ->with(['series.course', 'series.lessons.room', 'payments'])
            ->when($upcoming, fn ($query) => $query
                ->where('status', CourseEnrollmentStatus::Active)
                ->whereHas('series', fn ($series) => $series->whereDate('end_date', '>=', today())))
            ->when(! $upcoming, fn ($query) => $query
                ->where(fn ($inner) => $inner
                    ->where('status', CourseEnrollmentStatus::Cancelled)
                    ->orWhereHas('series', fn ($series) => $series->whereDate('end_date', '<', today()))))
            ->get();
    }

    /**
     * @return Collection<int, LessonBooking>
     */
    protected function bookings(): Collection
    {
        $upcoming = $this->tab === 'aktualni';

        return $this->user()->lessonBookings()
            ->with(['lesson.category', 'lesson.course', 'lesson.room', 'payments'])
            ->when($upcoming, fn ($query) => $query
                ->whereIn('status', BookingStatus::occupying())
                ->whereHas('lesson', fn ($event) => $event->whereDate('lesson_date', '>=', today())))
            ->when(! $upcoming, fn ($query) => $query
                ->where(fn ($inner) => $inner
                    ->where('status', BookingStatus::Cancelled)
                    ->orWhereHas('lesson', fn ($event) => $event->whereDate('lesson_date', '<', today()))))
            ->get();
    }

    /**
     * Upcoming lessons of an expanded course run with their excuse state.
     *
     * @return array<int, array{lesson: Lesson, excused: bool, token: bool}>
     */
    protected function lessonRows(CourseEnrollment $enrollment): array
    {
        $attendances = LessonAttendance::query()
            ->where('enrollment_id', $enrollment->getKey())
            ->get()
            ->keyBy('lesson_id');

        return $enrollment->series?->lessons
            ->filter(fn (Lesson $lesson): bool => $lesson->lesson_date->isToday() || $lesson->lesson_date->isFuture())
            ->map(fn (Lesson $lesson): array => [
                'lesson' => $lesson,
                'excused' => $attendances->get($lesson->getKey())?->cancelled_at !== null,
                'token' => (bool) $attendances->get($lesson->getKey())?->token_generated,
            ])
            ->values()
            ->all() ?? [];
    }

    public function render(): View
    {
        $cancel = app(CancelSignupAsClient::class);
        $expanded = $this->expandedEnrollmentId;
        $enrollments = $this->enrollments();

        return view('livewire.zone.courses', [
            'enrollments' => $enrollments,
            'bookings' => $this->bookings(),
            'canCancel' => fn ($signup): bool => $cancel->isCancellable($signup),
            'openPayment' => fn (CourseEnrollment|LessonBooking $signup): ?Payment => $signup->payments
                ->first(fn (Payment $payment): bool => $payment->method === PaymentMethod::Qr
                    && $payment->status->isOpen()),
            'lessonRows' => $expanded !== null && ($enrollment = $enrollments->firstWhere('id', $expanded))
                ? $this->lessonRows($enrollment)
                : [],
            'cancelling' => $this->signupBeingCancelled(),
            'excusing' => $this->lessonBeingExcused()[1],
        ]);
    }

    protected function user(): User
    {
        /** @var User */
        return auth()->user();
    }
}

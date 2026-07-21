<?php

namespace App\Livewire\Zone;

use App\Enums\BookingStatus;
use App\Enums\CourseEnrollmentStatus;
use App\Models\CourseEnrollment;
use App\Models\CourseLesson;
use App\Models\LessonAttendance;
use App\Models\OneOffEventBooking;
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

    public function excuseFromLesson(string $enrollmentId, string $lessonId, ExcuseFromLesson $excuse): void
    {
        $enrollment = $this->enrollments()->firstWhere('id', $enrollmentId);
        $lesson = $enrollment?->series?->lessons->firstWhere('id', $lessonId);

        if ($enrollment === null || $lesson === null) {
            return;
        }

        try {
            $token = $excuse($enrollment, $lesson);
        } catch (SubstituteException $exception) {
            $this->addError('excuse', $exception->getMessage());

            return;
        }

        session()->flash('status', $token !== null
            ? 'Z lekce jsme vás odhlásili a vystavili náhradní vstup — najdete ho v Náhradních vstupech.'
            : 'Z lekce jsme vás odhlásili. Na náhradní vstup už bohužel nevzniká nárok.');
    }

    protected function signupBeingCancelled(): CourseEnrollment|OneOffEventBooking|null
    {
        if (blank($this->confirmingCancelId)) {
            return null;
        }

        $user = $this->user();

        return match ($this->confirmingCancelType) {
            'enrollment' => $user->courseEnrollments()->with('series.course')->find($this->confirmingCancelId),
            'booking' => $user->oneOffEventBookings()->with('event.category')->find($this->confirmingCancelId),
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
     * @return Collection<int, OneOffEventBooking>
     */
    protected function bookings(): Collection
    {
        $upcoming = $this->tab === 'aktualni';

        return $this->user()->oneOffEventBookings()
            ->with(['event.category', 'event.course', 'event.room', 'payments'])
            ->when($upcoming, fn ($query) => $query
                ->whereIn('status', BookingStatus::occupying())
                ->whereHas('event', fn ($event) => $event->whereDate('event_date', '>=', today())))
            ->when(! $upcoming, fn ($query) => $query
                ->where(fn ($inner) => $inner
                    ->where('status', BookingStatus::Cancelled)
                    ->orWhereHas('event', fn ($event) => $event->whereDate('event_date', '<', today()))))
            ->get();
    }

    /**
     * Upcoming lessons of an expanded course run with their excuse state.
     *
     * @return array<int, array{lesson: CourseLesson, excused: bool, token: bool}>
     */
    protected function lessonRows(CourseEnrollment $enrollment): array
    {
        $attendances = LessonAttendance::query()
            ->where('enrollment_id', $enrollment->getKey())
            ->get()
            ->keyBy('lesson_id');

        return $enrollment->series?->lessons
            ->filter(fn (CourseLesson $lesson): bool => $lesson->lesson_date->isToday() || $lesson->lesson_date->isFuture())
            ->map(fn (CourseLesson $lesson): array => [
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
            'lessonRows' => $expanded !== null && ($enrollment = $enrollments->firstWhere('id', $expanded))
                ? $this->lessonRows($enrollment)
                : [],
            'cancelling' => $this->signupBeingCancelled(),
        ]);
    }

    protected function user(): User
    {
        /** @var User */
        return auth()->user();
    }
}

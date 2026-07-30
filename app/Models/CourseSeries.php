<?php

namespace App\Models;

use App\Enums\CourseEnrollmentStatus;
use App\Enums\CourseSeriesStatus;
use App\Enums\CourseSeriesVisibility;
use App\Enums\OfferState;
use App\Enums\WaitlistPromotionMode;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasCapacity;
use App\Observers\CourseSeriesObserver;
use App\Support\Lessons\LessonScheduleGenerator;
use App\Support\Lessons\SeriesSchedule;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[ObservedBy(CourseSeriesObserver::class)]
class CourseSeries extends Model
{
    use Auditable, HasCapacity, HasFactory, HasUuids;

    protected $fillable = [
        'course_id',
        'instructor_id',
        'name',
        'invoice_title',
        'start_date',
        'end_date',
        'schedule',
        'capacity',
        'max_substitutions',
        'waitlist_promotion_mode',
        'waitlist_invited_until',
        'price',
        'status',
        'visibility',
        'presale_token',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'schedule' => 'array',
            'capacity' => 'integer',
            'max_substitutions' => 'integer',
            'waitlist_promotion_mode' => WaitlistPromotionMode::class,
            'waitlist_invited_until' => 'datetime',
            'price' => 'integer',
            'status' => CourseSeriesStatus::class,
            'visibility' => CourseSeriesVisibility::class,
        ];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /**
     * The lecturer of this run of the course. Optional: an empty column means
     * the série is led by the course's own instructor — see {@see leadInstructor()}.
     */
    public function instructor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'instructor_id');
    }

    /**
     * Who actually teaches the série: its own lecturer when one is assigned,
     * otherwise the course's instructor.
     */
    public function leadInstructor(): ?User
    {
        return $this->instructor ?? $this->course?->instructor;
    }

    /**
     * Series the given user leads — their own série assignments plus every série
     * of a course they instruct. Both count, so assigning a lecturer to a single
     * série never takes the course's owner off it.
     */
    public function scopeLedBy(Builder $query, string $userId): Builder
    {
        return $query->where(fn (Builder $nested) => $nested
            ->where($this->qualifyColumn('instructor_id'), $userId)
            ->orWhereHas('course', fn (Builder $course) => $course->where('instructor_id', $userId)));
    }

    public function lessons(): HasMany
    {
        return $this->hasMany(Lesson::class, 'series_id')->orderBy('lesson_date');
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(CourseEnrollment::class, 'series_id');
    }

    public function activeTakers(): HasMany
    {
        return $this->enrollments()->where('status', CourseEnrollmentStatus::Active);
    }

    public function waitlistEntries()
    {
        return $this->morphMany(WaitlistEntry::class, 'waitlistable');
    }

    public function substituteRulesAsSource(): HasMany
    {
        return $this->hasMany(SubstituteRule::class, 'source_series_id');
    }

    public function substituteRulesAsTarget(): HasMany
    {
        return $this->hasMany(SubstituteRule::class, 'target_series_id');
    }

    public function invitations()
    {
        return $this->morphMany(Invitation::class, 'inviteable');
    }

    public function isPrivate(): bool
    {
        return $this->visibility === CourseSeriesVisibility::Private;
    }

    public function hasStarted(): bool
    {
        return $this->start_date->isBefore(today());
    }

    public function hasEnded(): bool
    {
        return $this->end_date->isBefore(today());
    }

    /**
     * When this série meets — one slot per weekly session, so a série running on
     * středa at 9:00 and čtvrtek at 10:30 is expressible. Unusable stored rows
     * are dropped rather than thrown on: a rozvrh is a convenience, and one
     * stale row must not break the série's detail page.
     */
    public function weeklySchedule(): SeriesSchedule
    {
        return SeriesSchedule::fromArray($this->schedule);
    }

    /**
     * Whether the série carries everything {@see LessonScheduleGenerator} needs
     * to place a lesson: at least one slot, and a room on every one of them
     * (lessons cannot be saved without one). A single roomless slot blocks the
     * whole run, the same all-or-nothing rule an otherwise incomplete rozvrh has.
     */
    public function hasLessonSchedule(): bool
    {
        $schedule = $this->weeklySchedule();

        return ! $schedule->isEmpty() && $schedule->everySlotHasRoom();
    }

    /**
     * The rozvrh in one line for staff — "Pondělí a středa 17:00–18:00 · Sál A",
     * or one group per room when the days do not share one. Null only while no
     * rozvrh is filled in; a missing room still shows the days and times, since
     * that is what staff need to see to spot what is missing.
     */
    public function scheduleLabel(): ?string
    {
        $schedule = $this->weeklySchedule();

        if ($schedule->isEmpty()) {
            return null;
        }

        /** @var array<string, string> $roomNames */
        $roomNames = Room::query()->whereKey($schedule->roomIds())->pluck('name', 'id')->all();

        return Str::ucfirst((string) $schedule->labelWithRooms($roomNames));
    }

    /**
     * The rozvrh as clients read it — "úterý a čtvrtek 17:30–18:30", no room
     * (the public page shows the place on its own row) and no room requirement,
     * so a série still advertises its day and time before one is assigned.
     */
    public function scheduleSummary(): ?string
    {
        return $this->weeklySchedule()->label();
    }

    /**
     * The same, abbreviated to weekday and start time — "út 15:00, st 10:00" —
     * for the cramped offer card and the detail page's summary row.
     */
    public function shortScheduleSummary(): ?string
    {
        return $this->weeklySchedule()->shortLabel();
    }

    public function totalLessonsCount(): int
    {
        $eager = $this->getAttribute('lessons_count');

        return $eager !== null ? (int) $eager : $this->lessons()->count();
    }

    public function remainingLessonsCount(): int
    {
        $eager = $this->getAttribute('remaining_lessons_count');

        return $eager !== null
            ? (int) $eager
            : $this->lessons()->whereDate('lesson_date', '>=', today())->count();
    }

    /**
     * The price a client signing up right now pays. Mid-series sign-ups are
     * pro-rated by the share of lessons still ahead ("cena je poměrně snížena
     * podle počtu zbývajících lekcí"); a series without planned lessons (or one
     * that hasn't started) charges the full price.
     */
    public function currentPrice(): int
    {
        if (! $this->hasStarted()) {
            return (int) $this->price;
        }

        $total = $this->totalLessonsCount();

        if ($total === 0) {
            return (int) $this->price;
        }

        return (int) round($this->price * $this->remainingLessonsCount() / $total);
    }

    /**
     * Public registration state of this series. The manual Inactive status means
     * "we're preparing this one" (registration closed, notify-me form shown);
     * a Private series behaves the same on every public surface — only its
     * hidden link (offerStateForPresale) opens it. Fullness combines the manual
     * Full status with live spot accounting.
     */
    public function offerState(): OfferState
    {
        return match (true) {
            $this->hasEnded() => OfferState::Inactive,
            $this->visibility === CourseSeriesVisibility::Private => OfferState::Preparing,
            $this->status === CourseSeriesStatus::Inactive => OfferState::Preparing,
            $this->status === CourseSeriesStatus::Full, $this->isFull() => OfferState::Full,
            $this->waitlistInviteActive() => OfferState::Full,
            default => OfferState::Open,
        };
    }

    /**
     * Hidden-link access: a series keeps taking registrations through its
     * secret link even while still Inactive (docs: "predpredaj pre stálych
     * klientov") or Private (invite-only run). Ended or full series stay
     * closed even with the token.
     *
     * The link is also what lets a waitlist invite round work: it ignores
     * {@see waitlistInviteActive()}, so a waiter holding the link can take the
     * reserved spot while the public form still shows the series as full.
     */
    public function offerStateForPresale(): OfferState
    {
        if ($this->hasEnded()) {
            return OfferState::Inactive;
        }

        if ($this->status === CourseSeriesStatus::Full || $this->isFull()) {
            return OfferState::Full;
        }

        return OfferState::Open;
    }

    public function ensurePresaleToken(): string
    {
        if (blank($this->presale_token)) {
            $this->forceFill(['presale_token' => Str::random(40)])->save();
        }

        return (string) $this->presale_token;
    }

    public function presaleUrl(): string
    {
        return $this->course->permalink().'?predprodej='.$this->ensurePresaleToken();
    }
}

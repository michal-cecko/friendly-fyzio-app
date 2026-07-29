<?php

namespace App\Models;

use App\Enums\BookingStatus;
use App\Enums\CourseEnrollmentStatus;
use App\Enums\OfferState;
use App\Enums\OfferVisibility;
use App\Enums\WaitlistPromotionMode;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasCapacity;
use App\Models\Concerns\HasPresaleAccess;
use App\Models\Concerns\Publishable;
use App\Observers\LessonObserver;
use App\Support\RichText;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * One session: a time, a room, an instructor. It may belong to a course série
 * and it may be on public sale, independently of each other.
 *
 *   series_id set,  published_at null → a lesson of a course série (schedule only)
 *   series_id set,  published_at set  → that same lesson, also sold as a drop-in
 *   series_id null, published_at set  → a standalone workshop / jednorázová lekce
 *
 * This is one record, not two: a course lesson released to the public *is* the
 * jednorázová lekce. That is what keeps a single occupancy number — the série's
 * roster and the drop-in bookings are counted by the same {@see takenSpots()},
 * so the room can no longer be double-booked from two directions.
 */
#[ObservedBy(LessonObserver::class)]
class Lesson extends Model
{
    use Auditable, HasCapacity, HasFactory, HasPresaleAccess, HasUuids, Publishable, SoftDeletes;

    public function logTitle(): string
    {
        $title = $this->name
            ?? $this->series?->course?->name
            ?? $this->series?->name
            ?? 'Lekce';

        return trim($title.' · '.$this->lesson_date?->format('j. n. Y'), ' ·');
    }

    protected $fillable = [
        'series_id',
        'event_category_id',
        'course_id',
        'instructor_id',
        'room_id',
        'lesson_date',
        'start_time',
        'end_time',
        'name',
        'slug',
        'invoice_title',
        'description',
        'featured_image',
        'detail_image',
        'capacity',
        'price',
        'visibility',
        'presale_token',
        'published_at',
        'released_at',
        'waitlist_promotion_mode',
        'waitlist_invited_until',
    ];

    protected function casts(): array
    {
        return [
            'lesson_date' => 'date',
            'visibility' => OfferVisibility::class,
            'waitlist_promotion_mode' => WaitlistPromotionMode::class,
            'waitlist_invited_until' => 'datetime',
            'featured_image' => 'integer',
            'detail_image' => 'integer',
            'published_at' => 'datetime',
            'released_at' => 'datetime',
        ];
    }

    public function series(): BelongsTo
    {
        return $this->belongsTo(CourseSeries::class, 'series_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(EventCategory::class, 'event_category_id');
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function instructor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'instructor_id');
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(LessonAttendance::class, 'lesson_id');
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(LessonBooking::class, 'lesson_id');
    }

    public function activeTakers(): HasMany
    {
        return $this->bookings()->whereIn('status', BookingStatus::occupying());
    }

    public function substituteTokensAsSource(): HasMany
    {
        return $this->hasMany(SubstituteToken::class, 'source_lesson_id');
    }

    public function substituteTokensUsedHere(): HasMany
    {
        return $this->hasMany(SubstituteToken::class, 'used_for_lesson_id');
    }

    public function waitlistEntries()
    {
        return $this->morphMany(WaitlistEntry::class, 'waitlistable');
    }

    public function reviews()
    {
        return $this->morphMany(Review::class, 'reviewable');
    }

    public function invitations()
    {
        return $this->morphMany(Invitation::class, 'inviteable');
    }

    public function isPartOfSeries(): bool
    {
        return $this->series_id !== null;
    }

    /**
     * Whether a free place on this lesson has been put on public sale.
     */
    public function isReleased(): bool
    {
        return $this->released_at !== null;
    }

    /**
     * A lesson of a série holds no capacity of its own — it borrows the série's
     * room. A standalone one carries its own.
     */
    protected function capacity(): Attribute
    {
        return Attribute::get(fn (mixed $value): int => $value !== null
            ? (int) $value
            : (int) ($this->series?->capacity ?? 0));
    }

    /**
     * What a single seat costs. A standalone lesson is priced directly; a lesson
     * of a série falls back to its course's drop-in price, and null there means
     * this course is never sold by the lesson.
     */
    protected function price(): Attribute
    {
        return Attribute::get(function (mixed $value): ?int {
            if ($value !== null) {
                return (int) $value;
            }

            $dropIn = $this->series?->course?->drop_in_price;

            return $dropIn === null ? null : (int) $dropIn;
        });
    }

    public function isSoldIndividually(): bool
    {
        return $this->price !== null;
    }

    /**
     * People expected in the room — one count of one list, since the presence
     * list holds everybody: the série's enrollees, substitutes moved in from
     * another run, and anyone who bought this single lesson.
     *
     * Rows of a cancelled enrollment are kept for history and stop counting the
     * moment the enrollment is no longer active; excused rows carry
     * `cancelled_at` and free their seat the same way.
     *
     * This deliberately overrides {@see HasCapacity::takenSpots()}, which counts
     * bookings alone — for a lesson of a série that would ignore the roster.
     */
    public function takenSpots(): int
    {
        $eager = $this->getAttribute('present_count');

        return $eager !== null ? (int) $eager : $this->presentSeats()->count();
    }

    /**
     * The seats that actually hold a place: not excused, and not left behind by
     * an enrollment that has since been cancelled.
     *
     * @return HasMany<LessonAttendance>
     */
    public function presentSeats(): HasMany
    {
        return $this->attendances()
            ->whereNull('cancelled_at')
            ->where(fn (Builder $query) => $query
                ->whereNull('enrollment_id')
                ->orWhereHas('enrollment', fn (Builder $enrollment) => $enrollment
                    ->where('status', CourseEnrollmentStatus::Active)));
    }

    /**
     * Active enrollments in the owning série — the course participants, before
     * excuses and before anyone bought a single seat.
     */
    public function enrolledCount(): int
    {
        return (int) ($this->series?->takenSpots() ?? 0);
    }

    /**
     * Clients excused from this date, whose spot is therefore free.
     */
    public function excusedCount(): int
    {
        $eager = $this->getAttribute('excused_count');

        return $eager !== null
            ? (int) $eager
            : $this->attendances()->whereNotNull('cancelled_at')->count();
    }

    /**
     * Substitutes from another série sitting in this lesson.
     */
    public function substitutesInCount(): int
    {
        $eager = $this->getAttribute('substitutes_in_count');

        return $eager !== null
            ? (int) $eager
            : $this->attendances()
                ->whereNull('cancelled_at')
                ->whereHas('enrollment', fn (Builder $query) => $query->where('series_id', '!=', $this->series_id))
                ->count();
    }

    /**
     * People who bought this one lesson rather than the whole série.
     */
    public function dropInCount(): int
    {
        $eager = $this->getAttribute('drop_in_count');

        return $eager !== null
            ? (int) $eager
            : $this->attendances()->whereNull('cancelled_at')->whereNotNull('booking_id')->count();
    }

    /**
     * Eager-loads everything {@see takenSpots()} needs, so lesson lists don't fire
     * four COUNTs per row.
     */
    public function scopeWithOccupancyCounts(Builder $query): Builder
    {
        return $query
            ->with(['series' => fn ($series) => $series->withTakenSpots()])
            ->withCount([
                'presentSeats as present_count',
                'attendances as drop_in_count' => fn (Builder $attendances) => $attendances
                    ->whereNull('cancelled_at')
                    ->whereNotNull('booking_id'),
                'attendances as excused_count' => fn (Builder $attendances) => $attendances->whereNotNull('cancelled_at'),
                'attendances as substitutes_in_count' => fn (Builder $attendances) => $attendances
                    ->whereNull('cancelled_at')
                    ->whereHas('enrollment', fn (Builder $enrollment) => $enrollment
                        ->whereColumn('course_enrollments.series_id', '!=', 'lessons.series_id')),
            ]);
    }

    /**
     * The SQL twin of {@see takenSpots()}, for list queries that filter on
     * availability. {@see HasCapacity::scopeHasSpotsLeft()} compares the capacity
     * COLUMN against booking rows, which is null and half the story for a lesson
     * of a série — without this override the public "jen volná místa" filter
     * would silently drop every released course lesson.
     */
    public function scopeHasSpotsLeft(Builder $query): Builder
    {
        $active = CourseEnrollmentStatus::Active->value;

        return $query->whereRaw(<<<SQL
            coalesce(lessons.capacity, (
                select course_series.capacity from course_series where course_series.id = lessons.series_id
            ), 0) > (
                select count(*) from lesson_attendances
                left join course_enrollments on course_enrollments.id = lesson_attendances.enrollment_id
                where lesson_attendances.lesson_id = lessons.id
                  and lesson_attendances.cancelled_at is null
                  and (lesson_attendances.enrollment_id is null or course_enrollments.status = '{$active}')
            )
        SQL);
    }

    /**
     * Lessons the given user leads: the ones they teach themselves plus every
     * lesson of a série they lead (see {@see CourseSeries::scopeLedBy()}), so a
     * lecturer keeps the sessions of their own série even when a colleague
     * stands in for one of them.
     */
    public function scopeLedBy(Builder $query, string $userId): Builder
    {
        return $query->where(fn (Builder $nested) => $nested
            ->where($this->qualifyColumn('instructor_id'), $userId)
            ->orWhereHas('series', fn (Builder $series) => $series->ledBy($userId)));
    }

    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->whereDate('lesson_date', '>=', today());
    }

    public function scopePast(Builder $query): Builder
    {
        return $query->whereDate('lesson_date', '<', today());
    }

    /**
     * Lessons that are on public sale, soonest first — the single archive.
     */
    public function scopeOnSale(Builder $query): Builder
    {
        return $query->published()
            ->upcoming()
            ->whereNotNull('slug')
            ->whereNotNull('event_category_id')
            ->orderBy('lesson_date')
            ->orderBy('start_time');
    }

    /**
     * Description shown on public surfaces — the lesson's own, or the linked
     * course's as a live fallback (kept in sync, not copied).
     */
    public function displayDescription(): ?string
    {
        return $this->description ?? $this->offerCourse()?->description;
    }

    /**
     * The same description as rich-editor HTML, so a detail page can render one
     * block regardless of whether it came from the lesson's plain-text field or
     * the course's rich editor.
     */
    public function displayDescriptionHtml(): ?string
    {
        return $this->description !== null
            ? RichText::fromPlainText($this->description)
            : $this->offerCourse()?->description;
    }

    /**
     * Media-library image id for the landscape card in the archive. The lesson's
     * own photos win over the course's; within each, the card photo wins over the
     * square one.
     */
    public function displayCardImage(): ?int
    {
        return $this->featured_image ?? $this->detail_image ?? $this->offerCourse()?->cardImage();
    }

    /**
     * Media-library image id for the square detail hero, on the same
     * lesson-before-course rule as {@see displayCardImage()}.
     */
    public function displayDetailImage(): ?int
    {
        return $this->detail_image ?? $this->featured_image ?? $this->offerCourse()?->detailImage();
    }

    /**
     * The course this lesson belongs to, whether by the direct cross-sell link
     * (standalone offers) or through its série.
     */
    public function offerCourse(): ?Course
    {
        return $this->course ?? $this->series?->course;
    }

    public function displayName(): string
    {
        return $this->name
            ?? trim(($this->offerCourse()?->name ?? 'Lekce').' – jednorázová lekce');
    }

    public function startsAt(): Carbon
    {
        return Carbon::parse($this->lesson_date->format('Y-m-d').' '.$this->start_time);
    }

    public function endsAt(): Carbon
    {
        return Carbon::parse($this->lesson_date->format('Y-m-d').' '.$this->end_time);
    }

    public function isPast(): bool
    {
        return $this->startsAt()->isPast();
    }

    /**
     * Public sign-up state: unpublished or private lessons are still being
     * prepared, past ones display muted with no sign-up, and published upcoming
     * ones take sign-ups until the capacity runs out (then the waitlist opens).
     */
    public function offerState(): OfferState
    {
        return match (true) {
            ! $this->isPublished() => OfferState::Preparing,
            $this->isPrivate() => OfferState::Preparing,
            $this->isPast() => OfferState::Inactive,
            $this->isFull() => OfferState::Full,
            $this->waitlistInviteActive() => OfferState::Full,
            default => OfferState::Open,
        };
    }

    public function permalink(): string
    {
        return url('/'.($this->category?->slug ?? '').'/'.$this->slug);
    }
}

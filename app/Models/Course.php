<?php

namespace App\Models;

use App\Enums\CourseSeriesStatus;
use App\Enums\CourseSeriesVisibility;
use App\Enums\OfferState;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\Publishable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Course extends Model
{
    use Auditable, HasFactory, HasUuids, Publishable, SoftDeletes;

    protected $fillable = [
        'category_id',
        'instructor_id',
        'name',
        'slug',
        'description',
        'featured_image',
        'detail_image',
        'early_cancel_hours',
        'drop_in_price',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'early_cancel_hours' => 'integer',
            'drop_in_price' => 'integer',
            'featured_image' => 'integer',
            'detail_image' => 'integer',
            'published_at' => 'datetime',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(CourseCategory::class, 'category_id');
    }

    public function instructor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'instructor_id');
    }

    public function series(): HasMany
    {
        return $this->hasMany(CourseSeries::class);
    }

    /**
     * Courses the given user leads: the ones they instruct, plus the ones where
     * they were put in charge of a single série. Without the second half a
     * lecturer handed one série would have no way to reach it — the série is
     * only ever opened from its course.
     */
    public function scopeLedBy(Builder $query, string $userId): Builder
    {
        return $query->where(fn (Builder $nested) => $nested
            ->where($this->qualifyColumn('instructor_id'), $userId)
            ->orWhereHas('series', fn (Builder $series) => $series->where('instructor_id', $userId)));
    }

    /**
     * Standalone lessons pinned to this course by the optional `course_id` link
     * — the hand-made "ochutnávka" offers.
     */
    public function oneOffEvents(): HasMany
    {
        return $this->hasMany(Lesson::class);
    }

    /**
     * Everything of this course that can be bought as a single lesson: the
     * standalone offers linked to it, plus lessons of its own séries whose free
     * places have been released for sale. Both are the same model, reached by
     * two different routes — a standalone one carries `course_id`, a released
     * lesson belongs through its série.
     */
    public function upcomingPublicLessons(): Builder
    {
        return Lesson::query()
            ->published()
            ->whereDate('lesson_date', '>=', today())
            ->whereNotNull('slug')
            ->whereNotNull('event_category_id')
            ->where(fn (Builder $query) => $query
                ->where('course_id', $this->getKey())
                ->orWhereHas('series', fn (Builder $series) => $series->where('course_id', $this->getKey())))
            ->orderBy('lesson_date')
            ->orderBy('start_time');
    }

    public function invitations()
    {
        return $this->morphMany(Invitation::class, 'inviteable');
    }

    public function reviews()
    {
        return $this->morphMany(Review::class, 'reviewable');
    }

    /**
     * "Chci vědět první" interest subscriptions: notified when a series of this
     * course opens for registration (unlike the offer-level waitlists, nobody
     * is auto-enrolled from here).
     */
    public function waitlistEntries()
    {
        return $this->morphMany(WaitlistEntry::class, 'waitlistable');
    }

    /**
     * The series the public page presents: the soonest one still running or
     * ahead, preferring an enrollable (non-Inactive) series over one that is
     * merely being prepared. Private (invite-only) runs are never presented —
     * they open only through their hidden link. Works on the loaded `series`
     * collection so archive queries can eager-load everything in one go.
     */
    public function currentSeries(): ?CourseSeries
    {
        return $this->series
            ->reject(fn (CourseSeries $series): bool => $series->hasEnded()
                || $series->visibility === CourseSeriesVisibility::Private)
            ->sortBy([
                fn (CourseSeries $a, CourseSeries $b): int => (int) ($a->status === CourseSeriesStatus::Inactive) <=> (int) ($b->status === CourseSeriesStatus::Inactive),
                fn (CourseSeries $a, CourseSeries $b): int => $a->start_date <=> $b->start_date,
            ])
            ->first();
    }

    /**
     * Course-level display state, derived from the presented series ("no current
     * series" shows the muted informational tile per spec §3.6).
     */
    public function offerState(): OfferState
    {
        return $this->currentSeries()?->offerState() ?? OfferState::Inactive;
    }

    public function permalink(): string
    {
        return url('/kurzy/'.$this->slug);
    }

    /**
     * Media-library image id for the landscape card in the archive, falling back
     * to the square one so a course with only one photo still shows it.
     */
    public function cardImage(): ?int
    {
        return $this->featured_image ?? $this->detail_image;
    }

    /**
     * Media-library image id for the square detail hero, falling back to the
     * card photo.
     */
    public function detailImage(): ?int
    {
        return $this->detail_image ?? $this->featured_image;
    }
}

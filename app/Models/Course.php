<?php

namespace App\Models;

use App\Enums\CourseSeriesStatus;
use App\Enums\CourseSeriesVisibility;
use App\Enums\OfferState;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\Publishable;
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
        'max_substitutions',
        'early_cancel_hours',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'max_substitutions' => 'integer',
            'early_cancel_hours' => 'integer',
            'featured_image' => 'integer',
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

    public function oneTimeLessons(): HasMany
    {
        return $this->hasMany(OneTimeLesson::class);
    }

    public function upcomingOneTimeLessons(): HasMany
    {
        return $this->oneTimeLessons()
            ->published()
            ->whereDate('lesson_date', '>=', today())
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
}

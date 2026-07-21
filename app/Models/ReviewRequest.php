<?php

namespace App\Models;

use App\Enums\ReviewRequestChannel;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;

class ReviewRequest extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'user_id',
        'reviewable_type',
        'reviewable_id',
        'channel',
        'token',
        'sent_at',
        'completed_at',
        'review_id',
    ];

    protected function casts(): array
    {
        return [
            'channel' => ReviewRequestChannel::class,
            'sent_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (ReviewRequest $request): void {
            if (blank($request->token)) {
                $request->token = Str::random(48);
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewable(): MorphTo
    {
        return $this->morphTo();
    }

    public function review(): BelongsTo
    {
        return $this->belongsTo(Review::class);
    }

    public function isCompleted(): bool
    {
        return $this->completed_at !== null;
    }

    /**
     * Absolute URL of the public review form for this request's magic-link token.
     */
    public function formUrl(): string
    {
        return route('reviews.form', $this->token);
    }

    /**
     * Human-readable name of the thing being reviewed, shown to the recipient on
     * the form and in the request e-mail (e.g. 'workshop „Zdravá záda"').
     */
    public function targetLabel(): string
    {
        $reviewable = $this->reviewable;

        return match (true) {
            $reviewable instanceof OneOffEvent => 'akci „'.$reviewable->name.'“',
            $reviewable instanceof CourseSeries => 'kurz „'.($reviewable->course?->name ?? $reviewable->name).'“',
            $reviewable instanceof Reservation => 'návštěvu „'.($reviewable->service?->name ?? '').'“'
                .($reviewable->reservation_date !== null ? ' ('.$reviewable->reservation_date->format('d.m.Y').')' : ''),
            default => 'vaši návštěvu',
        };
    }

    /**
     * The model a submitted review should attach to for display/filtering: the
     * course/workshop/service template rather than the concrete instance.
     */
    public function reviewTarget(): ?Model
    {
        $reviewable = $this->reviewable;

        return match (true) {
            // Course-linked events (jednorázové lekce) attach to the course
            // programme; standalone events (workshopy) carry their own reviews.
            $reviewable instanceof OneOffEvent => $reviewable->course ?? $reviewable,
            $reviewable instanceof CourseSeries => $reviewable->course,
            $reviewable instanceof Reservation => $reviewable->service,
            default => $reviewable,
        };
    }
}

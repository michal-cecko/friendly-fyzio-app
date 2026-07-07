<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Course extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'category_id',
        'instructor_id',
        'name',
        'slug',
        'description',
        'max_substitutions',
        'early_cancel_hours',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'max_substitutions' => 'integer',
            'early_cancel_hours' => 'integer',
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

    public function substituteRulesAsSource(): HasMany
    {
        return $this->hasMany(SubstituteRule::class, 'source_course_id');
    }

    public function substituteRulesAsTarget(): HasMany
    {
        return $this->hasMany(SubstituteRule::class, 'target_course_id');
    }

    public function invitations()
    {
        return $this->morphMany(Invitation::class, 'inviteable');
    }

    public function reviews()
    {
        return $this->morphMany(Review::class, 'reviewable');
    }
}

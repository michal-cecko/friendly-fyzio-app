<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubstituteToken extends Model
{
    use Auditable, HasFactory, HasUuids;

    protected $fillable = [
        'client_id',
        'source_lesson_id',
        'expires_at',
        'used_at',
        'used_for_lesson_id',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function sourceLesson(): BelongsTo
    {
        return $this->belongsTo(CourseLesson::class, 'source_lesson_id');
    }

    public function usedForLesson(): BelongsTo
    {
        return $this->belongsTo(CourseLesson::class, 'used_for_lesson_id');
    }
}

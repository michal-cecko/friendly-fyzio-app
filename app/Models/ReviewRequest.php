<?php

namespace App\Models;

use App\Enums\ReviewRequestChannel;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ReviewRequest extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'user_id',
        'reviewable_type',
        'reviewable_id',
        'channel',
        'questionnaire_url',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'channel' => ReviewRequestChannel::class,
            'sent_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewable(): MorphTo
    {
        return $this->morphTo();
    }
}

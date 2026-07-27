<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Observers\ReviewObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[ObservedBy(ReviewObserver::class)]
class Review extends Model
{
    use Auditable, HasFactory, HasUuids;

    public function logTitle(): string
    {
        return 'Recenze '.$this->rating.'★'.($this->client ? ' · '.$this->client->name : '');
    }

    protected $fillable = [
        'client_id',
        'reviewable_type',
        'reviewable_id',
        'rating',
        'content',
        'author_name',
        'visible',
    ];

    /**
     * Visibility is logged as its own published/hidden event by
     * {@see ReviewObserver}, so it must not also appear in the generic diff.
     *
     * @return list<string>
     */
    protected function auditExcept(): array
    {
        return ['visible'];
    }

    protected function casts(): array
    {
        return [
            'visible' => 'boolean',
            'rating' => 'integer',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function reviewable(): MorphTo
    {
        return $this->morphTo();
    }
}

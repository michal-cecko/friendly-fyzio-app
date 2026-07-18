<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

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

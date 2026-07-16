<?php

namespace App\Models;

use App\Observers\ClientNoteObserver;
use Database\Factories\ClientNoteFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[ObservedBy(ClientNoteObserver::class)]
class ClientNote extends Model
{
    /** @use HasFactory<ClientNoteFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'client_id',
        'author_id',
        'reservation_id',
        'content',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /**
     * The reservation this note was written for, when it was added from a
     * reservation rather than directly on the client profile.
     */
    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }
}

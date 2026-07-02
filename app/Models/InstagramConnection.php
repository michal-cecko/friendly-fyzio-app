<?php

namespace App\Models;

use App\Enums\InstagramConnectionStatus;
use Database\Factories\InstagramConnectionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class InstagramConnection extends Model
{
    /** @use HasFactory<InstagramConnectionFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'username',
        'instagram_user_id',
        'access_token',
        'token_expires_at',
        'status',
        'last_synced_at',
        'last_error',
        'is_active',
    ];

    protected $hidden = [
        'access_token',
    ];

    protected function casts(): array
    {
        return [
            'access_token' => 'encrypted',
            'status' => InstagramConnectionStatus::class,
            'token_expires_at' => 'datetime',
            'last_synced_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function posts(): HasMany
    {
        return $this->hasMany(InstagramPost::class);
    }

    /**
     * Active connections that have completed OAuth and hold a usable token —
     * the set the public brick and the scheduled sync draw from.
     */
    public function scopeActiveConnected(Builder $query): Builder
    {
        return $query
            ->where('is_active', true)
            ->where('status', InstagramConnectionStatus::Connected);
    }

    /**
     * True when there is no token or it has already expired, so the account must
     * be re-authorized through the OAuth flow before it can sync again.
     */
    public function needsReauthorization(): bool
    {
        return blank($this->access_token)
            || ($this->token_expires_at !== null && $this->token_expires_at->isPast());
    }

    /**
     * True when the long-lived token is within a week of expiring and should be
     * refreshed on the next sync (long-lived tokens last 60 days).
     */
    public function tokenExpiringSoon(): bool
    {
        return $this->token_expires_at !== null
            && $this->token_expires_at->lte(now()->addWeek());
    }
}

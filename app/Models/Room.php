<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Database\Factories\RoomFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Room extends Model
{
    /** @use HasFactory<RoomFactory> */
    use Auditable, HasFactory, HasUuids;

    protected $fillable = ['building_id', 'name', 'short_name'];

    /**
     * Compact room label for tight UI (calendar chips, selects): the
     * shortcut when one is set, otherwise the full name.
     */
    public function getDisplayShortNameAttribute(): string
    {
        return $this->short_name ?: $this->name;
    }

    /**
     * Full label for room selects: name with the shortcut and building appended.
     */
    public function getPickerLabelAttribute(): string
    {
        $label = $this->short_name ? "{$this->name} ({$this->short_name})" : $this->name;

        return $this->building ? "{$label} · {$this->building->name}" : $label;
    }

    public function building(): BelongsTo
    {
        return $this->belongsTo(Building::class);
    }

    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class, 'service_rooms');
    }

    public function workBlocks(): HasMany
    {
        return $this->hasMany(TherapistWorkBlock::class);
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    public function blockings(): HasMany
    {
        return $this->hasMany(RoomBlocking::class);
    }
}

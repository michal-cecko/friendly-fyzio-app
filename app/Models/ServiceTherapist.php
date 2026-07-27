<?php

namespace App\Models;

use App\Support\Reservations\BreakResolver;
use App\Support\Settings;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * A therapist's assignment to a service — and, the reason it is a model rather
 * than a bare pivot row, the break they take after that particular service.
 *
 * `break_blocks` is counted in reservation blocks ({@see Settings::blockMinutes()}),
 * and a null means "whatever this therapist's profile says". The resolution
 * itself lives in {@see BreakResolver}, which answers
 * for a whole day's worth of therapists without loading models.
 */
class ServiceTherapist extends Pivot
{
    use HasUuids;

    protected $table = 'service_therapists';

    public $timestamps = false;

    protected $fillable = [
        'service_id',
        'therapist_id',
        'break_blocks',
    ];

    protected function casts(): array
    {
        return [
            'break_blocks' => 'integer',
        ];
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function therapist(): BelongsTo
    {
        return $this->belongsTo(StaffProfile::class, 'therapist_id');
    }
}

<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubstituteRule extends Model
{
    use Auditable, HasFactory, HasUuids;

    protected $fillable = ['source_series_id', 'target_series_id'];

    public function sourceSeries(): BelongsTo
    {
        return $this->belongsTo(CourseSeries::class, 'source_series_id');
    }

    public function targetSeries(): BelongsTo
    {
        return $this->belongsTo(CourseSeries::class, 'target_series_id');
    }
}

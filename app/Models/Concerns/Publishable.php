<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

/**
 * Published state driven solely by `published_at`: a null or future date means
 * a draft (hidden from the public, previewable by staff). Shared by every model
 * that gates its public visibility this way (Page, ServiceCategory).
 */
trait Publishable
{
    public function initializePublishable(): void
    {
        $this->mergeCasts(['published_at' => 'datetime']);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->whereNotNull('published_at')->where('published_at', '<=', now());
    }

    public function isPublished(): bool
    {
        return $this->published_at !== null && $this->published_at->lessThanOrEqualTo(now());
    }
}

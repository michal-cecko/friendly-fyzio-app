<?php

namespace App\Observers;

use App\Models\Review;
use App\Support\ActivityLog\LogActivity;

/**
 * Publishing a review is a decision about what the public sees, so it gets its
 * own semantic log entry instead of hiding inside a generic attribute diff.
 * {@see Review::auditExcept()} keeps `visible` out of that diff so a toggle
 * files exactly one entry.
 */
class ReviewObserver
{
    public function updated(Review $review): void
    {
        if (! $review->wasChanged('visible')) {
            return;
        }

        LogActivity::record(
            $review->visible ? 'review_published' : 'review_hidden',
            $review,
            $review->visible ? 'Recenze zveřejněna na webu' : 'Recenze skryta z webu',
        );
    }
}

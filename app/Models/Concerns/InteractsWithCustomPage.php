<?php

namespace App\Models\Concerns;

use App\Contracts\HasPublicPage;
use App\Models\Page;
use Illuminate\Database\Eloquent\Relations\MorphOne;

/**
 * Gives a model the optional custom override page (polymorphic one-to-one).
 * Use together with implementing {@see HasPublicPage} and a
 * `permalink` accessor.
 */
trait InteractsWithCustomPage
{
    public function customPage(): MorphOne
    {
        return $this->morphOne(Page::class, 'pageable');
    }
}

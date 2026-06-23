<?php

namespace App\Contracts;

use Illuminate\Database\Eloquent\Relations\MorphOne;

/**
 * A model that has a default public page AND may be overridden by a custom CMS
 * page attached via the polymorphic `pageable` relationship. The attached page's
 * permalink derives from this owner, so the two URLs never diverge.
 */
interface HasPublicPage extends HasPermalink
{
    public function customPage(): MorphOne;
}

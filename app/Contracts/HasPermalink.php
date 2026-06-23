<?php

namespace App\Contracts;

use Illuminate\Database\Eloquent\Casts\Attribute;

/**
 * A model that has a canonical public URL, exposed as a `permalink` accessor
 * (`$model->permalink`). Always use that accessor for links — never hand-build
 * the path — so URLs stay in one place.
 */
interface HasPermalink
{
    public function permalink(): Attribute;
}

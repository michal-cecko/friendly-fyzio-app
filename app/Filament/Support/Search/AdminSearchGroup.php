<?php

namespace App\Filament\Support\Search;

use BackedEnum;
use Illuminate\Support\Collection;

final readonly class AdminSearchGroup
{
    /**
     * @param  Collection<int, AdminSearchResult>  $results
     */
    public function __construct(
        public string $label,
        public string|BackedEnum|null $icon,
        public Collection $results,
    ) {}
}

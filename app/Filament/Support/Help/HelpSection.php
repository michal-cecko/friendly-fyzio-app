<?php

namespace App\Filament\Support\Help;

use Illuminate\Support\Collection;

/**
 * A group of help topics — one directory under resources/help, described by its
 * optional `_section.md` front-matter.
 */
final readonly class HelpSection
{
    /**
     * @param  Collection<int, HelpTopic>  $topics
     */
    public function __construct(
        public string $id,
        public string $title,
        public ?string $icon,
        public Collection $topics,
    ) {}
}

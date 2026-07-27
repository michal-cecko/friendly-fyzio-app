<?php

namespace App\Filament\Support\Actions;

use App\Contracts\HasPermalink;
use App\Models\Course;
use App\Models\Lesson;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;

/**
 * "Open public page" button for any resource whose model has a public URL.
 * Links to the record's canonical permalink in a new tab.
 */
class OpenPublicPageAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'visit';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label('Zobrazit stránku')
            ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
            ->color('gray')
            ->url(fn (Model $record): ?string => self::permalinkFor($record))
            ->openUrlInNewTab()
            ->visible(fn (Model $record): bool => filled(self::permalinkFor($record)));
    }

    /**
     * Course and Lesson expose their permalink as a plain method; every other
     * destination implements {@see HasPermalink} and exposes it as an attribute.
     * Both need their URL segments filled in before the link points anywhere.
     */
    private static function permalinkFor(Model $record): ?string
    {
        if ($record instanceof HasPermalink) {
            return $record->permalink;
        }

        if ($record instanceof Course) {
            return filled($record->slug) ? $record->permalink() : null;
        }

        if ($record instanceof Lesson) {
            return filled($record->slug) && $record->category !== null ? $record->permalink() : null;
        }

        return null;
    }
}

<?php

namespace App\Filament\Support\Actions;

use App\Contracts\HasPermalink;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;

/**
 * "Open public page" button for any resource whose model implements
 * {@see HasPermalink}. Links to the record's canonical `permalink` in a new tab.
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
            ->url(fn (Model $record): ?string => $record instanceof HasPermalink ? $record->permalink : null)
            ->openUrlInNewTab()
            ->visible(fn (Model $record): bool => $record instanceof HasPermalink && filled($record->permalink));
    }
}

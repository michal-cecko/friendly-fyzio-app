<?php

namespace App\Filament\Support\Actions;

use App\Models\Page;
use App\Models\Service;
use Filament\Actions\Action;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Icons\Heroicon;

/**
 * "Copy content from another page" for any section holding a Mason editor: pulls
 * the bricks of an existing page into the editor so a new page can start from a
 * finished one instead of an empty canvas. Only form state is touched — the
 * source page is never modified and nothing is persisted until the form is saved.
 *
 * Attach as a header action of the section wrapping the Mason field.
 */
class CopyPageContentAction extends Action
{
    /**
     * State path of the Mason field this action writes to, relative to the
     * section the action is attached to.
     */
    protected string $contentField = 'content';

    public static function getDefaultName(): ?string
    {
        return 'copyPageContent';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label('Kopírovat obsah z jiné stránky')
            ->icon(Heroicon::OutlinedDocumentDuplicate)
            ->color('gray')
            ->modalHeading('Kopírovat obsah z jiné stránky')
            ->modalDescription('Přenese bloky vybrané stránky do tohoto editoru. Zdrojová stránka zůstane beze změny a nic se neuloží, dokud formulář sami neuložíte.')
            ->modalSubmitActionLabel('Vložit bloky')
            ->schema([
                Select::make('source_page_id')
                    ->label('Zdrojová stránka')
                    ->options(fn (): array => self::groupedOptions())
                    ->searchable()
                    ->required(),
                Radio::make('mode')
                    ->label('Jak s obsahem naložit')
                    ->options([
                        'replace' => 'Nahradit stávající obsah',
                        'append' => 'Připojit na konec',
                    ])
                    ->default('replace')
                    ->required(),
            ])
            ->action(function (array $data, Get $get, Set $set): void {
                $source = Page::find($data['source_page_id']);

                if ($source === null) {
                    Notification::make()
                        ->title('Zdrojová stránka už neexistuje')
                        ->danger()
                        ->send();

                    return;
                }

                $bricks = $source->content ?: [];

                if (($data['mode'] ?? 'replace') === 'append') {
                    $bricks = [...(array) ($get($this->contentField) ?: []), ...$bricks];
                }

                $set($this->contentField, $bricks);

                Notification::make()
                    ->title('Obsah vložen ze stránky „'.$source->title.'“')
                    ->body('Nezapomeňte formulář uložit.')
                    ->success()
                    ->send();
            });
    }

    /**
     * Point the action at a Mason field that is not called `content`.
     */
    public function contentField(string $statePath): static
    {
        $this->contentField = $statePath;

        return $this;
    }

    /**
     * Every page, grouped by what it is the public page for, so the finished
     * service and category pages are findable next to the standalone ones.
     *
     * @return array<string, array<string, string>>
     */
    private static function groupedOptions(): array
    {
        $groups = [
            'Samostatné stránky' => [],
            'Stránky služeb' => [],
            'Stránky kategorií' => [],
        ];

        foreach (Page::query()->with('pageable')->orderBy('title')->get() as $page) {
            $group = match (true) {
                $page->pageable === null => 'Samostatné stránky',
                $page->pageable instanceof Service => 'Stránky služeb',
                default => 'Stránky kategorií',
            };

            $groups[$group][$page->getKey()] = $page->title;
        }

        return array_filter($groups);
    }
}

<?php

namespace App\Filament\Pages;

use App\Filament\Support\Help\HelpExport;
use App\Filament\Support\Help\HelpRepository;
use App\Filament\Support\Help\HelpSearch;
use App\Filament\Support\Help\HelpSearchResult;
use App\Filament\Support\Help\HelpSection;
use App\Filament\Support\Help\HelpTopic;
use App\Providers\Filament\AdminPanelProvider;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Panel;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * In-app documentation: a topic tree and a search box beside the open article.
 *
 * Reached from the pinned entry at the bottom of the sidebar rather than from the
 * navigation itself ({@see AdminPanelProvider}), so it does
 * not register navigation of its own.
 *
 * Deliberately not access-gated: therapists and lecturers see a narrower panel than
 * admins and are the people most likely to need the manual.
 */
class Help extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQuestionMarkCircle;

    protected static ?string $title = 'Nápověda';

    protected string $view = 'filament.pages.help';

    #[Url(as: 'tema')]
    public string $topic = '';

    #[Url(as: 'q')]
    public string $q = '';

    public static function getSlug(?Panel $panel = null): string
    {
        return 'napoveda';
    }

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    /**
     * The whole manual as one markdown file, for feeding to an AI assistant.
     * Admins only — the export is the entire panel's documentation in one place.
     *
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('download')
                ->label('Stáhnout příručku')
                ->tooltip('Celá nápověda v jednom .md souboru — dá se vložit do AI asistenta a ptát se ho.')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->color('gray')
                ->visible(fn (): bool => (auth()->user()?->isAdmin() ?? false) && $this->sections->isNotEmpty())
                ->action(function (): StreamedResponse {
                    $export = app(HelpExport::class);

                    return response()->streamDownload(
                        fn () => print $export->markdown(),
                        $export->filename(),
                        ['Content-Type' => 'text/markdown; charset=UTF-8'],
                    );
                }),
        ];
    }

    /**
     * @return Collection<int, HelpSection>
     */
    #[Computed]
    public function sections(): Collection
    {
        return app(HelpRepository::class)->sections();
    }

    /**
     * The open article. An unknown or empty `tema` falls back to the first topic
     * rather than erroring — help that 404s is worse than help that starts over.
     */
    #[Computed]
    public function current(): ?HelpTopic
    {
        $repository = app(HelpRepository::class);

        return $repository->find($this->topic) ?? $repository->first();
    }

    /**
     * @return Collection<int, HelpSearchResult>
     */
    #[Computed]
    public function results(): Collection
    {
        return app(HelpSearch::class)->search($this->q);
    }

    public function openTopic(string $id): void
    {
        $this->topic = $id;
        $this->q = '';

        unset($this->current, $this->results);
    }
}

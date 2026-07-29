<?php

namespace App\Filament\Pages;

use App\Filament\Support\Help\HelpExport;
use App\Filament\Support\Help\HelpRepository;
use App\Filament\Support\Help\HelpSearch;
use App\Filament\Support\Help\HelpSearchResult;
use App\Filament\Support\Help\HelpSection;
use App\Filament\Support\Help\HelpTopic;
use App\Filament\Support\Help\HelpVersion;
use App\Filament\Support\Help\HelpVersions;
use App\Filament\Support\Search\SearchHighlighter;
use App\Providers\Filament\AdminPanelProvider;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
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
 *
 * The version rides in the path (`/admin/napoveda/2026-07-29`) rather than in a
 * query string, so an archived article can be linked to and the address bar says
 * which manual is on screen. `latest` — and a bare `/admin/napoveda` — is the live
 * tree; everything else is a snapshot from {@see HelpVersions}.
 */
class Help extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQuestionMarkCircle;

    protected static ?string $title = 'Nápověda';

    protected string $view = 'filament.pages.help';

    public string $version = HelpVersions::LATEST;

    #[Url(as: 'tema')]
    public string $topic = '';

    #[Url(as: 'q')]
    public string $q = '';

    public static function getSlug(?Panel $panel = null): string
    {
        return 'napoveda';
    }

    public static function getRoutePath(Panel $panel): string
    {
        return '/'.static::getSlug($panel).'/{version?}';
    }

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public function mount(?string $version = null): void
    {
        $this->version = blank($version) ? HelpVersions::LATEST : $version;
    }

    /**
     * Says which manual is open, so an archived version cannot be mistaken for
     * the current one at a glance.
     */
    public function getHeading(): string
    {
        return $this->archived === null
            ? 'Nápověda'
            : 'Nápověda — archiv '.$this->archived->label();
    }

    /**
     * @return array<int, Action|ActionGroup>
     */
    protected function getHeaderActions(): array
    {
        return [
            $this->versionAction(),
            $this->downloadAction(),
        ];
    }

    /**
     * The version picker. Always rendered — even with no archive yet it names the
     * manual on screen, and a dropdown that appears only sometimes is a worse
     * surprise than one holding a single entry.
     */
    protected function versionAction(): ActionGroup
    {
        $items = [$this->versionLink(null)];

        foreach ($this->versions as $version) {
            $items[] = $this->versionLink($version);
        }

        return ActionGroup::make($items)
            ->label($this->archived === null ? 'Verze: aktuální' : 'Verze: '.$this->archived->label())
            ->tooltip('Starší verze příručky, uložené vždy k jednomu commitu.')
            ->icon(Heroicon::OutlinedClock)
            ->color('gray')
            ->button();
    }

    protected function versionLink(?HelpVersion $version): Action
    {
        $id = $version?->id ?? HelpVersions::LATEST;
        $isOpen = $this->version === $id;

        return Action::make('version_'.str_replace('-', '_', $id))
            ->label($version === null ? 'Aktuální' : $version->label())
            // The snapshot's line carries its commit; the live tree has none to name.
            ->badge($version?->commit)
            ->icon($isOpen ? Heroicon::OutlinedCheck : null)
            ->color($isOpen ? 'primary' : 'gray')
            ->url(static::getUrl(['version' => $id]));
    }

    /**
     * The manual on screen as one markdown file, for feeding to an AI assistant.
     * Admins only — the export is the entire panel's documentation in one place.
     */
    protected function downloadAction(): Action
    {
        return Action::make('download')
            ->label('Stáhnout příručku')
            ->tooltip('Zobrazená verze v jednom .md souboru — dá se vložit do AI asistenta a ptát se ho.')
            ->icon(Heroicon::OutlinedArrowDownTray)
            ->color('gray')
            ->visible(fn (): bool => (auth()->user()?->isAdmin() ?? false) && $this->sections->isNotEmpty())
            ->action(function (): StreamedResponse {
                $export = new HelpExport($this->repository(), $this->archived);

                return response()->streamDownload(
                    fn () => print $export->markdown(),
                    $export->filename(),
                    ['Content-Type' => 'text/markdown; charset=UTF-8'],
                );
            });
    }

    /**
     * @return Collection<int, HelpVersion>
     */
    #[Computed]
    public function versions(): Collection
    {
        return app(HelpVersions::class)->all();
    }

    /**
     * The snapshot being read, or null while reading the live manual. An id that
     * matches no snapshot resolves to null — the page then shows the current
     * manual rather than 404ing, the same way an unknown topic falls back.
     */
    #[Computed]
    public function archived(): ?HelpVersion
    {
        return app(HelpVersions::class)->find($this->version);
    }

    /**
     * @return Collection<int, HelpSection>
     */
    #[Computed]
    public function sections(): Collection
    {
        return $this->repository()->sections();
    }

    /**
     * The open article. An unknown or empty `tema` falls back to the first topic
     * rather than erroring — help that 404s is worse than help that starts over.
     */
    #[Computed]
    public function current(): ?HelpTopic
    {
        $repository = $this->repository();

        return $repository->find($this->topic) ?? $repository->first();
    }

    /**
     * @return Collection<int, HelpSearchResult>
     */
    #[Computed]
    public function results(): Collection
    {
        return (new HelpSearch($this->repository(), app(SearchHighlighter::class)))->search($this->q);
    }

    public function openTopic(string $id): void
    {
        $this->topic = $id;
        $this->q = '';

        unset($this->current, $this->results);
    }

    /**
     * Reading the tree of whichever version is open — the live one by default.
     */
    protected function repository(): HelpRepository
    {
        return app(HelpVersions::class)->repository($this->version);
    }
}

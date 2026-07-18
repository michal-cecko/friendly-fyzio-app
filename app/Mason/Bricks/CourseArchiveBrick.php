<?php

namespace App\Mason\Bricks;

use App\Mason\Support\Fields;
use Awcodes\Mason\Brick;
use Filament\Actions\Action;
use Filament\Forms\Components\Toggle;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

/**
 * Data-driven archive of movement courses and one-time lessons: type switcher,
 * category pills, availability toggle, text search and pagination — all
 * URL-bound (handled by the CourseArchive Livewire component). Content comes
 * live from the Kurzy cluster; this brick only configures the section shell.
 */
class CourseArchiveBrick extends Brick
{
    public static function getId(): string
    {
        return 'course-archive';
    }

    public static function getLabel(): string
    {
        return 'Archiv kurzů a lekcí';
    }

    public static function getIcon(): string|Heroicon|Htmlable|null
    {
        return Heroicon::OutlinedAcademicCap;
    }

    public static function toHtml(array $config, ?array $data = null): ?string
    {
        return view('bricks.course-archive', ['config' => $config])->render();
    }

    public static function configureBrickAction(Action $action): Action
    {
        return $action
            ->slideOver()
            ->schema([
                ...Fields::heading(),
                Toggle::make('show_type_switch')
                    ->label('Přepínač kurzy / jednorázové lekce')
                    ->default(true),
                Toggle::make('show_filters')
                    ->label('Filtr kategorií a dostupnosti')
                    ->default(true),
                Toggle::make('show_search')
                    ->label('Vyhledávací pole')
                    ->default(true),
            ]);
    }
}

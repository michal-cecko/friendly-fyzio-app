<?php

namespace App\Filament\Support\Concerns;

use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Livewire;
use Filament\Schemas\Components\Section;
use Filament\Support\Icons\Heroicon;

/**
 * Renders a page's relation managers stacked, each inside its own titled,
 * icon-led section, instead of Filament's default tab strip.
 *
 * Apply to a ViewRecord or EditRecord page. The section heading and icon are
 * taken from each relation manager's own `getTitle()` / `$icon`.
 */
trait RendersRelationManagersAsSections
{
    public function getRelationManagersContentComponent(): Component
    {
        $managers = $this->getRelationManagers();

        if (empty($managers)) {
            return Group::make()->hidden();
        }

        $ownerRecord = $this->getRecord();
        $livewireData = ['ownerRecord' => $ownerRecord, 'pageClass' => static::class];

        return Group::make(
            collect($managers)
                ->map(function ($manager) use ($livewireData, $ownerRecord): Section {
                    $managerClass = $this->normalizeRelationManagerClass($manager);

                    return Section::make($managerClass::getTitle($ownerRecord, static::class))
                        ->icon($managerClass::getIcon($ownerRecord, static::class) ?? Heroicon::OutlinedRectangleStack)
                        ->schema([
                            Livewire::make($managerClass, [...$livewireData, ...$managerClass::getDefaultProperties()])
                                ->key($managerClass),
                        ]);
                })
                ->values()
                ->all()
        );
    }
}

<?php

namespace App\Filament\Clusters\System\Pages;

use App\Filament\Clusters\System\SystemCluster;
use App\Models\Setting;
use BackedEnum;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

class Settings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static ?string $cluster = SystemCluster::class;

    protected static ?string $navigationLabel = 'Nastavení';

    protected static ?string $title = 'Nastavení';

    protected static ?int $navigationSort = 100;

    protected string $view = 'filament.pages.settings';

    /**
     * Form state, keyed by setting key.
     *
     * @var array<string, mixed>
     */
    public array $data = [];

    public function mount(): void
    {
        $this->form->fill(
            $this->settings()
                ->mapWithKeys(fn (Setting $setting): array => [$setting->key => $setting->typedValue])
                ->all()
        );
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components(
                $this->settings()
                    ->groupBy(fn (Setting $setting): string => $setting->group ?? 'Ostatní')
                    ->map(fn (Collection $group, string $heading): Section => Section::make($heading)
                        ->schema($group->map($this->componentFor(...))->all()))
                    ->values()
                    ->all()
            );
    }

    public function save(): void
    {
        // Setting keys contain dots, which Filament expands into nested state; flatten back.
        $data = Arr::dot($this->form->getState());

        $this->settings()->each(function (Setting $setting) use ($data): void {
            $setting->update(['value' => $setting->type->serialize($data[$setting->key] ?? null)]);
        });

        Notification::make()->success()->title('Nastavení uloženo')->send();
    }

    /**
     * Build the Filament field for a single setting, applying its per-field config.
     */
    protected function componentFor(Setting $setting): mixed
    {
        $component = $setting->type->formComponent($setting->key)
            ->label($setting->label)
            ->helperText($setting->description);

        $config = $setting->config ?? [];

        if ($component instanceof TextInput) {
            if (isset($config['min'])) {
                $component->minValue($config['min']);
            }

            if (isset($config['step'])) {
                $component->step($config['step']);
            }

            if (isset($config['suffix'])) {
                $component->suffix($config['suffix']);
            }
        }

        return $component;
    }

    /**
     * @return Collection<int, Setting>
     */
    protected function settings(): Collection
    {
        return Setting::query()->orderBy('group')->orderBy('sort')->get();
    }
}

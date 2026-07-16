<?php

namespace App\Filament\Clusters\System\Pages\Concerns;

use App\Filament\Clusters\System\SystemCluster;
use App\Models\Setting;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

/**
 * Shared behaviour for a single-group settings page. Each concrete page manages
 * exactly one Setting `group` (Fakturace, Platby, …) so every group is its own
 * standalone page in the Nastavení cluster sidebar. Setting keys contain dots,
 * which Filament expands into nested state; Arr::undot/Arr::dot bridge that.
 */
abstract class SettingsGroupPage extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static ?string $cluster = SystemCluster::class;

    protected string $view = 'filament.pages.settings';

    /**
     * Form state, keyed by setting key.
     *
     * @var array<string, mixed>
     */
    public array $data = [];

    /** The Setting `group` this page manages. */
    abstract protected static function group(): string;

    /**
     * A save button in the page header so long forms don't need scrolling to save.
     *
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('save')
                ->label('Uložit')
                ->icon(Heroicon::OutlinedCheck)
                ->keyBindings(['mod+s'])
                ->action(fn () => $this->save()),
        ];
    }

    public function mount(): void
    {
        $this->form->fill(
            Arr::undot(
                $this->settings()
                    ->mapWithKeys(fn (Setting $setting): array => [$setting->key => $setting->typedValue])
                    ->all()
            )
        );
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make()->schema(
                    $this->settings()
                        ->map($this->componentFor(...))
                        ->all()
                ),
            ]);
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
            ->helperText($setting->description)
            ->hintIcon(Heroicon::OutlinedClock)
            ->hint($setting->updated_at !== null
                ? 'Upraveno '.$setting->updated_at->format('d.m.Y H:i')
                : null);

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
        return Setting::query()
            ->where('group', static::group())
            ->orderBy('sort')
            ->get();
    }
}

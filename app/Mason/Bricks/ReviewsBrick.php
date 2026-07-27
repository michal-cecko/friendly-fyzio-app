<?php

namespace App\Mason\Bricks;

use App\Mason\Support\Fields;
use App\Models\Review;
use Awcodes\Mason\Brick;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Collection;

class ReviewsBrick extends Brick
{
    public static function getId(): string
    {
        return 'reviews';
    }

    public static function getLabel(): string
    {
        return 'Recenze';
    }

    public static function getIcon(): string|Heroicon|Htmlable|null
    {
        return Heroicon::OutlinedStar;
    }

    public static function toHtml(array $config, ?array $data = null): ?string
    {
        return view('bricks.reviews', [
            'config' => $config,
            'reviews' => self::resolveReviews($config),
        ])->render();
    }

    public static function configureBrickAction(Action $action): Action
    {
        return $action
            ->slideOver()
            ->schema([
                ...Fields::heading(),
                Select::make('background')
                    ->label('Pozadí')
                    ->options([
                        'alt' => 'Světle růžové',
                        'white' => 'Bílé',
                    ])
                    ->default('alt'),
                Select::make('source')
                    ->label('Zdroj recenzí')
                    ->options([
                        'all' => 'Automaticky (nejnovější)',
                        'specific' => 'Vybrané recenze',
                    ])
                    ->default('all')
                    ->live(),
                Select::make('reviewable_type')
                    ->label('Filtr podle typu')
                    ->placeholder('Všechny')
                    ->options([
                        'course' => 'Kurzy',
                        'lesson' => 'Lekce',
                        'service' => 'Služby',
                    ])
                    ->visible(fn (Get $get): bool => ($get('source') ?? 'all') === 'all'),
                Select::make('min_rating')
                    ->label('Minimální hodnocení')
                    ->placeholder('Bez omezení')
                    ->options([
                        3 => '3 ★ a více',
                        4 => '4 ★ a více',
                        5 => 'Jen 5 ★',
                    ])
                    ->visible(fn (Get $get): bool => ($get('source') ?? 'all') === 'all'),
                TextInput::make('limit')
                    ->label('Počet recenzí')
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(12)
                    ->default(3)
                    ->visible(fn (Get $get): bool => ($get('source') ?? 'all') === 'all'),
                Select::make('review_ids')
                    ->label('Recenze')
                    ->multiple()
                    ->options(fn (): array => Review::query()
                        ->where('visible', true)
                        ->latest()
                        ->limit(100)
                        ->get()
                        ->mapWithKeys(fn (Review $review): array => [
                            $review->id => str($review->author_name.' ('.$review->rating.'★) — '.$review->content)->limit(50),
                        ])
                        ->all())
                    ->visible(fn (Get $get): bool => $get('source') === 'specific'),
            ]);
    }

    /**
     * Resolve the reviews to display: an explicit hand-picked set (in the chosen
     * order) or the latest visible reviews, optionally filtered by type and rating.
     * Only published (visible) reviews are ever returned.
     *
     * @param  array<string, mixed>  $config
     * @return Collection<int, Review>
     */
    protected static function resolveReviews(array $config): Collection
    {
        $query = Review::query()->where('visible', true);

        if (($config['source'] ?? 'all') === 'specific') {
            $ids = array_values(array_filter((array) ($config['review_ids'] ?? [])));

            if ($ids === []) {
                return new Collection;
            }

            $reviews = $query->whereIn('id', $ids)->get()->keyBy('id');

            return collect($ids)
                ->map(fn (string $id): ?Review => $reviews->get($id))
                ->filter()
                ->values();
        }

        if (filled($config['reviewable_type'] ?? null)) {
            // Legacy configs may still say 'workshop' — those reviews were
            // migrated to the unified lesson alias.
            $type = $config['reviewable_type'] === 'workshop' ? 'lesson' : $config['reviewable_type'];

            $query->where('reviewable_type', $type);
        }

        if (filled($config['min_rating'] ?? null)) {
            $query->where('rating', '>=', (int) $config['min_rating']);
        }

        return $query
            ->latest()
            ->limit((int) ($config['limit'] ?? 3))
            ->get();
    }
}

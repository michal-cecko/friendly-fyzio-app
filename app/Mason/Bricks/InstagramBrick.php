<?php

namespace App\Mason\Bricks;

use App\Mason\Support\Fields;
use App\Mason\Support\LinkPickerField;
use App\Models\InstagramConnection;
use App\Models\InstagramPost;
use Awcodes\Mason\Brick;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Collection;

class InstagramBrick extends Brick
{
    public static function getId(): string
    {
        return 'instagram';
    }

    public static function getLabel(): string
    {
        return 'Instagram';
    }

    public static function getIcon(): string|Heroicon|Htmlable|null
    {
        return Heroicon::OutlinedCamera;
    }

    public static function toHtml(array $config, ?array $data = null): ?string
    {
        return view('bricks.instagram', [
            'config' => $config,
            'posts' => self::resolvePosts($config),
        ])->render();
    }

    public static function configureBrickAction(Action $action): Action
    {
        return $action
            ->slideOver()
            ->schema([
                ...Fields::heading(),
                Select::make('connection_id')
                    ->label('Instagram účet')
                    ->options(fn (): array => InstagramConnection::query()
                        ->activeConnected()
                        ->pluck('username', 'id')
                        ->map(fn (?string $username): string => '@'.$username)
                        ->all())
                    ->helperText('Účty spravujete v sekci Obsah → Instagram účty.')
                    ->live(),
                Select::make('source')
                    ->label('Zdroj příspěvků')
                    ->options([
                        'latest' => 'Nejnovější',
                        'specific' => 'Vybrané příspěvky',
                    ])
                    ->default('latest')
                    ->live(),
                TextInput::make('count')
                    ->label('Počet příspěvků')
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(12)
                    ->default(4)
                    ->visible(fn (Get $get): bool => ($get('source') ?? 'latest') === 'latest'),
                Select::make('post_ids')
                    ->label('Příspěvky')
                    ->multiple()
                    ->options(fn (Get $get): array => InstagramPost::query()
                        ->where('instagram_connection_id', $get('connection_id'))
                        ->latest('posted_at')
                        ->limit(50)
                        ->get()
                        ->mapWithKeys(fn (InstagramPost $post): array => [
                            $post->id => $post->posted_at->format('j. n. Y').' — '.str($post->caption ?? '')->limit(40),
                        ])
                        ->all())
                    ->visible(fn (Get $get): bool => $get('source') === 'specific'),
                LinkPickerField::make('cta_', 'Tlačítko', withText: true),
            ]);
    }

    /**
     * Resolve the posts to display: an explicit hand-picked set (in the chosen
     * order) or the latest N for the selected connection. Empty when no connection
     * is configured, so the view falls back to legacy manually-picked images.
     *
     * @param  array<string, mixed>  $config
     * @return Collection<int, InstagramPost>
     */
    protected static function resolvePosts(array $config): Collection
    {
        $connectionId = $config['connection_id'] ?? null;

        if (blank($connectionId)) {
            return new Collection;
        }

        if (($config['source'] ?? 'latest') === 'specific') {
            $ids = array_values(array_filter((array) ($config['post_ids'] ?? [])));

            if ($ids === []) {
                return new Collection;
            }

            $posts = InstagramPost::query()
                ->where('instagram_connection_id', $connectionId)
                ->whereIn('id', $ids)
                ->get()
                ->keyBy('id');

            return collect($ids)
                ->map(fn (string $id): ?InstagramPost => $posts->get($id))
                ->filter()
                ->values();
        }

        return InstagramPost::query()
            ->where('instagram_connection_id', $connectionId)
            ->latest('posted_at')
            ->limit((int) ($config['count'] ?? 4))
            ->get();
    }
}

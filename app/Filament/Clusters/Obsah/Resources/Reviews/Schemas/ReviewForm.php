<?php

namespace App\Filament\Clusters\Obsah\Resources\Reviews\Schemas;

use App\Enums\UserRole;
use App\Filament\Support\Schemas\PresenceBanner;
use App\Models\Course;
use App\Models\OneOffEvent;
use App\Models\Service;
use App\Models\User;
use Filament\Forms\Components\MorphToSelect;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class ReviewForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                PresenceBanner::make(),
                Section::make('Recenze')
                    ->columns(2)
                    ->schema([
                        ToggleButtons::make('rating')
                            ->label('Hodnocení')
                            ->options([
                                1 => '1',
                                2 => '2',
                                3 => '3',
                                4 => '4',
                                5 => '5',
                            ])
                            ->inline()
                            ->default(5)
                            ->required()
                            ->columnSpanFull(),
                        Select::make('client_id')
                            ->label('Klient (nepovinné)')
                            ->helperText('Propojí recenzi s existujícím klientem.')
                            ->relationship(
                                'client',
                                'name',
                                fn (Builder $query) => $query->where('role', UserRole::Customer),
                            )
                            ->searchable()
                            ->preload()
                            ->live()
                            ->afterStateUpdated(function (Set $set, ?string $state): void {
                                if ($state === null) {
                                    return;
                                }

                                $name = User::find($state)?->name;

                                if (filled($name)) {
                                    $set('author_name', $name);
                                }
                            }),
                        TextInput::make('author_name')
                            ->label('Jméno autora')
                            ->required()
                            ->maxLength(255),
                        Textarea::make('content')
                            ->label('Text recenze')
                            ->required()
                            ->rows(5)
                            ->columnSpanFull(),
                        MorphToSelect::make('reviewable')
                            ->label('Vztahuje se k (nepovinné)')
                            ->types([
                                MorphToSelect\Type::make(Course::class)
                                    ->titleAttribute('name')
                                    ->label('Kurz'),
                                MorphToSelect\Type::make(OneOffEvent::class)
                                    ->titleAttribute('name')
                                    ->label('Jednorázová akce'),
                                MorphToSelect\Type::make(Service::class)
                                    ->titleAttribute('name')
                                    ->label('Služba'),
                            ])
                            ->searchable()
                            ->columnSpanFull(),
                        Toggle::make('visible')
                            ->label('Zveřejnit na webu')
                            ->default(true),
                    ]),
            ]);
    }
}

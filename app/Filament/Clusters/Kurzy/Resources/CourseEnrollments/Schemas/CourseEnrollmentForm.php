<?php

namespace App\Filament\Clusters\Kurzy\Resources\CourseEnrollments\Schemas;

use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

/**
 * Enrollments are created here and never edited: an enrollment tied to a
 * different série or client is a different enrollment, so both are fixed at
 * creation. Its status is a state machine driven by the cancel/revert actions
 * and the waitlist, and its payment_status + paid_at are derived from the
 * payments — none of them belong in a form.
 */
class CourseEnrollmentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Přihláška')
                    ->columns(2)
                    ->schema([
                        Select::make('series_id')
                            ->label('Série')
                            ->relationship('series', 'name')
                            ->searchable()
                            ->preload()
                            ->native(false)
                            ->required(),
                        Select::make('client_id')
                            ->label('Klient')
                            ->relationship('client', 'name', fn (Builder $query): Builder => $query->customers())
                            ->searchable()
                            ->preload()
                            ->native(false)
                            ->required(),
                    ]),
            ]);
    }
}

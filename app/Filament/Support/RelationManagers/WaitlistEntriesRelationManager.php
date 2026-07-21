<?php

namespace App\Filament\Support\RelationManagers;

use App\Models\Course;
use App\Models\CourseSeries;
use App\Models\OneOffEvent;
use App\Models\WaitlistEntry;
use App\Support\Enrollments\PromoteFromWaitlist;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Shared waitlist tab for every enrollable offer (course series, one-time
 * lesson, workshop) and for courses themselves (there the entries are
 * "notify me when registration opens" interest sign-ups). Entries come from
 * the public site. When the offer has automatic promotion on, freed spots are
 * filled for you; with it off, staff use the "Přidat z čekací listiny" button
 * to offer the next in line a spot manually.
 */
class WaitlistEntriesRelationManager extends RelationManager
{
    protected static string $relationship = 'waitlistEntries';

    protected static ?string $title = 'Čekací listina';

    protected static string|BackedEnum|null $icon = Heroicon::OutlinedQueueList;

    /**
     * On a course the entries are "notify me when a new série opens" interest
     * sign-ups, so the tab is titled accordingly; on the enrollable offers
     * (série, one-time lesson, workshop) it is the real waitlist.
     */
    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return $ownerRecord instanceof Course ? 'Chci vědět první' : 'Čekací listina';
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('')
            ->defaultSort('created_at')
            ->columns([
                TextColumn::make('created_at')
                    ->label('Přihlášen')
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->description(fn (WaitlistEntry $record): string => 'Pořadí dle času registrace'),
                TextColumn::make('name')
                    ->label('Jméno')
                    ->state(fn (WaitlistEntry $record): string => $record->displayName())
                    ->description(fn (WaitlistEntry $record): ?string => $record->client !== null ? 'Registrovaný klient' : 'Bez účtu'),
                TextColumn::make('email')
                    ->label('E-mail')
                    ->state(fn (WaitlistEntry $record): ?string => $record->displayEmail())
                    ->placeholder('—')
                    ->copyable(),
                TextColumn::make('phone')
                    ->label('Telefon')
                    ->state(fn (WaitlistEntry $record): ?string => $record->displayPhone())
                    ->placeholder('—'),
                TextColumn::make('notified_at')
                    ->label('Upozorněn')
                    ->dateTime('d.m.Y H:i')
                    ->placeholder('Čeká'),
                TextColumn::make('confirmed_at')
                    ->label('Potvrzeno')
                    ->dateTime('d.m.Y H:i')
                    ->placeholder('—'),
            ])
            ->headerActions([
                Action::make('promote')
                    ->label('Přidat z čekací listiny')
                    ->icon(Heroicon::OutlinedUserPlus)
                    ->color('primary')
                    ->visible(fn (): bool => $this->promotableOffer() !== null)
                    ->disabled(fn (): bool => ! $this->canPromoteNow())
                    ->requiresConfirmation()
                    ->modalHeading('Přidat z čekací listiny')
                    ->modalDescription('Systém osloví dalšího v pořadí — vytvoří nezávaznou přihlášku a pošle výzvu k platbě. Přidá tolik lidí, kolik je právě volných míst.')
                    ->modalSubmitActionLabel('Přidat')
                    ->action(function (): void {
                        $offer = $this->promotableOffer();

                        if ($offer === null) {
                            return;
                        }

                        PromoteFromWaitlist::handle($offer);

                        Notification::make()
                            ->success()
                            ->title('Hotovo')
                            ->body('Oslovili jsme další v pořadí podle počtu volných míst.')
                            ->send();
                    }),
            ])
            ->recordActions([
                DeleteAction::make()
                    ->label('Odebrat'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->label('Odebrat'),
                ]),
            ]);
    }

    protected function promotableOffer(): CourseSeries|OneOffEvent|null
    {
        $owner = $this->getOwnerRecord();

        return $owner instanceof CourseSeries || $owner instanceof OneOffEvent
            ? $owner
            : null;
    }

    protected function canPromoteNow(): bool
    {
        $offer = $this->promotableOffer();

        return $offer !== null
            && $offer->spotsLeft() > 0
            && $offer->waitlistEntries()->pending()->exists();
    }
}

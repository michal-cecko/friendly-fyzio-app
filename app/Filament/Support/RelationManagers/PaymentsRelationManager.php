<?php

namespace App\Filament\Support\RelationManagers;

use App\Filament\Clusters\Finance\Resources\Payments\PaymentResource;
use App\Filament\Clusters\Finance\Resources\Payments\Schemas\PaymentForm;
use App\Filament\Support\Concerns\RendersRelationManagersAsSections;
use App\Models\Payment;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Livewire\Attributes\On;

/**
 * Payments attached to any payable (reservation, course enrollment, workshop
 * registration, one-time lesson booking) — rows deep-link into the Finance
 * cluster's payment detail.
 */
class PaymentsRelationManager extends RelationManager
{
    /**
     * Broadcast by anything that changes a payable's payments from outside this
     * table — above all the "Zaznamenat platbu" header action. A relation
     * manager is its own Livewire component, so the owning page re-rendering
     * after such an action leaves this table showing the payments it fetched
     * when the page first loaded.
     */
    public const REFRESH_EVENT = 'payments-updated';

    protected static string $relationship = 'payments';

    protected static ?string $title = 'Platby';

    protected static string|BackedEnum|null $icon = Heroicon::OutlinedBanknotes;

    /**
     * Handling the event is enough — Livewire re-renders the component, which
     * re-runs the table query.
     */
    #[On(self::REFRESH_EVENT)]
    public function refreshPayments(): void {}

    /**
     * Payments are corrected from the payable's View page, where Filament would
     * otherwise treat every relation manager as read-only.
     */
    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        return PaymentForm::configure($schema);
    }

    public function table(Table $table): Table
    {
        return $table
            // Pages that stack relation managers as sections already render a
            // "Platby" section heading, so suppress the table's own to avoid
            // doubling; standalone pages keep it.
            ->heading($this->rendersAsSection() ? '' : 'Platby')
            ->columns([
                TextColumn::make('number')
                    ->label('Č. platby')
                    ->searchable()
                    ->formatStateUsing(fn ($state): string => 'č. '.$state),
                TextColumn::make('variable_symbol')
                    ->label('VS')
                    ->searchable(),
                TextColumn::make('amount')
                    ->label('Částka')
                    ->formatStateUsing(fn (int $state): string => number_format($state, 0, ',', ' ').' Kč'),
                TextColumn::make('method')
                    ->label('Způsob')
                    ->badge(),
                TextColumn::make('status')
                    ->label('Stav')
                    ->badge(),
                TextColumn::make('due_at')
                    ->label('Splatnost')
                    ->date('d.m.Y')
                    ->placeholder('—'),
                TextColumn::make('paid_at')
                    ->label('Zaplaceno')
                    ->dateTime('d.m.Y H:i')
                    ->placeholder('—'),
                TextColumn::make('invoice.invoice_number')
                    ->label('Faktura')
                    ->placeholder('—'),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordUrl(fn (Payment $record): string => PaymentResource::getUrl('view', ['record' => $record]))
            ->headerActions([])
            ->recordActions([
                EditAction::make()
                    ->modalHeading(fn (Payment $record): string => 'Upravit platbu č. '.$record->number)
                    // The owning page caches the payable's payment status, and a
                    // corrected amount or state can flip it either way.
                    ->after(fn (): mixed => $this->dispatch(self::REFRESH_EVENT)),
                DeleteAction::make()
                    ->modalHeading(fn (Payment $record): string => 'Smazat platbu č. '.$record->number)
                    ->modalDescription('Platbu tím nevratně odstraníte a záznam se přepočítá jako neuhrazený. Vystavený pokladní doklad ani faktura nezmizí — jen se od platby odpojí.')
                    ->after(fn (): mixed => $this->dispatch(self::REFRESH_EVENT)),
            ])
            ->toolbarActions([]);
    }

    /**
     * Whether the owning page stacks relation managers inside their own titled
     * sections (via {@see RendersRelationManagersAsSections}).
     */
    protected function rendersAsSection(): bool
    {
        return $this->pageClass !== null
            && in_array(
                RendersRelationManagersAsSections::class,
                class_uses_recursive($this->pageClass),
                true,
            );
    }
}

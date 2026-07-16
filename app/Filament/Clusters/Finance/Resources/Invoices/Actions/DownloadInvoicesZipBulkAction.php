<?php

namespace App\Filament\Clusters\Finance\Resources\Invoices\Actions;

use App\Jobs\BuildInvoicesZipJob;
use Filament\Actions\BulkAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection;

/**
 * Queues the ZIP build (one Gotenberg render per invoice) and notifies the
 * initiator with a download link via a database notification when it's ready.
 */
class DownloadInvoicesZipBulkAction extends BulkAction
{
    public static function getDefaultName(): ?string
    {
        return 'downloadInvoicesZip';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label('Stáhnout ZIP')
            ->icon(Heroicon::OutlinedArchiveBox)
            ->color('gray')
            ->requiresConfirmation()
            ->modalHeading('Sestavit ZIP s fakturami')
            ->modalDescription('PDF se sestaví na pozadí; po dokončení dostanete oznámení s odkazem ke stažení (platí 24 hodin).')
            ->modalSubmitActionLabel('Sestavit')
            ->deselectRecordsAfterCompletion()
            ->action(function (Collection $records): void {
                BuildInvoicesZipJob::dispatch(
                    $records->modelKeys(),
                    (string) auth()->id(),
                );

                Notification::make()
                    ->title('Připravujeme ZIP s fakturami.')
                    ->body('Po dokončení dostanete oznámení s odkazem ke stažení.')
                    ->info()
                    ->send();
            });
    }
}

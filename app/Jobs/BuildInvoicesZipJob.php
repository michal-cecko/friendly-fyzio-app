<?php

namespace App\Jobs;

use App\Models\Invoice;
use App\Models\User;
use App\Support\Pdf\InvoicePdfRenderer;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;
use ZipArchive;

/**
 * Builds the accountant's ZIP of invoice PDFs in the background: renders every
 * PDF via Gotenberg, stores the archive on the private disk (S3 in production)
 * under invoice-exports/, and delivers a database notification with the download
 * link. Files are pruned after 24 h by invoices:prune-exports.
 */
class BuildInvoicesZipJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;

    public int $tries = 1;

    /**
     * @param  list<string>  $invoiceIds
     */
    public function __construct(
        public array $invoiceIds,
        public string $userId,
    ) {}

    public function handle(InvoicePdfRenderer $renderer): void
    {
        $invoices = Invoice::query()->whereKey($this->invoiceIds)->get();

        if ($invoices->isEmpty()) {
            return;
        }

        $tempPath = tempnam(sys_get_temp_dir(), 'invoices');

        if ($tempPath === false) {
            throw new RuntimeException('Nelze vytvořit dočasný soubor pro ZIP.');
        }

        try {
            $zip = new ZipArchive;

            if ($zip->open($tempPath, ZipArchive::OVERWRITE) !== true) {
                throw new RuntimeException('Nelze otevřít ZIP archiv.');
            }

            foreach ($invoices as $invoice) {
                $zip->addFromString("{$invoice->invoice_number}.pdf", $renderer->render($invoice));
            }

            $zip->close();

            $path = 'invoice-exports/'.Str::uuid().'/faktury-'.now()->format('Y-m-d').'.zip';

            Storage::disk('local')->put($path, (string) file_get_contents($tempPath));
        } finally {
            @unlink($tempPath);
        }

        $user = User::query()->find($this->userId);

        if ($user === null) {
            return;
        }

        Notification::make()
            ->title('ZIP s fakturami je připraven')
            ->body($invoices->count().' PDF souborů ke stažení. Odkaz je platný 24 hodin.')
            ->success()
            ->actions([
                Action::make('download')
                    ->label('Stáhnout ZIP')
                    ->url(route('invoices.export-download', ['path' => base64_encode($path)])),
            ])
            ->sendToDatabase($user);
    }

    public function failed(?Throwable $exception): void
    {
        $user = User::query()->find($this->userId);

        if ($user === null) {
            return;
        }

        Notification::make()
            ->title('Sestavení ZIP s fakturami selhalo')
            ->body('Zkuste to prosím znovu. '.($exception?->getMessage() ?? ''))
            ->danger()
            ->sendToDatabase($user);
    }
}

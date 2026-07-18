<?php

namespace App\Filament\Support\Actions;

use App\Enums\EmailTemplateKey;
use App\Support\Enrollments\CancelSignup;
use App\Support\Enrollments\SignupStatus;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Bulk counterpart of {@see CancelSignupAction}: cancels every selected active
 * sign-up, optionally e-mailing each client. Already-cancelled records are
 * skipped. Each freed spot is offered to the waitlist through the observers.
 */
class CancelSignupBulkAction extends BulkAction
{
    public static function getDefaultName(): ?string
    {
        return 'cancelSignups';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label('Zrušit přihlášky')
            ->icon(Heroicon::OutlinedXCircle)
            ->color('danger')
            ->modalHeading('Zrušit vybrané přihlášky')
            ->modalIcon(Heroicon::OutlinedXCircle)
            ->modalSubmitActionLabel('Zrušit přihlášky')
            ->schema([
                Toggle::make('notify_client')
                    ->label('Informovat klienty e-mailem')
                    ->default(true),
            ])
            ->action(function (Collection $records, array $data): void {
                $cancel = app(CancelSignup::class);
                $notify = (bool) ($data['notify_client'] ?? false);
                $cancelled = 0;

                $records->each(function (Model $record) use ($cancel, $notify, &$cancelled): void {
                    if (! SignupStatus::isActiveSignup($record)) {
                        return;
                    }

                    $cancel($record, $notify, EmailTemplateKey::EnrollmentCancelledByClinic);
                    $cancelled++;
                });

                Notification::make()
                    ->title("Zrušeno přihlášek: {$cancelled}")
                    ->success()
                    ->send();
            })
            ->deselectRecordsAfterCompletion();
    }
}

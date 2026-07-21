<?php

namespace App\Filament\Support\Actions;

use App\Enums\EmailTemplateKey;
use App\Models\CourseEnrollment;
use App\Models\OneOffEventBooking;
use App\Support\Enrollments\CancelSignup;
use App\Support\Enrollments\SignupStatus;
use Filament\Actions\Action;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;

/**
 * Admin cancellation of a course enrollment / one-off event booking.
 * Unlike the client-zone flow it overrides the cancellation
 * window (staff can cancel anytime); the freed spot is offered to the waitlist
 * via the sign-up observers. A toggle controls whether the client is e-mailed.
 */
class CancelSignupAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'cancelSignup';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label('Zrušit')
            ->icon(Heroicon::OutlinedXCircle)
            ->color('danger')
            ->modalHeading('Zrušit přihlášku')
            ->modalIcon(Heroicon::OutlinedXCircle)
            ->modalDescription('Přihláška se označí jako zrušená a případná nezaplacená výzva k platbě se stáhne. Uvolněné místo se nabídne čekací listině.')
            ->modalSubmitActionLabel('Zrušit přihlášku')
            ->visible(fn (Model $record): bool => SignupStatus::isActiveSignup($record))
            ->schema([
                Toggle::make('notify_client')
                    ->label('Informovat klienta e-mailem')
                    ->default(true),
            ])
            ->action(function (Model $record, array $data): void {
                if (! $record instanceof CourseEnrollment
                    && ! $record instanceof OneOffEventBooking) {
                    return;
                }

                app(CancelSignup::class)(
                    $record,
                    (bool) ($data['notify_client'] ?? false),
                    EmailTemplateKey::EnrollmentCancelledByClinic,
                );

                Notification::make()
                    ->title('Přihláška byla zrušena.')
                    ->success()
                    ->send();
            });
    }
}

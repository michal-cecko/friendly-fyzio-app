<?php

namespace App\Filament\Support\Actions;

use App\Enums\BookingStatus;
use App\Enums\CourseEnrollmentStatus;
use App\Models\CourseEnrollment;
use App\Support\Enrollments\SignupStatus;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;

/**
 * Brings a cancelled course enrollment / one-off event booking back to life:
 * the sign-up returns to its occupying status and — through the sign-up
 * observers — its lesson presence rows are refilled. Any withdrawn payment
 * request is not restored; the outstanding balance is settled again through the
 * usual payment actions, and payment_status is re-derived from there.
 */
class RevertSignupAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'revertSignup';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label('Obnovit')
            ->icon(Heroicon::OutlinedArrowUturnLeft)
            ->color('gray')
            ->requiresConfirmation()
            ->modalHeading('Obnovit přihlášku')
            ->modalIcon(Heroicon::OutlinedArrowUturnLeft)
            ->modalDescription('Zrušená přihláška se vrátí mezi aktivní a znovu obsadí místo. Případnou platbu je potřeba vyřešit samostatně.')
            ->modalSubmitActionLabel('Obnovit přihlášku')
            ->visible(fn (Model $record): bool => SignupStatus::isCancelledSignup($record))
            ->action(function (Model $record): void {
                $record->update([
                    'status' => $record instanceof CourseEnrollment
                        ? CourseEnrollmentStatus::Active
                        : BookingStatus::Confirmed,
                ]);

                Notification::make()
                    ->title('Přihláška byla obnovena.')
                    ->success()
                    ->send();
            });
    }
}

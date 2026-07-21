<?php

namespace App\Filament\Support\Actions;

use App\Models\CourseSeries;
use App\Models\OneOffEvent;
use App\Support\Enrollments\AlreadySignedUpException;
use App\Support\Enrollments\EnrollmentData;
use App\Support\Enrollments\OfferClosedException;
use App\Support\Enrollments\SignUpForOffer;
use App\Support\Reservations\DeactivatedClientException;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;

/**
 * Manually add a participant to an offer from admin, through the same domain
 * flow as the public sign-up ({@see SignUpForOffer}): resolves or creates the
 * customer account, issues a QR payment request and sends the confirmation +
 * account e-mails, and respects capacity (a full offer is refused). Placed as a
 * header action on the offer's signups relation manager.
 */
class AddParticipantAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'addParticipant';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label('Přidat účastníka')
            ->icon(Heroicon::OutlinedUserPlus)
            ->color('primary')
            ->modalHeading('Přidat účastníka')
            ->modalDescription('Vytvoří přihlášku jako z veřejného webu: založí účet (pokud neexistuje), vystaví výzvu k QR platbě a odešle potvrzovací e-mail.')
            ->modalSubmitActionLabel('Přidat')
            ->schema([
                TextInput::make('name')
                    ->label('Jméno a příjmení')
                    ->required()
                    ->maxLength(255),
                TextInput::make('email')
                    ->label('E-mail')
                    ->email()
                    ->required()
                    ->maxLength(255),
                TextInput::make('phone')
                    ->label('Telefon')
                    ->tel()
                    ->maxLength(30),
                Textarea::make('note')
                    ->label('Poznámka')
                    ->rows(2)
                    ->maxLength(2000),
            ])
            ->action(function (array $data, RelationManager $livewire): void {
                $offer = $livewire->getOwnerRecord();

                $enrollment = new EnrollmentData(
                    name: trim($data['name']),
                    email: trim($data['email']),
                    phone: filled($data['phone'] ?? null) ? trim($data['phone']) : null,
                    note: filled($data['note'] ?? null) ? trim($data['note']) : null,
                    client: null,
                );

                $action = app(SignUpForOffer::class);

                try {
                    match (true) {
                        $offer instanceof CourseSeries => $action->forSeries($offer, $enrollment),
                        $offer instanceof OneOffEvent => $action->forEvent($offer, $enrollment),
                        default => null,
                    };
                } catch (OfferClosedException) {
                    Notification::make()
                        ->title('Kapacita je naplněná nebo je přihlašování uzavřené.')
                        ->body('Účastníka lze přidat ručně přes standardní formulář vytvoření záznamu.')
                        ->danger()
                        ->send();

                    return;
                } catch (AlreadySignedUpException) {
                    Notification::make()
                        ->title('S tímto e-mailem už aktivní přihlášku evidujeme.')
                        ->danger()
                        ->send();

                    return;
                } catch (DeactivatedClientException) {
                    Notification::make()
                        ->title('Účet klienta je deaktivovaný.')
                        ->danger()
                        ->send();

                    return;
                }

                Notification::make()
                    ->title('Účastník byl přidán.')
                    ->success()
                    ->send();
            });
    }
}

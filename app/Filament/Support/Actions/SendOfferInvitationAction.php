<?php

namespace App\Filament\Support\Actions;

use App\Enums\EmailTemplateKey;
use App\Filament\Support\Schemas\CopyRecipientsFields;
use App\Models\CourseSeries;
use App\Models\OneOffEvent;
use App\Models\User;
use App\Notifications\EnrollmentTemplateNotification;
use App\Support\Emails\CopyRecipients;
use App\Support\Emails\SentEmailReceipt;
use App\Support\Enrollments\EnrollmentEmailContext;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Support\Icons\Heroicon;

/**
 * Sends the private-access invite ("předprodej / VIP pozvánka") to selected
 * customers — one or many. Each recipient gets the offer's shared hidden link
 * ({@see EmailTemplateKey::OfferInvitation}, `{{ odkaz }}` = presaleUrl), through
 * which they can sign up even while the offer is Private. Shared header action
 * across the course-series and one-off-event resources; only shown
 * for a Private offer.
 */
class SendOfferInvitationAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'sendInvitation';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label('Poslat pozvánku')
            ->icon(Heroicon::OutlinedEnvelope)
            ->color('gray')
            ->visible(fn (CourseSeries|OneOffEvent $record): bool => $record->isPrivate())
            ->modalHeading('Poslat přednostní pozvánku')
            ->modalDescription('Vybraní zákazníci dostanou e-mail s přihlašovacím odkazem, přes který se mohou přihlásit, i když termín není veřejně otevřený.')
            ->modalSubmitActionLabel('Odeslat')
            ->schema([
                Select::make('recipient_ids')
                    ->label('Příjemci')
                    ->multiple()
                    ->required()
                    ->searchable()
                    ->getSearchResultsUsing(fn (string $search): array => User::query()
                        ->customers()
                        ->whereNull('deactivated_at')
                        ->where(fn ($query) => $query
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%"))
                        ->orderBy('name')
                        ->limit(50)
                        ->pluck('name', 'id')
                        ->all())
                    ->getOptionLabelsUsing(fn (array $values): array => User::query()
                        ->whereKey($values)
                        ->pluck('name', 'id')
                        ->all()),
                Textarea::make('zprava')
                    ->label('Osobní zpráva (nepovinné)')
                    ->rows(3),
                ...CopyRecipientsFields::make('Kopie se přidá ke každé odeslané pozvánce.'),
            ])
            ->action(function (CourseSeries|OneOffEvent $record, array $data): void {
                $url = $record->presaleUrl();
                $offerTokens = EnrollmentEmailContext::offerTokens($record);
                $message = (string) ($data['zprava'] ?? '');
                $copies = CopyRecipients::fromFormData($data);

                $sent = 0;

                foreach (User::query()->whereKey($data['recipient_ids'])->get() as $recipient) {
                    if (blank($recipient->email)) {
                        continue;
                    }

                    $recipient->notify(new EnrollmentTemplateNotification(EmailTemplateKey::OfferInvitation, [
                        'jmeno' => EnrollmentEmailContext::firstName($recipient),
                        ...$offerTokens,
                        'odkaz' => $url,
                        'zprava' => $message,
                    ], $copies));

                    $sent++;
                }

                SentEmailReceipt::report($sent, 'Pozvánka');
            });
    }
}

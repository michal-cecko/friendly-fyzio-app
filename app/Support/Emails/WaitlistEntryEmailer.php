<?php

namespace App\Support\Emails;

use App\Enums\EmailTemplateKey;
use App\Models\Course;
use App\Models\CourseSeries;
use App\Models\OneOffEvent;
use App\Models\WaitlistEntry;
use App\Notifications\EnrollmentTemplateNotification;
use App\Support\Enrollments\EnrollmentEmailContext;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

/**
 * Resolves and dispatches the handful of CMS e-mails that can be addressed to a
 * bare waitlist entry — one that has no enrollment or payment yet. Only the two
 * keys whose tokens are fillable from the entry + its offer are exposed; the
 * payment/enrollment templates are deliberately left out so their
 * {{ castka }} / {{ qr }} placeholders never render empty.
 *
 * Guests (no linked User) are mailed on demand, so the picker works the same for
 * a registered client and a name-only sign-up.
 */
class WaitlistEntryEmailer
{
    /** Client-facing keys renderable from a bare entry. @var list<EmailTemplateKey> */
    private const CLIENT_KEYS = [
        EmailTemplateKey::WaitlistJoined,
        EmailTemplateKey::OfferInvitation,
    ];

    /**
     * @return array<string, array<string, string>>
     */
    public static function templateGroups(WaitlistEntry $entry): array
    {
        if (! self::offer($entry) instanceof CourseSeries
            && ! self::offer($entry) instanceof OneOffEvent
            && ! self::offer($entry) instanceof Course) {
            return [];
        }

        if ($entry->displayEmail() === null) {
            return [];
        }

        return ['Klient' => self::keyOptions(self::CLIENT_KEYS)];
    }

    /**
     * The keys offered to the bulk waitlist mailer.
     *
     * @return array<string, string>
     */
    public static function broadcastTemplateOptions(): array
    {
        return self::keyOptions(self::CLIENT_KEYS);
    }

    public static function send(WaitlistEntry $entry, EmailTemplateKey $key, ?CopyRecipients $copies = null): void
    {
        $offer = self::offer($entry);

        if (! $offer instanceof CourseSeries && ! $offer instanceof OneOffEvent && ! $offer instanceof Course) {
            return;
        }

        $tokens = [
            'jmeno' => self::firstName($entry),
            ...EnrollmentEmailContext::offerTokens($offer),
            ...self::extraTokens($entry, $offer, $key),
        ];

        $notification = new EnrollmentTemplateNotification($key, $tokens, $copies);

        if ($entry->client !== null) {
            $entry->client->notify($notification);

            return;
        }

        Notification::route('mail', $entry->displayEmail())->notify($notification);
    }

    private static function offer(WaitlistEntry $entry): mixed
    {
        return $entry->waitlistable;
    }

    /**
     * @return array<string, string>
     */
    private static function extraTokens(WaitlistEntry $entry, CourseSeries|OneOffEvent|Course $offer, EmailTemplateKey $key): array
    {
        return match ($key) {
            EmailTemplateKey::WaitlistJoined => [
                'poradi' => (string) (1 + $offer->waitlistEntries()->pending()
                    ->where('created_at', '<', $entry->created_at)
                    ->count()),
            ],
            EmailTemplateKey::OfferInvitation => [
                'odkaz' => $offer instanceof CourseSeries ? $offer->presaleUrl() : $offer->permalink(),
                'zprava' => '',
            ],
            default => [],
        };
    }

    private static function firstName(WaitlistEntry $entry): string
    {
        $fromClient = EnrollmentEmailContext::firstName($entry->client);

        return $fromClient !== '' ? $fromClient : Str::of($entry->displayName())->before(' ')->toString();
    }

    /**
     * @param  list<EmailTemplateKey>  $keys
     * @return array<string, string>
     */
    private static function keyOptions(array $keys): array
    {
        return collect($keys)
            ->mapWithKeys(fn (EmailTemplateKey $key): array => [$key->value => $key->label()])
            ->all();
    }
}

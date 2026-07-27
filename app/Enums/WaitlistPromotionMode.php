<?php

namespace App\Enums;

use App\Support\Settings;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * What an enrollable offer (course series, one-off event) does with its waitlist
 * when a spot frees up. Replaces the old `auto_promote_waitlist` boolean, which
 * could only express the first and last case.
 *
 * The two automatic modes come from two different chapters of the spec:
 *
 * - {@see self::AutomaticAdd} — §4.4 "klient je automaticky zaregistrovaný, ak
 *   sa niekto odhlási": the next in line gets a real (unpaid) sign-up that holds
 *   the spot, plus a payment request.
 * - {@see self::AutomaticInvite} — §6.4 "Automatický náhradník: prvý kto potvrdí,
 *   miesto dostáva. Ak nikto nereaguje, miesto sa uvoľní pre verejnosť": every
 *   waiter is e-mailed at once and races for the spot, which stays reserved for
 *   the waitlist only until the invite window closes.
 */
enum WaitlistPromotionMode: string implements HasColor, HasLabel
{
    case Manual = 'manual';
    case AutomaticInvite = 'automatic_invite';
    case AutomaticAdd = 'automatic_add';

    public function getLabel(): string
    {
        return match ($this) {
            self::Manual => 'Ručně',
            self::AutomaticInvite => 'Oslovit čekající',
            self::AutomaticAdd => 'Rovnou přihlásit',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Manual => 'gray',
            self::AutomaticInvite => 'info',
            self::AutomaticAdd => 'success',
        };
    }

    /**
     * Both automatic modes hold the freed spot for a configurable window, so the
     * description spells out the current setting instead of a vague "lhůta":
     * {@see Settings::waitlistInviteHours()} for the invite race and
     * {@see Settings::enrollmentHoldHours()} for the unpaid sign-up.
     */
    public function description(): string
    {
        return match ($this) {
            self::Manual => 'Systém neudělá nic — uvolněné místo obsadíte z čekací listiny sami tlačítkem.',
            self::AutomaticInvite => 'Všem čekajícím přijde e-mail s odkazem a kdo se první přihlásí, ten místo dostane. Po dobu '.Settings::waitlistInviteHours().' hodin místo nemůže obsadit nikdo z webu; když se nikdo neozve, uvolní se veřejnosti.',
            self::AutomaticAdd => 'Dalšímu v pořadí rovnou vytvoříme přihlášku a pošleme výzvu k platbě. Místo mu držíme '.Settings::enrollmentHoldHours().' hodin na zaplacení.',
        };
    }

    public function isAutomatic(): bool
    {
        return $this !== self::Manual;
    }

    /**
     * Keyed by case value for a ToggleButtons `->descriptions()` call.
     *
     * @return array<string, string>
     */
    public static function descriptions(): array
    {
        return array_reduce(
            self::cases(),
            fn (array $carry, self $mode): array => $carry + [$mode->value => $mode->description()],
            [],
        );
    }
}

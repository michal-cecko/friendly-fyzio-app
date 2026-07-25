<?php

namespace App\Filament\Support\Schemas;

use App\Support\Emails\CopyRecipients;
use Filament\Forms\Components\TagsInput;

/**
 * The "Kopie (CC)" / "Skrytá kopie (BCC)" inputs shared by every action that
 * sends an e-mail by hand, so a staff member can always route a copy to
 * accounting, a colleague or their own archive. Submitted values are read back
 * with {@see CopyRecipients::fromFormData()}.
 */
class CopyRecipientsFields
{
    /**
     * @return array<int, TagsInput>
     */
    public static function make(?string $helperText = null): array
    {
        return [
            TagsInput::make('cc')
                ->label('Kopie (CC)')
                ->placeholder('E-mail a Enter')
                ->splitKeys([',', ' '])
                ->nestedRecursiveRules(['email']),
            TagsInput::make('bcc')
                ->label('Skrytá kopie (BCC)')
                ->placeholder('E-mail a Enter')
                ->splitKeys([',', ' '])
                ->nestedRecursiveRules(['email'])
                ->helperText($helperText ?? self::suppressionNote()),
        ];
    }

    /**
     * Before launch only administrators receive mail at all, and the guard judges
     * the message by its primary recipient — a copy rides along with a delivered
     * e-mail and is dropped together with a suppressed one.
     */
    private static function suppressionNote(): ?string
    {
        return config('mail.suppress_non_admin')
            ? 'Před spuštěním se e-maily klientům neodesílají — kopie se v tom případě neodešle také.'
            : null;
    }
}

<?php

namespace App\Forms\Components;

use App\Rules\TurnstileTokenValid;
use Filament\Forms\Components\Field;

/**
 * Runs an invisible Cloudflare Turnstile challenge in the background, binds the
 * issued token into form state, then validates it server-side against siteverify.
 * The widget only becomes visible if Cloudflare decides an interactive challenge
 * is genuinely required (appearance: interaction-only).
 */
class TurnstileField extends Field
{
    protected string $view = 'forms.components.turnstile';

    protected function setUp(): void
    {
        parent::setUp();

        // Invisible by default — keep the label for screen readers but hide it visually.
        $this->label(__('Ověření'));
        $this->hiddenLabel();
        $this->dehydrated();
        $this->required();
        $this->rule(static fn (): TurnstileTokenValid => new TurnstileTokenValid);
        $this->validationMessages([
            'required' => __('Ověření proti robotům selhalo. Zkuste to prosím znovu.'),
        ]);
    }

    public function getSiteKey(): ?string
    {
        return config('services.turnstile.site_key');
    }
}

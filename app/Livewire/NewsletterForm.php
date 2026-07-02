<?php

namespace App\Livewire;

use App\Support\MailerLite\MailerLiteClient;
use App\Support\MailerLite\MailerLiteException;
use App\Support\MailerLite\SubscribeResult;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * Public newsletter signup handled entirely over Livewire, so subscribing never
 * reloads the page or loses the visitor's scroll position. Rendered in both the
 * newsletter brick (default layout) and the site footer (compact layout); the
 * `compact` flag switches the view between the two.
 *
 * The subscribe call is synchronous so the visitor immediately sees which of the
 * three outcomes happened: newly subscribed, already subscribed, or an error.
 */
class NewsletterForm extends Component
{
    public string $email = '';

    /**
     * Outcome of the last attempt: null | 'subscribed' | 'already' | 'error'.
     */
    public ?string $status = null;

    public bool $compact = false;

    public string $placeholder = 'Váš e-mail';

    public string $buttonText = 'Odebírat';

    public function subscribe(MailerLiteClient $client): void
    {
        $this->validate(
            ['email' => ['required', 'email']],
            ['email.required' => 'Zadejte prosím e-mailovou adresu.', 'email.email' => 'Zadejte prosím platnou e-mailovou adresu.'],
            ['email' => 'e-mail'],
        );

        try {
            $result = $client->subscribe($this->email);
        } catch (MailerLiteException $exception) {
            report($exception);
            $this->status = 'error';

            return;
        }

        $this->status = match ($result) {
            SubscribeResult::Subscribed => 'subscribed',
            SubscribeResult::AlreadySubscribed => 'already',
        };

        $this->reset('email');
    }

    public function render(): View
    {
        return view('livewire.newsletter-form');
    }
}

<?php

namespace App\Livewire;

use App\Models\ContactInquiry;
use App\Notifications\ContactInquiryNotification;
use App\Support\Settings;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Notification;
use Livewire\Component;

/**
 * Public Kontakt page contact form handled over Livewire, so submitting never
 * reloads the page. The message is always persisted as a ContactInquiry (nothing
 * is lost if the notification e-mail fails), then e-mailed to the clinic inbox.
 */
class ContactForm extends Component
{
    public string $name = '';

    public string $email = '';

    public string $phone = '';

    public string $message = '';

    /**
     * Outcome of the last attempt: null | 'sent' | 'error'.
     */
    public ?string $status = null;

    public string $buttonText = 'Odeslat zprávu';

    public function submit(): void
    {
        $data = $this->validate(
            [
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'email', 'max:255'],
                'phone' => ['nullable', 'string', 'max:255'],
                'message' => ['required', 'string', 'min:10', 'max:5000'],
            ],
            [
                'name.required' => 'Zadejte prosím své jméno.',
                'email.required' => 'Zadejte prosím e-mailovou adresu.',
                'email.email' => 'Zadejte prosím platnou e-mailovou adresu.',
                'message.required' => 'Napište prosím svou zprávu.',
                'message.min' => 'Zpráva je příliš krátká.',
            ],
            [
                'name' => 'jméno',
                'email' => 'e-mail',
                'phone' => 'telefon',
                'message' => 'zpráva',
            ],
        );

        $inquiry = ContactInquiry::create($data);

        try {
            $recipient = Settings::get('web.contact_email');

            if (filled($recipient)) {
                Notification::route('mail', $recipient)
                    ->notify(new ContactInquiryNotification($inquiry));
            }
        } catch (\Throwable $exception) {
            // The inquiry is already saved; a failed e-mail must not lose the message.
            report($exception);
        }

        $this->status = 'sent';
        $this->reset('name', 'email', 'phone', 'message');
    }

    public function render(): View
    {
        return view('livewire.contact-form');
    }
}

<?php

namespace App\Listeners;

use App\Enums\Capability;
use App\Models\User;
use App\Support\Settings;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Events\NotificationSending;
use Illuminate\Support\Facades\Log;

/**
 * Pre-launch guard: while the database holds real imported client records but
 * the clinic is not live yet, nothing may reach a customer or a therapist by
 * accident. Administrators still receive their mail so the flows stay testable.
 *
 * Returning false from a NotificationSending listener cancels delivery. Every
 * e-mail in the application goes through Laravel notifications — there is no
 * direct Mail::send anywhere — so this single hook covers all of them.
 *
 * Controlled by MAIL_SUPPRESS_NON_ADMIN, which **defaults to enabled**: the two
 * failure modes are not symmetrical. Forgetting to switch it on could mail
 * fifteen hundred real clients, whereas forgetting to switch it off merely
 * delays mail until someone notices. Set it to false in .env to go live.
 */
class SuppressNonAdminMail
{
    public function handle(NotificationSending $event): bool
    {
        if ($event->channel !== 'mail' || ! config('mail.suppress_non_admin')) {
            return true;
        }

        if ($this->isAdminRecipient($event->notifiable)) {
            return true;
        }

        Log::debug('Suppressed e-mail to a non-administrator (MAIL_SUPPRESS_NON_ADMIN).', [
            'notification' => $event->notification::class,
            'recipient' => $this->describe($event->notifiable),
        ]);

        return false;
    }

    /**
     * Only administrators get mail. Anonymous recipients carry no role, so the
     * address is looked up among the users; the clinic's own contact inbox is
     * allowed explicitly so enquiries from the public form still arrive.
     * Anything that cannot be resolved is treated as a customer and suppressed,
     * because every remaining anonymous path (waitlists, booking) is
     * customer-facing.
     */
    protected function isAdminRecipient(mixed $notifiable): bool
    {
        if ($notifiable instanceof User) {
            return $notifiable->isAdmin();
        }

        if (! $notifiable instanceof AnonymousNotifiable) {
            return false;
        }

        $address = $notifiable->routes['mail'] ?? null;
        $address = is_array($address) ? array_key_first($address) : $address;

        if (! is_string($address) || $address === '') {
            return false;
        }

        if (rescue(fn (): mixed => Settings::get('web.contact_email'), null, false) === $address) {
            return true;
        }

        return User::query()
            ->where('email', $address)
            ->whereHas('roles', fn ($roles) => $roles->whereIn('name', [
                Capability::Admin->roleName(),
                Capability::SuperAdmin->roleName(),
            ]))
            ->exists();
    }

    protected function describe(mixed $notifiable): string
    {
        if ($notifiable instanceof User) {
            return $notifiable->email.' ('.($notifiable->role?->value ?? 'unknown').')';
        }

        $address = $notifiable instanceof AnonymousNotifiable ? ($notifiable->routes['mail'] ?? null) : null;

        return is_array($address) ? (string) array_key_first($address) : (string) ($address ?? 'unknown');
    }
}

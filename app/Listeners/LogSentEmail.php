<?php

namespace App\Listeners;

use App\Support\ActivityLog\LogActivity;
use App\Support\ActivityLog\MailBodyRenderer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Notifications\Messages\MailMessage;
use Throwable;

/**
 * Logs every outgoing e-mail as an `email_sent` activity: the recipients, the
 * subject line, the fully rendered HTML body (for previewing), and the domain
 * record it concerns (resolved from the notification's model property). All app
 * e-mail flows through Laravel notifications, so this one listener covers both
 * manual (admin actions) and automatic (scheduled/wizard) sends.
 */
class LogSentEmail
{
    public function handle(NotificationSent $event): void
    {
        if ($event->channel !== 'mail') {
            return;
        }

        $notification = $event->notification;

        if (! method_exists($notification, 'toMail')) {
            return;
        }

        try {
            $mail = $notification->toMail($event->notifiable);
        } catch (Throwable) {
            return;
        }

        if (! $mail instanceof MailMessage) {
            return;
        }

        $subjectLine = $mail->subject ?: class_basename($notification);

        LogActivity::record(
            event: 'email_sent',
            subject: $this->resolveSubject($notification),
            description: $subjectLine,
            properties: [
                'notification' => class_basename($notification),
                'recipients' => $this->recipients($event->notifiable),
                'subject' => $subjectLine,
                'body_html' => MailBodyRenderer::render($mail),
            ],
        );
    }

    /**
     * The domain record the e-mail concerns: the first Eloquent model held as a
     * public property of the notification (reservation, payment, invoice…).
     */
    private function resolveSubject(object $notification): ?Model
    {
        foreach (get_object_vars($notification) as $value) {
            if ($value instanceof Model) {
                return $value;
            }
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    private function recipients(object $notifiable): array
    {
        if ($notifiable instanceof AnonymousNotifiable) {
            return $this->normalizeRoute($notifiable->routes['mail'] ?? null);
        }

        $email = method_exists($notifiable, 'routeNotificationFor')
            ? $notifiable->routeNotificationFor('mail')
            : ($notifiable->email ?? null);

        if (is_array($email)) {
            return $this->normalizeRoute($email);
        }

        if (! is_string($email) || $email === '') {
            return [];
        }

        $name = $notifiable->name ?? null;

        return [is_string($name) && $name !== '' ? "{$name} <{$email}>" : $email];
    }

    /**
     * @return array<int, string>
     */
    private function normalizeRoute(mixed $route): array
    {
        if (is_string($route)) {
            return $route === '' ? [] : [$route];
        }

        if (! is_array($route)) {
            return [];
        }

        $recipients = [];

        foreach ($route as $key => $value) {
            $recipients[] = is_string($key) ? "{$value} <{$key}>" : (string) $value;
        }

        return $recipients;
    }
}

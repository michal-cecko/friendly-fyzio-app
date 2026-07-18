<?php

namespace App\Support\ActivityLog;

use Illuminate\Mail\Markdown;
use Illuminate\Notifications\Messages\MailMessage;
use Throwable;

/**
 * Renders a notification's MailMessage to the same HTML the recipient received,
 * so the activity log can show an exact preview of what was sent. Mirrors how
 * Laravel's MailChannel builds the view: explicit view first, then markdown
 * (which MailMessage defaults to the `notifications::email` template).
 */
class MailBodyRenderer
{
    public static function render(MailMessage $mail): string
    {
        try {
            if (! empty($mail->view)) {
                $view = is_array($mail->view) ? ($mail->view[0] ?? null) : $mail->view;

                if (is_string($view) && $view !== '') {
                    return view($view, $mail->data())->render();
                }
            }

            if (! empty($mail->markdown)) {
                return (string) app(Markdown::class)
                    ->theme($mail->theme ?? config('mail.markdown.theme', 'default'))
                    ->render($mail->markdown, $mail->data());
            }
        } catch (Throwable) {
            // Fall through to an empty body rather than break the send/log path.
        }

        return '';
    }
}

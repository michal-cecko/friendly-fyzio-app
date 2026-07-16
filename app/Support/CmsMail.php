<?php

namespace App\Support;

use App\Models\EmailTemplate;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\HtmlString;

/**
 * Builds a MailMessage from a CMS EmailTemplate: renders the template to HTML with the
 * given token context and wraps it in the fixed emails.rendered mail view. Callers own
 * the null-template fallback (a framework/plain default) so this only handles the happy
 * path once a template row is present.
 */
class CmsMail
{
    /**
     * @param  array<string, string|HtmlString>  $context
     */
    public static function render(EmailTemplate $template, array $context = []): MailMessage
    {
        return (new MailMessage)
            ->subject($template->subject)
            ->view('emails.rendered', ['html' => EmailTemplateRenderer::render($template, $context)]);
    }
}

<?php

namespace App\Support;

use App\Mason\EmailBrickRegistry;
use App\Models\EmailTemplate;
use Awcodes\Mason\Support\MasonRenderer;
use Illuminate\Support\HtmlString;

/**
 * Turns a CMS EmailTemplate into a complete, email-safe HTML document: renders its
 * Mason bricks, substitutes {{ tokens }} from the given context, and wraps the body
 * in the fixed emails.layout chrome (header + footer).
 */
class EmailTemplateRenderer
{
    /**
     * @param  array<string, string|HtmlString>  $context  Token replacements (e.g. ['jmeno' => 'Jana']).
     */
    public static function render(EmailTemplate $template, array $context = []): string
    {
        $body = MasonRenderer::make($template->content ?: [])
            ->bricks(EmailBrickRegistry::flat())
            ->toUnsafeHtml();

        $body = self::substituteTokens($body, $context);
        $body = self::styleContentLinks($body);

        return view('emails.layout', [
            'body' => $body,
            'subject' => $template->subject,
        ])->render();
    }

    /**
     * Wrap an arbitrary HTML fragment (e.g. a RichEditor body from the custom e-mail
     * composer) in the same fixed emails.layout chrome the CMS templates use, giving
     * inline links the brand accent along the way.
     */
    public static function renderHtml(string $bodyHtml, string $subject): string
    {
        return view('emails.layout', [
            'body' => self::styleContentLinks($bodyHtml),
            'subject' => $subject,
        ])->render();
    }

    /**
     * Replace every {{ token }} with its context value. Scalars are escaped;
     * HtmlString values (server-rendered fragments like the invoice items table)
     * are inserted raw — the type IS the raw-allowlist. Unknown tokens resolve
     * to an empty string.
     *
     * @param  array<string, string|HtmlString>  $context
     */
    private static function substituteTokens(string $html, array $context): string
    {
        return preg_replace_callback(
            '/\{\{\s*(\w+)\s*\}\}/',
            function (array $matches) use ($context): string {
                $value = $context[$matches[1]] ?? '';

                return $value instanceof HtmlString ? $value->toHtml() : e($value);
            },
            $html,
        ) ?? $html;
    }

    /**
     * Give inline content links the brand accent colour. Anchors that already carry
     * a style attribute (e.g. buttons) are left untouched.
     */
    private static function styleContentLinks(string $html): string
    {
        return preg_replace(
            '/<a (?![^>]*\bstyle=)/i',
            '<a style="color:#ED86A3;text-decoration:underline;" ',
            $html,
        ) ?? $html;
    }
}

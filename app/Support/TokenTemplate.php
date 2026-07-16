<?php

namespace App\Support;

/**
 * Plain-text {{ token }} substitution shared by payment notes and invoice item
 * templates. No HTML escaping (see EmailTemplateRenderer for the escaped variant);
 * unknown tokens resolve to an empty string.
 */
final class TokenTemplate
{
    /**
     * @param  array<string, string>  $context
     */
    public static function render(string $template, array $context): string
    {
        return preg_replace_callback(
            '/\{\{\s*(\w+)\s*\}\}/',
            fn (array $matches): string => $context[$matches[1]] ?? '',
            $template,
        ) ?? $template;
    }
}

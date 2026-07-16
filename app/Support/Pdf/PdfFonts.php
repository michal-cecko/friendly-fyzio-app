<?php

namespace App\Support\Pdf;

/**
 * The brand woff2 files shipped alongside every Gotenberg request (referenced by
 * bare filename from the @font-face rules in pdf/partials/styles). Gotenberg has
 * no network egress, so fonts must travel with the HTML.
 */
final class PdfFonts
{
    /**
     * @return array<string, string> filename => bytes
     */
    public static function assets(): array
    {
        $assets = [];

        foreach (glob(resource_path('fonts/pdf/*.woff2')) ?: [] as $path) {
            $assets[basename($path)] = (string) file_get_contents($path);
        }

        return $assets;
    }
}

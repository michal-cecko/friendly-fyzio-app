<?php

namespace App\Support\Pdf;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Minimal client for Gotenberg's Chromium HTML route. The main document must be
 * attached as `index.html`; a file named `footer.html` is automatically used as
 * the running footer (with Gotenberg's pageNumber/totalPages spans). Extra assets
 * (fonts, images) are attached under their bare filenames and referenced
 * relatively from the HTML.
 */
final class GotenbergClient
{
    /**
     * A4 portrait with a taller bottom margin for the running footer; inches.
     * printBackground is mandatory — the documents rely on CSS backgrounds.
     * emulatedMediaType pins Gotenberg's default so the screen-only preview
     * styles (pdf/partials/styles) can never leak into the PDFs.
     *
     * @var array<string, float|bool|string>
     */
    private const DEFAULT_OPTIONS = [
        'paperWidth' => 8.27,
        'paperHeight' => 11.7,
        'marginTop' => 0.4,
        'marginBottom' => 0.8,
        'marginLeft' => 0.4,
        'marginRight' => 0.4,
        'printBackground' => true,
        'emulatedMediaType' => 'print',
    ];

    /**
     * @param  array<string, string>  $assets  filename => bytes
     * @param  array<string, float|bool|string>  $options  chromium form fields merged over the defaults
     */
    public function pdfFromHtml(string $html, ?string $footerHtml = null, array $assets = [], array $options = []): string
    {
        $request = Http::timeout(30)
            ->attach('files', $html, 'index.html', ['Content-Type' => 'text/html']);

        if ($footerHtml !== null) {
            $request->attach('files', $footerHtml, 'footer.html', ['Content-Type' => 'text/html']);
        }

        foreach ($assets as $filename => $contents) {
            $request->attach('files', $contents, $filename);
        }

        $response = $request->post(
            config('services.gotenberg.url').'/forms/chromium/convert/html',
            [...self::DEFAULT_OPTIONS, ...$options],
        );

        if (! $response->successful()) {
            throw new RuntimeException('Gotenberg PDF conversion failed: HTTP '.$response->status());
        }

        return $response->body();
    }
}

<?php

namespace App\Support\Invoices;

use App\Contracts\Payable;
use App\Support\TokenTemplate;

/**
 * Renders the human-readable invoice line (title + optional sub-description) for
 * a payable, combining the settings-editable templates with the payable's token
 * context. Honors the per-entity "Název pro fakturaci" override via the context.
 */
final class PayableTitle
{
    /**
     * @return array{title: string, description: ?string}
     */
    public static function render(Payable $payable): array
    {
        $templates = $payable->invoiceItemTemplates();
        $context = $payable->payableTitleContext();

        $title = trim(TokenTemplate::render($templates['title'], $context));
        $description = trim(TokenTemplate::render($templates['description'] ?? '', $context));

        return [
            'title' => $title !== '' ? $title : $payable->payableType()->label(),
            'description' => $description !== '' ? $description : null,
        ];
    }
}

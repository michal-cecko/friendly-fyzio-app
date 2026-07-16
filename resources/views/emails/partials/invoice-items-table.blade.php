@php
    /** @var App\Models\Invoice $invoice */
    $headStyle = "font-family:'Montserrat',Arial,sans-serif;font-size:12px;font-weight:600;color:#FFFFFF;padding:8px 12px;text-align:left;";
    $cellStyle = "font-family:'Open Sans',Arial,sans-serif;font-size:13px;color:#1A1A1A;padding:8px 12px;border-bottom:1px solid #E5E5E5;";
    $money = fn (int $amount): string => number_format($amount, 0, ',', ' ').' Kč';
    $totalLabel = $invoice->status === App\Enums\InvoiceStatus::Paid ? 'Celkem zaplaceno' : 'Celkem k úhradě';
@endphp

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 24px;border-collapse:collapse;">
    <tr>
        <td style="{{ $headStyle }}background-color:#2D2D2D;border-radius:8px 0 0 0;">Položka</td>
        <td style="{{ $headStyle }}background-color:#2D2D2D;text-align:center;width:52px;">Počet</td>
        <td style="{{ $headStyle }}background-color:#2D2D2D;text-align:right;width:84px;">Cena/ks</td>
        <td style="{{ $headStyle }}background-color:#2D2D2D;text-align:right;border-radius:0 8px 0 0;width:84px;">Celkem</td>
    </tr>
    @foreach($invoice->items as $item)
        <tr>
            <td style="{{ $cellStyle }}{{ $loop->even ? 'background-color:#F5F5F5;' : '' }}">
                <span style="font-weight:600;">{{ $item->title }}</span>
                @if(filled($item->description))
                    <br><span style="font-size:11px;color:#666666;">{{ $item->description }}</span>
                @endif
            </td>
            <td style="{{ $cellStyle }}text-align:center;{{ $loop->even ? 'background-color:#F5F5F5;' : '' }}">{{ $item->quantity }}</td>
            <td style="{{ $cellStyle }}text-align:right;{{ $loop->even ? 'background-color:#F5F5F5;' : '' }}">{{ $money($item->unit_price) }}</td>
            <td style="{{ $cellStyle }}text-align:right;font-weight:600;{{ $loop->even ? 'background-color:#F5F5F5;' : '' }}">{{ $money($item->total) }}</td>
        </tr>
    @endforeach
    <tr>
        <td colspan="3" style="font-family:'Montserrat',Arial,sans-serif;font-size:13px;font-weight:700;color:#1A1A1A;padding:10px 12px;background-color:#FDECF1;border-radius:0 0 0 8px;">
            {{ $totalLabel }} ({{ $invoice->payment_method?->getLabel() ?? '—' }})
        </td>
        <td style="font-family:'Montserrat',Arial,sans-serif;font-size:14px;font-weight:700;color:#D4607E;padding:10px 12px;background-color:#FDECF1;text-align:right;border-radius:0 0 8px 0;">
            {{ $money($invoice->amount) }}
        </td>
    </tr>
</table>

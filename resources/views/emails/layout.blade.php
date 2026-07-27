@php
    use App\Support\Settings;

    $address = Settings::get('web.address');
    $phone = Settings::get('web.contact_phone');
    $email = Settings::get('web.contact_email');
    $instagram = Settings::get('web.instagram_url');
    $facebook = Settings::get('web.facebook_url');

    $brand = 'FriendlyFyzio s.r.o.';
    $footerAddress = filled($address) ? "{$brand} | {$address}" : $brand;
    $footerContact = implode('  •  ', array_filter([$phone, $email]));

    $socialLinks = array_filter([
        'instagram' => $instagram,
        'facebook' => $facebook,
    ]);
@endphp
<!DOCTYPE html>
<html lang="cs" xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <title>{{ $subject ?? '' }}</title>
    {{-- Phones render the 600px card scaled down, so everything reads small. The card is fluid
         (width:100%;max-width:600px) and these rules bump the type on narrow viewports — the
         first pass overshot, so every value here was trimmed by 10% (rounded to whole px, with
         the scale kept monotonic). !important is required to beat the inline styles the bricks
         carry. Clients that strip <style> simply keep the desktop sizes. --}}
    <style type="text/css">
        @media only screen and (max-width: 620px) {
            .e-head { padding: 22px 18px 18px !important; }
            .e-content { padding: 25px 18px !important; }
            .e-foot { padding: 18px !important; }

            .e-logo { font-size: 25px !important; }
            .e-greeting { font-size: 21px !important; }
            .e-title { font-size: 18px !important; }
            .e-text, .e-total, .e-content p, .e-content li { font-size: 16px !important; }
            .e-small, .e-title-sm, .e-td { font-size: 15px !important; }
            .e-note { font-size: 14px !important; }
            .e-th { font-size: 14px !important; }
            .e-tiny, .e-footer-text { font-size: 13px !important; }

            .e-btn { font-size: 17px !important; padding: 14px 29px !important; }
        }
    </style>
</head>
<body style="margin:0;padding:0;background-color:#F5F5F5;-webkit-text-size-adjust:100%;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#F5F5F5;border-collapse:collapse;">
        <tr>
            <td align="center" style="padding:24px 12px;">
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" style="width:100%;max-width:600px;background-color:#FFFFFF;border-radius:12px;border:1px solid #E5E5E5;border-collapse:separate;">
                    <tr>
                        <td class="e-head" style="background-color:#FFF8FA;padding:32px 40px 24px;text-align:center;border-top-left-radius:12px;border-top-right-radius:12px;">
                            <span class="e-logo" style="font-family:'Montserrat',Arial,sans-serif;font-size:22px;font-weight:500;color:#1A1A1A;">Friendly</span><span class="e-logo" style="font-family:'Montserrat',Arial,sans-serif;font-size:22px;font-weight:600;font-style:italic;color:#ED86A3;">Fyzio</span>
                        </td>
                    </tr>
                    <tr>
                        <td style="height:3px;background-color:#ED86A3;font-size:0;line-height:0;">&nbsp;</td>
                    </tr>
                    <tr>
                        <td class="e-content" style="padding:40px 48px;">
                            {!! $body !!}
                        </td>
                    </tr>
                    <tr>
                        <td class="e-foot" style="background-color:#F5F5F5;padding:24px 48px;text-align:center;border-bottom-left-radius:12px;border-bottom-right-radius:12px;">
                            <div class="e-footer-text" style="font-family:'Open Sans',Arial,sans-serif;font-size:11px;color:#888888;padding-bottom:6px;">{{ $footerAddress }}</div>
                            @if(filled($footerContact))
                                <div class="e-footer-text" style="font-family:'Open Sans',Arial,sans-serif;font-size:11px;color:#888888;padding-bottom:10px;">{{ $footerContact }}</div>
                            @endif
                            @if($socialLinks)
                                <table role="presentation" cellpadding="0" cellspacing="0" border="0" align="center" style="border-collapse:collapse;margin:0 auto;">
                                    <tr>
                                        @foreach($socialLinks as $icon => $url)
                                            <td style="padding:0 8px;">
                                                <a href="{{ $url }}" style="text-decoration:none;">{!! \App\Support\Icon::render($icon, '', ['style' => 'width:18px;height:18px;display:block;color:#888888;']) !!}</a>
                                            </td>
                                        @endforeach
                                    </tr>
                                </table>
                            @endif
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>

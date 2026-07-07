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
</head>
<body style="margin:0;padding:0;background-color:#F5F5F5;-webkit-text-size-adjust:100%;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#F5F5F5;border-collapse:collapse;">
        <tr>
            <td align="center" style="padding:24px 12px;">
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" style="width:600px;max-width:600px;background-color:#FFFFFF;border-radius:12px;border:1px solid #E5E5E5;border-collapse:separate;">
                    <tr>
                        <td style="background-color:#FFF8FA;padding:32px 40px 24px;text-align:center;border-top-left-radius:12px;border-top-right-radius:12px;">
                            <span style="font-family:'Montserrat',Arial,sans-serif;font-size:22px;font-weight:500;color:#1A1A1A;">Friendly</span><span style="font-family:'Montserrat',Arial,sans-serif;font-size:22px;font-weight:600;font-style:italic;color:#ED86A3;">Fyzio</span>
                        </td>
                    </tr>
                    <tr>
                        <td style="height:3px;background-color:#ED86A3;font-size:0;line-height:0;">&nbsp;</td>
                    </tr>
                    <tr>
                        <td style="padding:40px 48px;">
                            {!! $body !!}
                        </td>
                    </tr>
                    <tr>
                        <td style="background-color:#F5F5F5;padding:24px 48px;text-align:center;border-bottom-left-radius:12px;border-bottom-right-radius:12px;">
                            <div style="font-family:'Open Sans',Arial,sans-serif;font-size:11px;color:#888888;padding-bottom:6px;">{{ $footerAddress }}</div>
                            @if(filled($footerContact))
                                <div style="font-family:'Open Sans',Arial,sans-serif;font-size:11px;color:#888888;padding-bottom:10px;">{{ $footerContact }}</div>
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

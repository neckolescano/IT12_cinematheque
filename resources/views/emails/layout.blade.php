{{-- Email frame: table layout + inline styles, because email clients ignore most CSS. --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <title>@yield('title')</title>
</head>
<body style="margin:0;padding:0;background:#f4f2ef;font-family:Arial,Helvetica,sans-serif;color:#141219;">
<span style="display:none;max-height:0;overflow:hidden;opacity:0;">@yield('preheader')</span>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f2ef;">
    <tr>
        <td align="center" style="padding:24px 12px;">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;background:#ffffff;border-radius:12px;overflow:hidden;">
                <tr>
                    <td style="background:#141219;padding:20px 28px;">
                        <div style="color:#ffffff;font-size:18px;font-weight:bold;letter-spacing:1.5px;">CINEMATHEQUE</div>
                        <div style="color:#ebbc00;font-size:11px;font-weight:bold;letter-spacing:3px;">CENTRE DAVAO</div>
                    </td>
                </tr>
                <tr>
                    <td style="background:@yield('status_bg');color:@yield('status_fg');padding:12px 28px;font-size:13px;font-weight:bold;letter-spacing:.5px;">
                        @yield('status')
                    </td>
                </tr>
                <tr>
                    <td style="padding:28px;font-size:15px;line-height:1.55;">
                        @yield('content')
                    </td>
                </tr>
                <tr>
                    <td style="padding:18px 28px;background:#faf9f7;border-top:1px solid #ece9e4;font-size:12px;line-height:1.5;color:#6b6674;">
                        Cinematheque Centre Davao · Davao City<br>
                        This email was sent because a reservation was made with this address. Please don't reply to this email.
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>

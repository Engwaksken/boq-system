<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <title>{{ __('You’re invited to join :organisation', ['organisation' => $organisation]) }}</title>
</head>
<body style="margin:0;padding:0;background-color:#f1f5f9;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f1f5f9;">
        <tr>
            <td align="center" style="padding:32px 16px;">
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" style="width:600px;max-width:600px;background-color:#ffffff;border-radius:12px;">
                    <tr>
                        <td bgcolor="#05645b" style="background-color:#05645b;padding:22px 28px;font-family:Arial,Helvetica,sans-serif;">
                            @if($logoUrl)
                                <img src="{{ $logoUrl }}" alt="{{ $organisation }}" width="170" style="display:block;max-width:170px;max-height:56px;margin:0 0 12px;border:0;outline:none;text-decoration:none;">
                            @endif
                            <div style="font-size:17px;font-weight:bold;color:#ffffff;">{{ $organisation }}</div>
                            <div style="font-size:12px;letter-spacing:.08em;text-transform:uppercase;color:#cdece8;">{{ $platformName }}</div>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:28px;font-family:Arial,Helvetica,sans-serif;color:#1e293b;font-size:15px;line-height:1.6;">
                            <h1 style="margin:0 0 16px;font-size:24px;line-height:1.3;color:#0f172a;">{{ __('You’re invited to join :organisation', ['organisation' => $organisation]) }}</h1>
                            <p style="margin:0 0 16px;">{{ __(':name invited you to join their team on :platform.', ['name' => $inviter, 'platform' => $platformName]) }}</p>

                            @if($role)
                                <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 16px;">
                                    <tr>
                                        <td bgcolor="#e6f2f1" style="background-color:#e6f2f1;border-radius:999px;padding:6px 14px;font-size:13px;font-weight:bold;color:#05645b;">{{ __('You will join as :role.', ['role' => $role]) }}</td>
                                    </tr>
                                </table>
                            @endif

                            <p style="margin:0 0 16px;">{{ __('Sign in or create an account using this email address, then enter this invitation code on the Team Invitations page:') }}</p>

                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 24px;">
                                <tr>
                                    <td align="center" bgcolor="#f8fafc" style="background-color:#f8fafc;border:1px solid #cbd5e1;border-radius:8px;padding:18px 12px;font-size:30px;font-weight:bold;letter-spacing:6px;color:#0f172a;">{{ $code }}</td>
                                </tr>
                            </table>

                            <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 20px;">
                                <tr>
                                    <td style="padding:0 8px 0 0;">
                                        <table role="presentation" cellpadding="0" cellspacing="0" border="0">
                                            <tr>
                                                <td bgcolor="#05645b" style="background-color:#05645b;border-radius:6px;">
                                                    <a href="{{ $acceptUrl }}" style="display:inline-block;padding:11px 20px;font-family:Arial,Helvetica,sans-serif;font-size:14px;font-weight:bold;color:#ffffff;text-decoration:none;">{{ __('Open Team Invitations') }}</a>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                    @if($registerUrl)
                                        <td>
                                            <table role="presentation" cellpadding="0" cellspacing="0" border="0">
                                                <tr>
                                                    <td bgcolor="#ffffff" style="background-color:#ffffff;border:1px solid #05645b;border-radius:6px;">
                                                        <a href="{{ $registerUrl }}" style="display:inline-block;padding:10px 20px;font-family:Arial,Helvetica,sans-serif;font-size:14px;font-weight:bold;color:#05645b;text-decoration:none;">{{ __('Create an account') }}</a>
                                                    </td>
                                                </tr>
                                            </table>
                                        </td>
                                    @endif
                                </tr>
                            </table>

                            <p style="margin:0;color:#64748b;font-size:13px;">{{ __('This code expires at :time.', ['time' => $expiresAt->toDayDateTimeString()]) }}</p>
                        </td>
                    </tr>
                </table>

                <table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" style="width:600px;max-width:600px;">
                    <tr>
                        <td align="center" style="padding:16px 12px 0;font-family:Arial,Helvetica,sans-serif;font-size:12px;color:#94a3b8;">
                            @if($platformUrl)<a href="{{ $platformUrl }}" style="color:#94a3b8;text-decoration:none;">{{ $platformName }}</a>@else{{ $platformName }}@endif
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>

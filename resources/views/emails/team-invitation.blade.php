<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<body style="margin:0;background:#f1f5f9;color:#1e293b;font-family:Arial,sans-serif;line-height:1.6">
    <div style="max-width:600px;margin:32px auto;padding:0 16px">
        <div style="overflow:hidden;border-radius:12px;background:#fff;box-shadow:0 4px 18px rgba(15,23,42,.08)">
            <div style="background:#05645b;padding:22px 28px;color:#fff">
                @if($logoUrl)<img src="{{ $logoUrl }}" alt="{{ $organisation }}" style="display:block;max-width:170px;max-height:56px;margin-bottom:12px">@endif
                <div style="font-size:17px;font-weight:bold">{{ $organisation }}</div>
                <div style="font-size:12px;letter-spacing:.08em;text-transform:uppercase;opacity:.85">{{ $platformName }}</div>
            </div>
            <div style="padding:28px">
                <h1 style="margin:0 0 16px;font-size:24px;line-height:1.3">{{ __('You’re invited to join :organisation', ['organisation' => $organisation]) }}</h1>
                <p>{{ __(':name invited you to join their team on :platform.', ['name' => $inviter, 'platform' => $platformName]) }}</p>

                @if($role)
                    <p style="margin:16px 0">
                        <span style="display:inline-block;background:#e6f2f1;color:#05645b;border-radius:999px;padding:4px 12px;font-weight:bold;font-size:13px">{{ __('You will join as :role.', ['role' => $role]) }}</span>
                    </p>
                @endif

                <p>{{ __('Sign in or create an account using this email address, then enter this invitation code on the Team Invitations page:') }}</p>

                <div style="margin:24px 0;padding:18px;border:1px solid #cbd5e1;border-radius:8px;background:#f8fafc;text-align:center;font-size:30px;font-weight:bold;letter-spacing:8px">{{ $code }}</div>

                <p style="margin:24px 0">
                    <a href="{{ $acceptUrl }}" style="display:inline-block;border-radius:6px;background:#05645b;padding:11px 18px;color:#fff;text-decoration:none;font-weight:bold">{{ __('Open Team Invitations') }}</a>
                    @if($registerUrl)
                        <a href="{{ $registerUrl }}" style="display:inline-block;margin-left:8px;border-radius:6px;background:#fff;border:1px solid #05645b;padding:10px 18px;color:#05645b;text-decoration:none;font-weight:bold">{{ __('Create an account') }}</a>
                    @endif
                </p>

                <p style="color:#64748b;font-size:13px">{{ __('This code expires at :time.', ['time' => $expiresAt->toDayDateTimeString()]) }}</p>
            </div>
        </div>
        <p style="padding:0 12px;text-align:center;color:#94a3b8;font-size:12px">
            @if($platformUrl)<a href="{{ $platformUrl }}" style="color:#94a3b8">{{ $platformName }}</a>@else{{ $platformName }}@endif
        </p>
    </div>
</body>
</html>

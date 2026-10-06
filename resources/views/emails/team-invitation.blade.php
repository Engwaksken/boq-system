<h1>{{ __('You’re invited to join :organisation', ['organisation' => $organisation]) }}</h1>
<p>{{ __(':name invited you to join their team on the BOQ platform.', ['name' => $inviter]) }}</p>
<p>{{ __('Sign in or create an account using this email address, then enter this invitation code on the Team Invitations page:') }}</p>
<p style="font-size: 24px; font-weight: bold; letter-spacing: 4px">{{ $code }}</p>
<p><a href="{{ $acceptUrl }}">{{ __('Open Team Invitations') }}</a></p>
<p>{{ __('This code expires at :time.', ['time' => $expiresAt->toDayDateTimeString()]) }}</p>

<!DOCTYPE html>
<html>
<body style="font-family: Arial, sans-serif; color: #1e293b; line-height: 1.5;">
    <p>{{ __('Hello,') }}</p>

    <p>{{ __(':sender from :company asks you to review and sign a Bill of Quantities.', ['sender' => $sender->name, 'company' => $company]) }}</p>

    <table cellpadding="4" style="border-collapse: collapse; margin: 12px 0;">
        <tr><td style="color:#64748b">{{ __('BOQ') }}</td><td><strong>{{ $boq->name }}</strong></td></tr>
        <tr><td style="color:#64748b">{{ __('Reference') }}</td><td>{{ $boq->reference }}</td></tr>
        @if($boq->project)
            <tr><td style="color:#64748b">{{ __('Project') }}</td><td>{{ $boq->project->name }}</td></tr>
        @endif
    </table>

    @if($personalMessage)
        <p style="white-space: pre-line; border-left: 3px solid #05645b; padding-left: 10px;">{{ $personalMessage }}</p>
    @endif

    <p style="margin: 20px 0;">
        <a href="{{ $url }}" style="display: inline-block; background: #05645b; color: #ffffff; padding: 10px 18px; border-radius: 6px; text-decoration: none; font-weight: bold;">{{ __('Review and sign') }}</a>
    </p>

    <p style="color:#64748b; font-size: 12px;">
        @if($expiresAt)
            {{ __('This link can be used once and expires on :date.', ['date' => \App\Support\Format::date($expiresAt)]) }}
        @endif
        {{ __('Reply to this email to contact :sender directly.', ['sender' => $sender->name]) }}
    </p>

    <p style="color:#94a3b8; font-size: 11px; word-break: break-all;">{{ $url }}</p>
</body>
</html>

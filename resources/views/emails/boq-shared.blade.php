<!DOCTYPE html>
<html>
<body style="font-family: Arial, sans-serif; color: #1e293b; line-height: 1.5;">
    <p>{{ __('Hello,') }}</p>

    <p>{{ __(':sender from :company has shared a Bill of Quantities with you.', ['sender' => $sender->name, 'company' => $company]) }}</p>

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

    <p>{{ __('The BOQ is attached as a PDF.') }}</p>

    <p style="color:#64748b; font-size: 12px;">{{ __('Reply to this email to contact :sender directly.', ['sender' => $sender->name]) }}</p>
</body>
</html>

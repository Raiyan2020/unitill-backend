<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Contact Us message #{{ $contactMessage->id }}</title>
</head>
<body style="margin:0;padding:24px;background:#f6f7f9;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Arial,sans-serif;color:#14181f;">
<div style="max-width:640px;margin:0 auto;background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:24px;">
    <h2 style="margin:0 0 4px;font-size:18px;">New Contact Us message</h2>
    <p style="margin:0 0 20px;color:#6b7280;font-size:14px;">Reference #{{ $contactMessage->id }}</p>

    <table style="width:100%;border-collapse:collapse;font-size:14px;">
        <tr>
            <td style="padding:8px 0;color:#6b7280;width:35%;">Reason</td>
            <td style="padding:8px 0;">{{ $reason ?: '—' }}</td>
        </tr>
        <tr>
            <td style="padding:8px 0;color:#6b7280;border-top:1px solid #e5e7eb;">From</td>
            <td style="padding:8px 0;border-top:1px solid #e5e7eb;">
                {{ $sender?->name ?: $sender?->first_name ?: 'Unknown user' }} (ID {{ $sender?->id ?: '—' }})
            </td>
        </tr>
        <tr>
            <td style="padding:8px 0;color:#6b7280;border-top:1px solid #e5e7eb;">Email</td>
            <td style="padding:8px 0;border-top:1px solid #e5e7eb;">{{ $sender?->email ?: '—' }}</td>
        </tr>
        <tr>
            <td style="padding:8px 0;color:#6b7280;border-top:1px solid #e5e7eb;">Student email</td>
            <td style="padding:8px 0;border-top:1px solid #e5e7eb;">{{ $sender?->student_email ?: '—' }}</td>
        </tr>
        <tr>
            <td style="padding:8px 0;color:#6b7280;border-top:1px solid #e5e7eb;">Phone</td>
            <td style="padding:8px 0;border-top:1px solid #e5e7eb;">{{ $sender?->phone ?: '—' }}</td>
        </tr>
        <tr>
            <td style="padding:8px 0;color:#6b7280;border-top:1px solid #e5e7eb;">Received</td>
            <td style="padding:8px 0;border-top:1px solid #e5e7eb;">{{ $contactMessage->created_at?->toDayDateTimeString() }}</td>
        </tr>
    </table>

    <h3 style="margin:24px 0 8px;font-size:15px;">Message</h3>
    <div style="white-space:pre-wrap;background:#f6f7f9;border:1px solid #e5e7eb;border-radius:8px;padding:14px;font-size:14px;line-height:1.6;">{{ $contactMessage->message }}</div>
</div>
</body>
</html>

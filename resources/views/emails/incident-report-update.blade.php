<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $eventTitle }}</title>
</head>
<body style="margin: 0; background: #f6f7f2; color: #173f3b; font-family: Arial, sans-serif;">
    <div style="max-width: 560px; margin: 0 auto; padding: 40px 20px;">
        <div style="background: #ffffff; border: 1px solid #dce3df; border-radius: 8px; padding: 36px;">
            <p style="margin: 0 0 12px; color: #b17b28; font-size: 12px; font-weight: bold; letter-spacing: 2px; text-transform: uppercase;">Dian-ay NHS Inventory</p>
            <h1 style="margin: 0 0 16px; color: #173f3b; font-size: 24px;">{{ $eventTitle }}</h1>
            <p style="color: #53635e; font-size: 16px; line-height: 1.6;">Hello {{ $reporterName }},</p>
            <p style="color: #53635e; font-size: 16px; line-height: 1.6;">There is an update about your incident report for <strong>{{ $itemName }}</strong>.</p>
            <p style="color: #53635e; font-size: 16px; line-height: 1.6;">{{ $updateMessage }}</p>
            <p style="margin: 28px 0;">
                <a href="{{ $reportUrl }}" style="display: inline-block; border-radius: 6px; background: #059669; padding: 12px 18px; color: #ffffff; font-weight: bold; text-decoration: none;">View My Incident Reports</a>
            </p>
            <p style="color: #71807b; font-size: 13px; line-height: 1.6;">You can review the report timeline in My Assigned Items.</p>
        </div>
    </div>
</body>
</html>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Password reset code</title>
</head>
<body style="margin: 0; background: #f6f7f2; color: #173f3b; font-family: Arial, sans-serif;">
    <div style="max-width: 560px; margin: 0 auto; padding: 40px 20px;">
        <div style="background: #ffffff; border: 1px solid #dce3df; border-radius: 8px; padding: 36px;">
            <p style="margin: 0 0 12px; color: #b17b28; font-size: 12px; font-weight: bold; letter-spacing: 2px; text-transform: uppercase;">Dian-ay NHS Inventory</p>
            <h1 style="margin: 0 0 16px; color: #173f3b; font-size: 28px;">Password reset code</h1>
            <p style="margin: 0 0 24px; color: #53635e; font-size: 16px; line-height: 1.6;">Use the code below to continue resetting your password.</p>
            <div style="margin: 0 0 24px; background: #f6f7f2; border: 1px solid #e7b86a; border-radius: 8px; padding: 18px; text-align: center;">
                <span style="color: #173f3b; font-size: 32px; font-weight: bold; letter-spacing: 8px;">{{ $otp }}</span>
            </div>
            <p style="margin: 0; color: #71807b; font-size: 14px; line-height: 1.6;">This code expires in 5 minutes. If you did not request a password reset, you can safely ignore this email.</p>
        </div>
    </div>
</body>
</html>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Billing OTP Verification</title>
</head>
<body style="margin:0; padding:0; background:#f6f7f8; font-family:Arial, Helvetica, sans-serif; color:#1f2937;">
    <div style="max-width:720px; margin:0 auto; padding:32px 16px;">
        <div style="background:#ffffff; border:1px solid #e5e7eb; border-radius:18px; overflow:hidden; box-shadow:0 10px 30px rgba(0,0,0,0.06);">
            <div style="padding:28px 32px 10px;">
                <h1 style="margin:0 0 10px; font-size:26px; line-height:1.2; color:#085041;">OTP Verification Required</h1>
                <p style="margin:0 0 18px; font-size:15px; line-height:1.6; color:#4b5563;">
                    <strong>{{ $actionLabel }}</strong> was requested by <strong>{{ $requestedBy }}</strong>.
                    Please review the attached Proforma Invoice and share this OTP only if the request is valid.
                </p>
                <div style="display:inline-block; padding:14px 18px; border-radius:14px; background:#f0fdf4; border:1px solid #bbf7d0; font-size:20px; font-weight:700; letter-spacing:0.28em; color:#065f46;">
                    {{ $otp }}
                </div>
            </div>

            <div style="padding:22px 32px 12px;">
                <table style="width:100%; border-collapse:collapse; font-size:14px;">
                    <tr>
                        <td style="padding:10px 12px; background:#f3f4f6; border:1px solid #e5e7eb; font-weight:700;">PI</td>
                        <td style="padding:10px 12px; border:1px solid #e5e7eb;">{{ $documentNumber }}</td>
                    </tr>
                    <tr>
                        <td style="padding:10px 12px; background:#f3f4f6; border:1px solid #e5e7eb; font-weight:700;">Customer</td>
                        <td style="padding:10px 12px; border:1px solid #e5e7eb;">{{ $customerName }}</td>
                    </tr>
                    <tr>
                        <td style="padding:10px 12px; background:#f3f4f6; border:1px solid #e5e7eb; font-weight:700;">Total</td>
                        <td style="padding:10px 12px; border:1px solid #e5e7eb;">₹{{ number_format($totalAmount, 2) }}</td>
                    </tr>
                </table>
            </div>

            <div style="padding:0 32px 28px; color:#4b5563; font-size:14px; line-height:1.65;">
                <p style="margin:0 0 8px;"><strong>OTP valid until:</strong> {{ \Illuminate\Support\Carbon::parse($expiresAt)->format('M d, Y h:i A') }}</p>
                <p style="margin:0;">If you did not request this cancellation, do not share the OTP and inform the Avirqo team.</p>
            </div>
        </div>
    </div>
</body>
</html>

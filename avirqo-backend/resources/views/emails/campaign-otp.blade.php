<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Campaign OTP Verification</title>
</head>
<body style="margin:0; padding:0; background:#f6f7f8; font-family:Arial, Helvetica, sans-serif; color:#1f2937;">
    <div style="max-width:760px; margin:0 auto; padding:32px 16px;">
        <div style="background:#ffffff; border:1px solid #e5e7eb; border-radius:18px; overflow:hidden; box-shadow:0 10px 30px rgba(0,0,0,0.06);">
            <div style="padding:28px 32px 8px;">
                <h1 style="margin:0 0 10px; font-size:26px; line-height:1.2; color:#085041;">OTP Verification Required</h1>
                <p style="margin:0 0 18px; font-size:15px; line-height:1.6; color:#4b5563;">
                    {{ $contextLabel }} for campaign <strong>{{ $campaign->name }}</strong> was requested by <strong>{{ $requestedBy }}</strong>.
                    Please verify the OTP within 10 minutes to approve these campaign changes.
                </p>
                <div style="display:inline-block; padding:14px 18px; border-radius:14px; background:#f0fdf4; border:1px solid #bbf7d0; font-size:20px; font-weight:700; letter-spacing:0.28em; color:#065f46;">
                    {{ $otp }}
                </div>
            </div>

            <div style="padding:24px 32px 18px;">
                <h2 style="margin:0 0 14px; font-size:18px; color:#111827;">Change summary</h2>
                <table style="width:100%; border-collapse:collapse; font-size:14px;">
                    <thead>
                        <tr>
                            <th style="text-align:left; padding:10px 12px; background:#f3f4f6; border-bottom:1px solid #e5e7eb;">Field</th>
                            <th style="text-align:left; padding:10px 12px; background:#f3f4f6; border-bottom:1px solid #e5e7eb;">Old</th>
                            <th style="text-align:left; padding:10px 12px; background:#f3f4f6; border-bottom:1px solid #e5e7eb;">New</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($changes as $change)
                            <tr>
                                <td style="padding:10px 12px; border-bottom:1px solid #eef2f7; font-weight:600;">{{ $change['label'] ?? 'Change' }}</td>
                                <td style="padding:10px 12px; border-bottom:1px solid #eef2f7; color:#4b5563;">{{ $change['old'] ?? '—' }}</td>
                                <td style="padding:10px 12px; border-bottom:1px solid #eef2f7; color:#b91c1c; font-weight:600;">{{ $change['new'] ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" style="padding:14px 12px; border-bottom:1px solid #eef2f7; color:#6b7280;">
                                    No specific field differences were captured. Please verify to approve the request.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div style="padding:0 32px 28px; color:#4b5563; font-size:14px; line-height:1.65;">
                <p style="margin:0 0 8px;"><strong>OTP valid until:</strong> {{ \Illuminate\Support\Carbon::parse($expiresAt)->format('M d, Y h:i A') }}</p>
                <p style="margin:0;">If you did not request this, you can ignore this email.</p>
            </div>
        </div>
    </div>
</body>
</html>

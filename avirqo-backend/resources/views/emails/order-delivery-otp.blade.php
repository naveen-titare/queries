<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Voucher Download OTP</title>
</head>
<body style="font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; line-height: 1.6; color: #333; max-width: 760px; margin: 0 auto; padding: 20px; background-color: #f8f9fa;">
    <div style="background: white; border-radius: 14px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.08);">
        <div style="background: linear-gradient(135deg, #1d9e75 0%, #147a5c 100%); padding: 30px; text-align: center;">
            <div style="background: rgba(255,255,255,0.2); border-radius: 50%; width: 80px; height: 80px; margin: 0 auto 16px; display: flex; align-items: center; justify-content: center;">
                <span style="font-size: 36px;">🔐</span>
            </div>
            <h1 style="color: white; margin: 0; font-size: 24px; font-weight: 600;">OTP Verification Required</h1>
            <p style="color: rgba(255,255,255,0.9); margin: 8px 0 0; font-size: 14px;">Order #{{ $order->order_number }}</p>
        </div>

        <div style="padding: 30px;">
            <div style="background: #fff3cd; border: 1px solid #ffc107; border-radius: 12px; padding: 18px 20px; margin-bottom: 24px; text-align: center;">
                <div style="font-size: 13px; color: #856404; margin-bottom: 8px; text-transform: uppercase; letter-spacing: 0.08em;">OTP</div>
                <div style="font-size: 30px; font-weight: 700; color: #856404; letter-spacing: 8px; font-family: monospace;">{{ $otp }}</div>
                <div style="font-size: 13px; color: #664d03; margin-top: 8px;">This OTP expires in <strong>5 minutes</strong>.</div>
            </div>

            <p style="font-size: 15px; color: #444; margin-bottom: 16px;">
                Hello <strong>{{ $spoc->name }}</strong>,
            </p>
            <p style="font-size: 15px; color: #444; margin-bottom: 20px;">
                We received a request to download the vouchers for this order. Use the OTP above to confirm the download.
            </p>

            <div style="background: #f8f9fa; border-radius: 10px; padding: 20px; margin-bottom: 24px;">
                <h3 style="margin: 0 0 16px; font-size: 16px; color: #1d9e75; border-bottom: 2px solid #1d9e75; padding-bottom: 8px;">📋 Download steps</h3>
                <ol style="margin: 0; padding-left: 20px; color: #444; font-size: 14px;">
                    <li style="margin-bottom: 10px;">Open the secure delivery page: <a href="{{ $deliveryUrl }}" style="color: #147a5c; word-break: break-all;">{{ $deliveryUrl }}</a></li>
                    <li style="margin-bottom: 10px;">Enter the OTP on the page while keeping the secret key private.</li>
                    <li style="margin-bottom: 10px;">Download the Excel file once OTP verification succeeds.</li>
                    <li>After download, the secret key will be marked as used automatically.</li>
                </ol>
            </div>

            <div style="background: #eff6ff; border: 1px solid #93c5fd; border-radius: 12px; padding: 18px 20px; margin-bottom: 24px;">
                <h4 style="margin: 0 0 10px; color: #1d4ed8; font-size: 15px;">🛡️ If this OTP is compromised</h4>
                <p style="margin: 0; color: #1e3a8a; font-size: 14px; line-height: 1.6;">
                    Do not share this OTP with anyone. If you suspect the OTP was exposed, simply let it expire or request a new one through the delivery page again.
                    For extra safety, you can also resend the secret-key email from order history to invalidate the old delivery key.
                </p>
            </div>

            <div style="background: #fff7ed; border-left: 4px solid #f59e0b; padding: 16px; border-radius: 6px;">
                <p style="margin: 0; font-size: 13px; color: #92400e;">
                    <strong>Security notice:</strong> This OTP is valid until {{ \Illuminate\Support\Carbon::parse($expiresAt)->format('d M Y, h:i A') }}.
                    If you did not request the download, contact the admin team immediately.
                </p>
            </div>
        </div>
    </div>
</body>
</html>

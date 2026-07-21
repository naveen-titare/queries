<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Voucher Delivery Access</title>
</head>
<body style="font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; line-height: 1.6; color: #333; max-width: 760px; margin: 0 auto; padding: 20px; background-color: #f8f9fa;">
    <div style="background: white; border-radius: 14px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.08);">
        <div style="background: linear-gradient(135deg, #1d9e75 0%, #147a5c 100%); padding: 30px; text-align: center;">
            <div style="background: rgba(255,255,255,0.2); border-radius: 50%; width: 80px; height: 80px; margin: 0 auto 16px; display: flex; align-items: center; justify-content: center;">
                <span style="font-size: 36px;">🔑</span>
            </div>
            <h1 style="color: white; margin: 0; font-size: 24px; font-weight: 600;">Voucher delivery access</h1>
            <p style="color: rgba(255,255,255,0.9); margin: 8px 0 0; font-size: 14px;">Order #{{ $order->order_number }}</p>
        </div>

        <div style="padding: 30px;">
            <div style="background: #fff7ed; border: 1px solid #fdba74; border-radius: 12px; padding: 18px 20px; margin-bottom: 24px; text-align: center;">
                <div style="font-size: 13px; color: #9a3412; margin-bottom: 8px; text-transform: uppercase; letter-spacing: 0.08em;">Secret key</div>
                <div style="font-size: 28px; font-weight: 700; color: #c2410c; letter-spacing: 4px; font-family: monospace;">{{ $secretKey }}</div>
                <div style="font-size: 13px; color: #7c2d12; margin-top: 8px;">Valid for <strong>15 days</strong> and can be used only once.</div>
            </div>

            <p style="font-size: 15px; color: #444; margin-bottom: 16px;">
                Hello <strong>{{ $spoc->name }}</strong>,
            </p>
            <p style="font-size: 15px; color: #444; margin-bottom: 20px;">
                Your vouchers are ready for secure download. Please use the public delivery page and follow the steps below.
            </p>

            <div style="background: #f8f9fa; border-radius: 10px; padding: 20px; margin-bottom: 24px;">
                <h3 style="margin: 0 0 16px; font-size: 16px; color: #1d9e75; border-bottom: 2px solid #1d9e75; padding-bottom: 8px;">📋 Download steps</h3>
                <ol style="margin: 0; padding-left: 20px; color: #444; font-size: 14px;">
                    <li style="margin-bottom: 10px;">Open the secure delivery page: <a href="{{ $deliveryUrl }}" style="color: #147a5c; word-break: break-all;">{{ $deliveryUrl }}</a></li>
                    <li style="margin-bottom: 10px;">Enter your email address and the secret key shown above.</li>
                    <li style="margin-bottom: 10px;">The system will send a 6-digit OTP to the SPOC email stored against this order.</li>
                    <li style="margin-bottom: 10px;">Enter the OTP to unlock the Excel download.</li>
                    <li>Download the Excel file once. After download, the secret key becomes invalid automatically.</li>
                </ol>
            </div>

            <div style="background: #eff6ff; border: 1px solid #93c5fd; border-radius: 12px; padding: 18px 20px; margin-bottom: 24px;">
                <h4 style="margin: 0 0 10px; color: #1d4ed8; font-size: 15px;">🛡️ If this message is compromised</h4>
                <p style="margin: 0; color: #1e3a8a; font-size: 14px; line-height: 1.6;">
                    If the secret key may have been shared or exposed, do not use it. Please request a fresh resend from the order history screen so the old key gets invalidated.
                    If you suspect the recipient email itself is compromised, contact the admin team immediately before downloading anything.
                </p>
            </div>

            <div style="background: #fff7ed; border-left: 4px solid #f59e0b; padding: 16px; border-radius: 6px; margin-bottom: 24px;">
                <p style="margin: 0; font-size: 13px; color: #92400e;">
                    <strong>Security notice:</strong> This secret key is valid until {{ \Illuminate\Support\Carbon::parse($expiresAt)->format('d M Y, h:i A') }} and is tied to order <strong>{{ $order->order_number }}</strong>.
                    Keep it private and use it only on the official Avirqo delivery page.
                </p>
            </div>
        </div>
    </div>
</body>
</html>

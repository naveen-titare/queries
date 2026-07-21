<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OTP Verification - SPOC Switch</title>
</head>
<body style="font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; line-height: 1.6; color: #333; max-width: 700px; margin: 0 auto; padding: 20px; background-color: #f8f9fa;">
    <div style="background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.08);">
        <div style="background: linear-gradient(135deg, #1d9e75 0%, #147a5c 100%); padding: 30px; text-align: center;">
            <div style="background: rgba(255,255,255,0.2); border-radius: 50%; width: 80px; height: 80px; margin: 0 auto 16px; display: flex; align-items: center; justify-content: center;">
                <span style="font-size: 36px;">🔐</span>
            </div>
            <h1 style="color: white; margin: 0; font-size: 24px; font-weight: 600;">OTP Verification Required</h1>
            <p style="color: rgba(255,255,255,0.9); margin: 8px 0 0; font-size: 14px;">SPOC switch for order #{{ $order->order_number }}</p>
        </div>

        <div style="padding: 30px;">
            <div style="background: #fff3cd; border: 1px solid #ffc107; border-radius: 8px; padding: 16px; margin-bottom: 24px; text-align: center;">
                <div style="font-size: 32px; font-weight: 700; color: #856404; letter-spacing: 8px; font-family: monospace;">{{ $otp }}</div>
                <div style="font-size: 13px; color: #664d03; margin-top: 8px;">This OTP expires in <strong>10 minutes</strong></div>
            </div>

            <p style="font-size: 15px; color: #444; margin-bottom: 20px;">
                Review and approve the SPOC update for <strong>{{ $customer->company_name }}</strong>.
            </p>

            <div style="background: #f8f9fa; border-radius: 8px; padding: 20px; margin-bottom: 24px;">
                <h3 style="margin: 0 0 16px; font-size: 16px; color: #1d9e75; border-bottom: 2px solid #1d9e75; padding-bottom: 8px;">🧾 SPOC Change Summary</h3>
                <table style="width: 100%; border-collapse: collapse; font-size: 13px;">
                    <tr>
                        <td style="padding: 8px 0; color: #666;">Order Number</td>
                        <td style="padding: 8px 0; font-weight: 600; text-align: right;">{{ $order->order_number }}</td>
                    </tr>
                    <tr>
                        <td style="padding: 8px 0; color: #666;">Current SPOC</td>
                        <td style="padding: 8px 0; font-weight: 600; text-align: right;">{{ $currentSpoc->name }} ({{ $currentSpoc->email }})</td>
                    </tr>
                    <tr>
                        <td style="padding: 8px 0; color: #666;">New SPOC</td>
                        <td style="padding: 8px 0; font-weight: 600; text-align: right;">{{ $newSpoc->name }} ({{ $newSpoc->email }})</td>
                    </tr>
                </table>
            </div>

            <div style="background: #e8f7f2; border: 1px solid #1d9e75; border-radius: 8px; padding: 20px; margin-bottom: 24px;">
                <h4 style="margin: 0 0 12px; color: #1d9e75; font-size: 15px;">📝 Next Steps</h4>
                <ol style="margin: 0; padding-left: 20px; color: #444; font-size: 14px;">
                    <li style="margin-bottom: 8px;">Enter the OTP <strong>{{ $otp }}</strong> on the order history screen</li>
                    <li style="margin-bottom: 8px;">The SPOC snapshot for this order will update after approval</li>
                    <li>Then continue with OTP verification or voucher resend as needed</li>
                </ol>
            </div>
        </div>
    </div>
</body>
</html>

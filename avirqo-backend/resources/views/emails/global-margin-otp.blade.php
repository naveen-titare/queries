<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OTP Verification - Global Margin Changes</title>
</head>
<body style="font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; line-height: 1.6; color: #333; max-width: 700px; margin: 0 auto; padding: 20px; background-color: #f8f9fa;">
    <div style="background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.08);">
        <div style="background: linear-gradient(135deg, #1d9e75 0%, #147a5c 100%); padding: 30px; text-align: center;">
            <div style="background: rgba(255,255,255,0.2); border-radius: 50%; width: 80px; height: 80px; margin: 0 auto 16px; display: flex; align-items: center; justify-content: center;">
                <span style="font-size: 36px;">🔐</span>
            </div>
            <h1 style="color: white; margin: 0; font-size: 24px; font-weight: 600;">OTP Verification Required</h1>
            <p style="color: rgba(255,255,255,0.9); margin: 8px 0 0; font-size: 14px;">Global margin update approval</p>
        </div>

        <div style="padding: 30px;">
            <div style="background: #fff3cd; border: 1px solid #ffc107; border-radius: 8px; padding: 16px; margin-bottom: 24px; text-align: center;">
                <div style="font-size: 32px; font-weight: 700; color: #856404; letter-spacing: 8px; font-family: monospace;">{{ $otp }}</div>
                <div style="font-size: 13px; color: #664d03; margin-top: 8px;">This OTP expires in <strong>10 minutes</strong></div>
            </div>

            <p style="font-size: 15px; color: #444; margin-bottom: 16px;">
                Requested by <strong>{{ $requestedBy }}</strong>.
            </p>

            <div style="background: #f8f9fa; border-radius: 8px; padding: 20px; margin-bottom: 24px;">
                <h3 style="margin: 0 0 16px; font-size: 16px; color: #1d9e75; border-bottom: 2px solid #1d9e75; padding-bottom: 8px;">📋 Margin &amp; Blacklist Changes</h3>

                @if(empty($changes))
                    <p style="margin: 0; font-size: 14px; color: #666;">No changes in Product Margin. Refer attachment for the Blacklisted changes.</p>
                @else
                <table style="width: 100%; border-collapse: collapse; font-size: 13px;">
                    <thead>
                        <tr style="background: #1d9e75; color: white;">
                            <th style="padding: 10px 12px; text-align: left;">Brand Name</th>
                            <th style="padding: 10px 12px; text-align: left;">Product Name</th>
                            <th style="padding: 10px 12px; text-align: right;">Old Margin %</th>
                            <th style="padding: 10px 12px; text-align: right;">New Margin %</th>
                            <th style="padding: 10px 12px; text-align: center;">Old Blacklist</th>
                            <th style="padding: 10px 12px; text-align: center;">New Blacklist</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($changes as $product)
                        <tr style="border-bottom: 1px solid #eee;">
                            <td style="padding: 10px 12px; font-weight: 600;">{{ $product['brand'] ?? '—' }}</td>
                            <td style="padding: 10px 12px;">
                                <div style="font-weight: 500;">{{ $product['name'] ?? '—' }}</div>
                            </td>
                            <td style="padding: 10px 12px; text-align: right;">{{ number_format((float) $product['old_margin_percentage'], 2) }}%</td>
                            <td style="padding: 10px 12px; text-align: right; font-weight: 600; color: {{ (float) $product['old_margin_percentage'] !== (float) $product['new_margin_percentage'] ? '#b91c1c' : '#333' }};">
                                {{ number_format((float) $product['new_margin_percentage'], 2) }}%
                            </td>
                            <td style="padding: 10px 12px; text-align: center;">{{ !empty($product['old_is_blacklisted']) ? 'Yes' : 'No' }}</td>
                            <td style="padding: 10px 12px; text-align: center; font-weight: 600; color: {{ (bool) $product['old_is_blacklisted'] !== (bool) $product['new_is_blacklisted'] ? '#b91c1c' : '#333' }};">
                                {{ !empty($product['new_is_blacklisted']) ? 'Yes' : 'No' }}
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
                @endif
            </div>

            <div style="background: #e8f7f2; border: 1px solid #1d9e75; border-radius: 8px; padding: 20px; margin-bottom: 24px;">
                <h4 style="margin: 0 0 12px; color: #1d9e75; font-size: 15px;">📝 Next Steps</h4>
                <ol style="margin: 0; padding-left: 20px; color: #444; font-size: 14px;">
                    <li style="margin-bottom: 8px;">Open the Voucher Campaigns page</li>
                    <li style="margin-bottom: 8px;">Enter the OTP <strong>{{ $otp }}</strong> in the global margin approval prompt</li>
                    <li>Click verify to save the margin and blacklist changes</li>
                </ol>
            </div>

            <div style="background: #fff3cd; border-left: 4px solid #ffc107; padding: 16px; border-radius: 4px; margin-bottom: 24px;">
                <p style="margin: 0; font-size: 13px; color: #856403;">
                    <strong>⚠ Security Notice:</strong> This OTP is valid for 10 minutes only. Do not share it with unauthorized personnel.
                </p>
            </div>

            <div style="background: #e8f7f2; border: 1px solid #1d9e75; border-radius: 8px; padding: 16px; margin-bottom: 24px;">
                <p style="margin: 0; font-size: 14px; color: #145c47;">
                    The full product catalog has been attached as Excel with Brand Name, Product Name, Old Margin %, New Margin %, Old Blacklist and New Blacklist.
                </p>
            </div>
        </div>

        <div style="background: #f8f9fa; padding: 20px; text-align: center; border-top: 1px solid #eee;">
            <p style="margin: 0; font-size: 11px; color: #aaa;">
                This is an automated message from Avirqo Global Margin Approval System. Please do not reply to this email.
            </p>
        </div>
    </div>
</body>
</html>

<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OTP Verification - Send Voucher Order</title>
</head>
<body style="font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; line-height: 1.6; color: #333; max-width: 700px; margin: 0 auto; padding: 20px; background-color: #f8f9fa;">
    <div style="background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.08);">
        <div style="background: linear-gradient(135deg, #1d9e75 0%, #147a5c 100%); padding: 30px; text-align: center;">
            <div style="background: rgba(255,255,255,0.2); border-radius: 50%; width: 80px; height: 80px; margin: 0 auto 16px; display: flex; align-items: center; justify-content: center;">
                <span style="font-size: 36px;">🔐</span>
            </div>
            <h1 style="color: white; margin: 0; font-size: 24px; font-weight: 600;">OTP Verification Required</h1>
            <p style="color: rgba(255,255,255,0.9); margin: 8px 0 0; font-size: 14px;">Send Voucher Order #{{ $order->order_number }}</p>
        </div>

        <div style="padding: 30px;">
            <div style="background: #fff3cd; border: 1px solid #ffc107; border-radius: 8px; padding: 16px; margin-bottom: 24px; text-align: center;">
                <div style="font-size: 32px; font-weight: 700; color: #856404; letter-spacing: 8px; font-family: monospace;">{{ $otp }}</div>
                <div style="font-size: 13px; color: #664d03; margin-top: 8px;">This OTP expires in <strong>10 minutes</strong></div>
            </div>

            <p style="font-size: 15px; color: #444; margin-bottom: 16px;">
                Hello <strong>{{ $spoc->name }}</strong>,
            </p>
            <p style="font-size: 15px; color: #444; margin-bottom: 20px;">
                An order has been initiated on your account. Please verify this order by providing the OTP above.
            </p>

            <div style="background: #f8f9fa; border-radius: 8px; padding: 20px; margin-bottom: 24px;">
                <h3 style="margin: 0 0 16px; font-size: 16px; color: #1d9e75; border-bottom: 2px solid #1d9e75; padding-bottom: 8px;">📋 Order Summary</h3>

                <table style="width: 100%; border-collapse: collapse; font-size: 13px;">
                    <thead>
                        <tr style="background: #1d9e75; color: white;">
                            <th style="padding: 10px 12px; text-align: left;">Brand</th>
                            <th style="padding: 10px 12px; text-align: center;">Denomination</th>
	                            <th style="padding: 10px 12px; text-align: center;">Qty</th>
	                            <th style="padding: 10px 12px; text-align: right;">Gross Total</th>
	                            <th style="padding: 10px 12px; text-align: right;">Margin (Internal)</th>
	                            <th style="padding: 10px 12px; text-align: right;">Discount</th>
	                            <th style="padding: 10px 12px; text-align: right;">Net Total</th>
	                        </tr>
                    </thead>
                    <tbody>
                        @foreach($items as $item)
	                        @php
	                            $grossTotal = (float) ($item['gross_total'] ?? 0);
		                            $marginPercentage = (float) ($item['global_margin_percentage'] ?? 0);
		                            $marginAmount = (float) ($item['global_margin_amount'] ?? 0);
		                            $discountPercentage = (float) ($item['discount_percentage'] ?? 0);
		                            $discountAmount = $order->pricing_mode === 'product' ? (float) ($item['discount_amount'] ?? 0) : 0;
		                            $netTotal = $order->pricing_mode === 'invoice'
		                                ? $grossTotal
		                                : $grossTotal - $discountAmount;
		                        @endphp
                        <tr style="border-bottom: 1px solid #eee;">
                            <td style="padding: 10px 12px;">
                                <div style="font-weight: 600; color: #111;">{{ $item['brand'] }}</div>
                                <div style="font-size: 12px; color: #666;">{{ $item['product'] }}</div>
                            </td>
                            <td style="padding: 10px 12px; text-align: center;">{{ $item['currency'] }} {{ number_format($item['denomination'], 2) }}</td>
	                            <td style="padding: 10px 12px; text-align: center;">{{ $item['quantity'] }}</td>
	                            <td style="padding: 10px 12px; text-align: right; font-weight: 600;">{{ $item['currency'] }} {{ number_format($grossTotal, 2) }}</td>
	                            <td style="padding: 10px 12px; text-align: right; color: #6b7280;">
	                                @if($marginPercentage != 0.0)
	                                    {{ number_format($marginPercentage, 2) }}% ({{ $item['currency'] }} {{ number_format($marginAmount, 2) }})
	                                @else
	                                    —
	                                @endif
	                            </td>
	                            <td style="padding: 10px 12px; text-align: right; color: {{ $discountPercentage < 0 ? '#b45309' : '#16a34a' }};">
                                @if($order->pricing_mode === 'product' && $discountPercentage != 0.0)
                                    @if($discountPercentage > 0)
                                        −{{ number_format($discountPercentage, 2) }}% (−{{ $item['currency'] }} {{ number_format(abs($discountAmount), 2) }})
                                    @else
                                        +{{ number_format(abs($discountPercentage), 2) }}% (+{{ $item['currency'] }} {{ number_format(abs($discountAmount), 2) }})
                                    @endif
                                @else
                                    —
                                @endif
                            </td>
                            <td style="padding: 10px 12px; text-align: right; font-weight: 700; color: #085041;">{{ $item['currency'] }} {{ number_format($netTotal, 2) }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr style="border-top: 1px solid #d9e7e1;">
	                            <td colspan="3" style="padding: 10px 12px; text-align: right; font-weight: 700;">Products subtotal</td>
	                            <td style="padding: 10px 12px; text-align: right; font-weight: 700;">{{ $items[0]['currency'] ?? '₹' }} {{ number_format(collect($items)->sum('gross_total'), 2) }}</td>
	                            <td style="padding: 10px 12px; text-align: right; font-weight: 700; color: #6b7280;">{{ $items[0]['currency'] ?? '₹' }} {{ number_format(collect($items)->sum('global_margin_amount'), 2) }}</td>
	                            <td style="padding: 10px 12px; text-align: right; font-weight: 700; color: {{ collect($items)->sum('discount_amount') < 0 ? '#b45309' : '#16a34a' }};">
	                                {{ $order->pricing_mode === 'product' ? (collect($items)->sum('discount_amount') >= 0 ? '−' : '+') . ($items[0]['currency'] ?? '₹') . ' ' . number_format(abs(collect($items)->sum('discount_amount')), 2) : '—' }}
	                            </td>
	                            <td style="padding: 10px 12px; text-align: right; font-weight: 700; color: #085041;">{{ $items[0]['currency'] ?? '₹' }} {{ number_format($order->pricing_mode === 'invoice' ? collect($items)->sum('gross_total') : collect($items)->sum('gross_total') - collect($items)->sum('discount_amount'), 2) }}</td>
	                        </tr>
	                        @if($order->pricing_mode === 'invoice' && (float) $order->invoice_discount_percentage !== 0.0)
	                        <tr style="border-top: 1px solid #d9e7e1;">
	                            <td colspan="5" style="padding: 10px 12px; text-align: right; font-weight: 700;">{{ $order->invoice_discount_percentage >= 0 ? 'Discount' : 'Service Charge' }} ({{ $order->invoice_discount_percentage }}%)</td>
                            <td style="padding: 10px 12px; text-align: right; font-weight: 700; color: {{ $order->invoice_discount_percentage < 0 ? '#b45309' : '#16a34a' }};">{{ $order->invoice_discount_amount >= 0 ? '−' : '+' }}{{ $items[0]['currency'] ?? '₹' }} {{ number_format(abs($order->invoice_discount_amount), 2) }}</td>
                            <td style="padding: 10px 12px; text-align: right; font-weight: 700; color: #085041;">{{ $items[0]['currency'] ?? '₹' }} {{ number_format($order->total_amount, 2) }}</td>
                        </tr>
                        @endif
                    </tfoot>
                </table>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 24px;">
                <div style="background: #f8f9fa; border-radius: 8px; padding: 16px;">
                    <h4 style="margin: 0 0 12px; font-size: 13px; color: #666; text-transform: uppercase; letter-spacing: 0.5px;">🏢 Customer</h4>
                    <p style="margin: 4px 0; font-weight: 600;">{{ $customer->company_name }}</p>
                    <p style="margin: 4px 0; font-size: 13px; color: #666;">{{ $customer->location }}</p>
                    <p style="margin: 4px 0; font-size: 13px; color: #666;">GST: {{ $customer->gst_number ?: '—' }}</p>
                    <p style="margin: 4px 0; font-size: 13px; color: #666;">Balance After Deduction: <span style="color: {{ $customer->balance < 0 ? '#dc3545' : '#1d9e75' }}; font-weight: 600;">₹{{ number_format($customer->balance, 2) }}</span></p>
                </div>

                <div style="background: #f8f9fa; border-radius: 8px; padding: 16px;">
                    <h4 style="margin: 0 0 12px; font-size: 13px; color: #666; text-transform: uppercase; letter-spacing: 0.5px;">👤 SPOC (Recipient)</h4>
                    <p style="margin: 4px 0; font-weight: 600;">{{ $order->spoc_name ?? $spoc->name }} @if($spoc->is_primary) <span style="font-size: 11px; background: #1d9e75; color: white; padding: 2px 6px; border-radius: 4px;">Primary</span> @endif</p>
                    <p style="margin: 4px 0; font-size: 13px; color: #666;">{{ $order->spoc_email ?? $spoc->email }}</p>
                    <p style="margin: 4px 0; font-size: 13px; color: #666;">{{ $order->spoc_phone ?: ($spoc->phone ?: '—') }}</p>
                </div>
            </div>

            <div style="background: #e8f7f2; border: 1px solid #1d9e75; border-radius: 8px; padding: 20px; margin-bottom: 24px;">
                <h4 style="margin: 0 0 12px; color: #1d9e75; font-size: 15px;">📝 Next Steps</h4>
                <ol style="margin: 0; padding-left: 20px; color: #444; font-size: 14px;">
                    <li style="margin-bottom: 8px;">Share the OTP <strong>{{ $otp }}</strong> with the authorized person</li>
                    <li style="margin-bottom: 8px;">They will enter it on the Avirqo portal to confirm the order</li>
                    <li>Once verified, vouchers will be sent as an Excel attachment to <strong>{{ $spoc->email }}</strong></li>
                </ol>
            </div>

            <div style="background: #fff3cd; border-left: 4px solid #ffc107; padding: 16px; border-radius: 4px; margin-bottom: 24px;">
                <p style="margin: 0; font-size: 13px; color: #856403;">
                    <strong>⚠ Security Notice:</strong> This OTP is valid for 10 minutes only. Do not share it with unauthorized personnel.
                    If you did not initiate this order, please contact support immediately.
                </p>
            </div>
        </div>

        <div style="background: #f8f9fa; padding: 20px; text-align: center; border-top: 1px solid #eee;">
            <p style="margin: 0 0 8px; font-size: 12px; color: #888;">
                Order: <strong>{{ $order->order_number }}</strong> | Initiated: {{ $order->created_at->format('d M Y, H:i') }}
            </p>
            <p style="margin: 0; font-size: 11px; color: #aaa;">
                This is an automated message from Avirqo Send Voucher System. Please do not reply to this email.
            </p>
        </div>
    </div>
</body>
</html>

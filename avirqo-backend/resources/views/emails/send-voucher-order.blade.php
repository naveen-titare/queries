<!DOCTYPE html>
<html>
<body style="font-family: 'DM Sans', -apple-system, Arial, sans-serif; color: #0D0D0C; max-width: 520px; margin: 0 auto; padding: 24px;">
  <div style="margin-bottom: 24px;">
    <span style="font-weight: 700; font-size: 20px; letter-spacing: -0.04em;">
      avirq<span style="color: #1D9E75;">o</span>
    </span>
  </div>

  <h2 style="font-size: 22px; font-weight: 600; margin: 0 0 8px;">Your vouchers are here! 🎁</h2>
  <p style="color: #6B6A67; margin: 0 0 20px; line-height: 1.6;">
    Hi {{ $spoc->name }}, please find your gift vouchers attached to this email
    as an Excel file. Each row contains the voucher code, PIN (if applicable), and expiry date.
  </p>

  <div style="background: #F7FAF9; border: 1px solid #E4EDE9; border-radius: 12px; padding: 16px 20px; margin-bottom: 20px;">
    <div style="font-size: 12px; text-transform: uppercase; letter-spacing: 0.08em; color: #6B6A67; margin-bottom: 8px;">Order Summary</div>
    <table style="width: 100%; border-collapse: collapse; font-size: 14px;">
      <tr>
        <td style="padding: 4px 0; color: #6B6A67;">Order Number</td>
        <td style="padding: 4px 0; font-weight: 600; text-align: right;">{{ $orderNumber }}</td>
      </tr>
      <tr>
        <td style="padding: 4px 0; color: #6B6A67;">Company</td>
        <td style="padding: 4px 0; font-weight: 600; text-align: right;">{{ $customer->company_name }}</td>
      </tr>
      @foreach($order->items as $item)
      <tr>
        <td style="padding: 4px 0; color: #6B6A67;">{{ $item->product->name }} × {{ $item->quantity }} ({{ $item->currency_code }} {{ $item->denomination }})</td>
        <td style="padding: 4px 0; font-weight: 600; text-align: right;">₹{{ number_format($item->total_value, 2) }}</td>
      </tr>
      @endforeach
      <tr style="border-top: 1px solid #E4EDE9;">
        <td style="padding: 8px 0 4px; font-weight: 700;">Total</td>
        <td style="padding: 8px 0 4px; font-weight: 700; text-align: right; color: #085041;">₹{{ number_format($order->total_amount, 2) }}</td>
      </tr>
      <tr>
        <td style="padding: 4px 0; color: #6B6A67;">Balance After</td>
        <td style="padding: 4px 0; font-weight: 600; text-align: right;">₹{{ number_format($order->customer_balance_after, 2) }}</td>
      </tr>
    </table>
  </div>

  <div style="background: #FFFBEB; border: 1px solid #FDE68A; border-radius: 8px; padding: 12px 14px; margin-bottom: 20px;">
    <p style="color: #92400E; font-size: 12px; line-height: 1.5; margin: 0;">
      🔒 <strong>Security Notice:</strong> Voucher codes are sensitive — do not forward this email. Excel file is encrypted in database and decrypted only at send time.
    </p>
  </div>

  <p style="color: #6B6A67; font-size: 13px; line-height: 1.6;">
    Please keep this email and the attached file secure. If you have any issues redeeming, contact your Avirqo account manager with order number <strong>{{ $orderNumber }}</strong>.
  </p>

  <p style="color: #B4B2A9; font-size: 12px; margin-top: 24px; line-height: 1.5;">
    This email was sent by Avirqo on behalf of {{ $customer->company_name }} to {{ $spoc->email }}.<br>
    Order ID: {{ $order->id }} | Sent at: {{ now()->format('d M Y, h:i A') }}
  </p>
</body>
</html>

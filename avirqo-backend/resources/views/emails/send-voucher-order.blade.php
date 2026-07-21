<!DOCTYPE html>
<html>
<body style="font-family: 'DM Sans', -apple-system, Arial, sans-serif; color: #0D0D0C; max-width: 700px; margin: 0 auto; padding: 24px;">
  <div style="margin-bottom: 24px;">
    <span style="font-weight: 700; font-size: 20px; letter-spacing: -0.04em;">
      avirq<span style="color: #1D9E75;">o</span>
    </span>
  </div>

  <h2 style="font-size: 22px; font-weight: 600; margin: 0 0 8px;">Your vouchers are here! 🎁</h2>
  <p style="color: #6B6A67; margin: 0 0 20px; line-height: 1.6;">
    Hi {{ $spoc->name }}, please find your gift vouchers attached to this email as an Excel file.
    Each row contains the voucher code, PIN (if applicable), and expiry date.
  </p>

  <div style="background: #F7FAF9; border: 1px solid #E4EDE9; border-radius: 12px; padding: 18px 20px; margin-bottom: 20px;">
    <div style="font-size: 12px; text-transform: uppercase; letter-spacing: 0.08em; color: #6B6A67; margin-bottom: 10px;">Order Summary</div>
    @php
      $productSubtotalGross = collect($order->items)->sum(fn ($item) => (float) ($item->gross_total ?? ($item->denomination * $item->quantity)));
      $productDiscountTotal = $order->pricing_mode === 'product'
        ? collect($order->items)->sum(fn ($item) => (float) ($item->discount_amount ?? 0))
        : 0;
      $productSubtotalNet = $order->pricing_mode === 'invoice'
        ? $productSubtotalGross
        : $productSubtotalGross - $productDiscountTotal;
      $customerVisibleOrderTotal = $order->pricing_mode === 'invoice'
        ? $productSubtotalGross - (float) $order->invoice_discount_amount
        : $productSubtotalNet;
    @endphp
    <table style="width: 100%; border-collapse: collapse; font-size: 14px;">
      <tr>
        <td style="padding: 4px 0; color: #6B6A67;">Order Number</td>
        <td style="padding: 4px 0; font-weight: 600; text-align: right;">{{ $orderNumber }}</td>
      </tr>
      <tr>
        <td style="padding: 4px 0; color: #6B6A67;">Company</td>
        <td style="padding: 4px 0; font-weight: 600; text-align: right;">{{ $customer->company_name }}</td>
      </tr>
    </table>

    <div style="margin-top: 16px;">
      <table style="width: 100%; border-collapse: collapse; font-size: 13px;">
        <thead>
          <tr style="background: #f1f5f4;">
            <th style="padding: 10px 12px; text-align: left; font-size: 11px; text-transform: uppercase; letter-spacing: 0.06em; color: #6B6A67;">Brand</th>
            <th style="padding: 10px 12px; text-align: center; font-size: 11px; text-transform: uppercase; letter-spacing: 0.06em; color: #6B6A67;">Denomination</th>
            <th style="padding: 10px 12px; text-align: center; font-size: 11px; text-transform: uppercase; letter-spacing: 0.06em; color: #6B6A67;">Qty</th>
            <th style="padding: 10px 12px; text-align: right; font-size: 11px; text-transform: uppercase; letter-spacing: 0.06em; color: #6B6A67;">Gross Total</th>
            <th style="padding: 10px 12px; text-align: right; font-size: 11px; text-transform: uppercase; letter-spacing: 0.06em; color: #6B6A67;">Discount</th>
            <th style="padding: 10px 12px; text-align: right; font-size: 11px; text-transform: uppercase; letter-spacing: 0.06em; color: #6B6A67;">Net Total</th>
          </tr>
        </thead>
        <tbody>
          @foreach($order->items as $item)
          @php
            $grossTotal = (float) ($item->gross_total ?? ($item->denomination * $item->quantity));
            $discountPercentage = (float) ($item->discount_percentage ?? 0);
            $discountAmount = $order->pricing_mode === 'product'
              ? (float) ($item->discount_amount ?? 0)
              : 0;
            $netTotal = $order->pricing_mode === 'invoice'
              ? $grossTotal
              : $grossTotal - $discountAmount;
          @endphp
          <tr style="border-top: 1px solid #E4EDE9;">
            <td style="padding: 14px 12px;">
              <div style="font-weight: 700; color: #111;">{{ $item->product->brand ?? $item->product->name }}</div>
              <div style="font-size: 12px; color: #6B6A67;">{{ $item->product->name }}</div>
            </td>
            <td style="padding: 14px 12px; text-align: center;">{{ $item->currency_code }} {{ number_format($item->denomination, 2) }}</td>
            <td style="padding: 14px 12px; text-align: center;">{{ $item->quantity }}</td>
            <td style="padding: 14px 12px; text-align: right; font-weight: 700;">₹{{ number_format($grossTotal, 2) }}</td>
            <td style="padding: 14px 12px; text-align: right; font-weight: 700; color: {{ $discountPercentage < 0 ? '#b45309' : '#16a34a' }};">
              @if($order->pricing_mode === 'product' && $discountPercentage != 0.0)
                @if($discountPercentage > 0)
                  −{{ number_format($discountPercentage, 2) }}% (−₹{{ number_format(abs($discountAmount), 2) }})
                @else
                  +{{ number_format(abs($discountPercentage), 2) }}% (+₹{{ number_format(abs($discountAmount), 2) }})
                @endif
              @else
                —
              @endif
            </td>
            <td style="padding: 14px 12px; text-align: right; font-weight: 700; color: #085041;">₹{{ number_format($netTotal, 2) }}</td>
          </tr>
          @endforeach
        </tbody>
        <tfoot>
          <tr style="border-top: 1px solid #d9e7e1;">
            <td colspan="3" style="padding: 12px 12px; text-align: right; font-weight: 700;">Products subtotal</td>
            <td style="padding: 12px 12px; text-align: right; font-weight: 700;">₹{{ number_format($productSubtotalGross, 2) }}</td>
            <td style="padding: 12px 12px; text-align: right; font-weight: 700; color: {{ $productDiscountTotal < 0 ? '#b45309' : '#16a34a' }};">
              {{ $productDiscountTotal >= 0 ? '−' : '+' }}₹{{ number_format(abs($productDiscountTotal), 2) }}
            </td>
            <td style="padding: 12px 12px; text-align: right; font-weight: 700; color: #085041;">₹{{ number_format($productSubtotalNet, 2) }}</td>
          </tr>
          @if($order->pricing_mode === 'invoice' && (float) $order->invoice_discount_percentage !== 0.0)
          <tr style="border-top: 1px solid #d9e7e1;">
            <td colspan="4" style="padding: 12px 12px; text-align: right; font-weight: 700;">{{ $order->invoice_discount_percentage >= 0 ? 'Discount' : 'Service Charge' }} ({{ $order->invoice_discount_percentage }}%)</td>
            <td style="padding: 12px 12px; text-align: right; font-weight: 700; color: {{ $order->invoice_discount_percentage < 0 ? '#b45309' : '#16a34a' }};">{{ $order->invoice_discount_amount >= 0 ? '−' : '+' }}₹{{ number_format(abs($order->invoice_discount_amount), 2) }}</td>
            <td style="padding: 12px 12px; text-align: right; font-weight: 700; color: #085041;">₹{{ number_format($customerVisibleOrderTotal, 2) }}</td>
          </tr>
          @endif
        </tfoot>
      </table>
    </div>
  </div>

  <div style="background: #FFFBEB; border: 1px solid #FDE68A; border-radius: 8px; padding: 12px 14px; margin-bottom: 20px;">
    <p style="color: #92400E; font-size: 12px; line-height: 1.5; margin: 0;">
      🔒 <strong>Security Notice:</strong> Voucher codes are sensitive — do not forward this email. The Excel attachment is the only delivery format.
    </p>
  </div>

  <p style="color: #6B6A67; font-size: 13px; line-height: 1.6;">
    Please keep this email and the attached file secure. If you have any issues redeeming, contact your Avirqo account manager with order number <strong>{{ $orderNumber }}</strong>.
  </p>

  <p style="color: #B4B2A9; font-size: 12px; margin-top: 24px; line-height: 1.5;">
    This email was sent by Avirqo on behalf of {{ $customer->company_name }} to {{ $spocEmail ?? $spoc->email }}.<br>
    Order ID: {{ $order->id }} | Sent at: {{ now()->format('d M Y, h:i A') }}
  </p>
</body>
</html>

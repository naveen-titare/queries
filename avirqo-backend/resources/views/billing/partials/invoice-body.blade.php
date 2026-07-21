<table class="grid">
  <tr>
    <td style="width:50%;">
      <div class="brand">avirq<span>o</span></div>
      <div class="strong">Avirqo</div>
      <div class="muted">India</div>
      <div class="muted">GSTIN: — | PAN: —</div>
    </td>
    <td style="width:50%; padding:0;">
      <table class="grid">
        <tr><td>Document No.</td><td class="strong">{{ $number }}</td></tr>
        <tr><td>Dated</td><td class="strong">{{ $date ?: now()->format('d M Y') }}</td></tr>
        <tr><td>Payment Terms</td><td>Advance / Paid PI</td></tr>
        @if($document->proformaInvoice ?? false)
          <tr><td>Against PI</td><td>{{ $document->proformaInvoice->pi_number }}</td></tr>
        @endif
      </table>
    </td>
  </tr>
  <tr>
    <td colspan="2">
      <div>Buyer: <span class="strong">{{ $document->customer->company_name ?? '—' }}</span></div>
      <div>{{ $document->customer->location ?? '—' }}</div>
      <div>GSTIN/UIN: {{ $document->customer->gst_number ?: '—' }}</div>
    </td>
  </tr>
</table>

<table class="items">
  <thead>
    <tr>
      <th>Sl No.</th>
      <th>Description of Goods</th>
      <th>HSN/SAC</th>
      <th>Quantity</th>
      <th>Rate</th>
      <th>Adjustment</th>
      <th>Taxable Value</th>
      <th>IGST Rate</th>
      <th>IGST Amount</th>
      <th>Amount</th>
    </tr>
  </thead>
  <tbody>
    @foreach($document->items as $item)
      <tr>
        <td class="center">{{ $loop->iteration }}</td>
        <td>{{ $item->brand }} - {{ $item->product_name }} (INR {{ number_format((float) $item->denomination, 2) }})</td>
        <td class="center">{{ $item->hsn_sac ?: '—' }}</td>
        <td class="center">{{ $item->quantity }}</td>
        <td class="right">₹{{ number_format((float) $item->unit_price, 2) }}</td>
        <td class="right">
          @if((float) $item->discount_percentage > 0)
            Discount {{ number_format((float) $item->discount_percentage, 2) }}%
          @elseif((float) $item->discount_percentage < 0)
            Service Charge {{ number_format(abs((float) $item->discount_percentage), 2) }}%
          @else
            —
          @endif
        </td>
        <td class="right">₹{{ number_format((float) $item->taxable_value, 2) }}</td>
        <td class="right">{{ number_format((float) $item->gst_rate, 2) }}%</td>
        <td class="right">₹{{ number_format((float) $item->igst_amount, 2) }}</td>
        <td class="right">₹{{ number_format((float) $item->line_total, 2) }}</td>
      </tr>
    @endforeach
  </tbody>
  <tfoot>
    <tr>
      <td colspan="6" class="right strong">Products Subtotal</td>
      <td class="right strong">₹{{ number_format((float) $document->subtotal, 2) }}</td>
      <td></td>
      <td></td>
      <td class="right strong">₹{{ number_format((float) $document->subtotal, 2) }}</td>
    </tr>
    @if((float) $document->discount_amount !== 0.0)
      <tr>
        <td colspan="6" class="right strong">
          {{ (float) $document->discount_amount > 0 ? 'Discount' : 'Service Charge' }}
          @if(($document->discount_type ?? 'campaign') === 'invoice')
            ({{ number_format(abs((float) ($document->invoice_discount_percentage ?? 0)), 2) }}%)
          @endif
        </td>
        <td class="right strong">{{ (float) $document->discount_amount > 0 ? '−' : '+' }}₹{{ number_format(abs((float) $document->discount_amount), 2) }}</td>
        <td></td>
        <td></td>
        <td class="right strong">₹{{ number_format((float) $document->taxable_value, 2) }}</td>
      </tr>
    @endif
    <tr>
      <td colspan="6" class="right strong">Total</td>
      <td class="right strong">₹{{ number_format((float) $document->taxable_value, 2) }}</td>
      <td></td>
      <td class="right strong">₹{{ number_format((float) $document->igst_amount, 2) }}</td>
      <td class="right strong">₹{{ number_format((float) $document->total_amount, 2) }}</td>
    </tr>
  </tfoot>
</table>

<table class="invoice-footer-grid">
  <tr>
    <td class="amount-words">
      Amount Chargeable (in words): <span class="strong">{{ $amountInWords }}</span>
    </td>
    <td class="bank-signatory-box">
      <div class="foe">F. &amp; O.E</div>
      <div class="bank-title">Company's Bank Details</div>
      <table class="bank-details">
        <tr>
          <td>Account Holder's Name</td>
          <td>:</td>
          <td class="strong">Avirqo Fintech LLP</td>
        </tr>
        <tr>
          <td>Bank Name</td>
          <td>:</td>
          <td class="strong">—</td>
        </tr>
        <tr>
          <td>A/c No.</td>
          <td>:</td>
          <td class="strong">—</td>
        </tr>
        <tr>
          <td>IFSC / Swift Code</td>
          <td>:</td>
          <td class="strong">—</td>
        </tr>
        <tr>
          <td>Branch / Routing Code</td>
          <td>:</td>
          <td class="strong">—</td>
        </tr>
      </table>
      <div class="for-company">For Avirqo Fintech LLP</div>
      <div class="signatory-space"></div>
      <div class="signatory-label">Authorised signatory</div>
    </td>
  </tr>
</table>

<div class="declaration">
  <div class="strong">Declaration</div>
  <ol>
    <li>Vouchers/ Points are exempted under GST Act, 2017. Hence no HSN/ SAC/ GST is applicable.</li>
    <li>TDS is not applicable on Vouchers/ Points / Products. Hence don’t deduct TDS on these.</li>
    <li>Only &quot;Online Payment&quot; is accepted. Please avoid Cheque &amp; DD.</li>
    <li>We have stopped providing &quot;Physical Invoice&quot; as a part of digitization initiative.</li>
    <li>Supply meant for export/supply to SEZ unit or SEZ developer for authorised operations under bond or letter of undertaking without payment of integrated tax.</li>
  </ol>
</div>

<p class="note">This is a Computer Generated Invoice. Think before you print and save a tree.</p>
<div class="footer">Avirqo Billing System</div>

<!DOCTYPE html>
<html>
<head><meta charset="utf-8">@include('billing.partials.document-style')</head>
<body>
  <div class="title">{{ strtoupper($document->type) }} NOTE</div>
  <table class="grid">
    <tr>
      <td><div class="brand">avirq<span>o</span></div><div>Avirqo</div></td>
      <td>
        <div>Note No.: <strong>{{ $document->note_number ?: $document->draft_number }}</strong></div>
        <div>Date: {{ optional($document->finalized_at)->format('d M Y') ?: now()->format('d M Y') }}</div>
      </td>
    </tr>
    <tr><td colspan="2">Customer: <strong>{{ $document->customer->company_name ?? '—' }}</strong></td></tr>
  </table>
  <table class="items">
    <tr><th>Reason</th><th>Amount</th><th>Remaining</th></tr>
    <tr>
      <td>{{ $document->reason ?: '—' }}</td>
      <td class="right">₹{{ number_format((float) $document->amount, 2) }}</td>
      <td class="right">₹{{ number_format((float) $document->remaining_amount, 2) }}</td>
    </tr>
  </table>
  <p>Amount in words: <strong>{{ $amountInWords }}</strong></p>
  <p class="note">This is a system generated document and does not require any signature.</p>
</body>
</html>

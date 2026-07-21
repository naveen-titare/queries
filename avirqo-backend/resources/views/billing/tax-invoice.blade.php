<!DOCTYPE html>
<html>
<head><meta charset="utf-8">@include('billing.partials.document-style')</head>
<body>
  <div class="title">{{ $document->status === 'draft' ? 'DRAFT TAX INVOICE' : 'TAX INVOICE' }}</div>
  @include('billing.partials.invoice-body', ['document' => $document, 'number' => $document->invoice_number ?: $document->draft_number, 'date' => optional($document->invoice_date)->format('d M Y'), 'amountInWords' => $amountInWords])
</body>
</html>

<!DOCTYPE html>
<html>
<head><meta charset="utf-8">@include('billing.partials.document-style')</head>
<body>
  <div class="title">PROFORMA INVOICE</div>
  @include('billing.partials.invoice-body', ['document' => $document, 'number' => $document->pi_number ?: $document->draft_number, 'date' => optional($document->issue_date)->format('d M Y'), 'amountInWords' => $amountInWords])
</body>
</html>

<!DOCTYPE html>
<html>
<body style="font-family: Arial, sans-serif; color:#111827;">
  <h2 style="margin-bottom:8px;">{{ $heading ?? $label }}</h2>
  <p style="color:#4b5563;">Please find the attached internal document.</p>
  @if($messageText)
    <div style="background:#f7faf9;border:1px solid #e4ede9;border-radius:8px;padding:12px;margin:16px 0;">
      {!! nl2br(e($messageText)) !!}
    </div>
  @endif
  <p style="font-size:12px;color:#6b7280;">This is an internal Avirqo document email.</p>
</body>
</html>

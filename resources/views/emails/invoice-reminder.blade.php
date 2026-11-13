<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>{{ $invoice->invoice_number }}</title></head>
<body style="margin:0;background:#f4f8f7;color:#253936;font-family:Arial,sans-serif;line-height:1.6">
<main style="max-width:620px;margin:32px auto;padding:30px;background:#fff;border:1px solid #e2ece8;border-radius:14px">
    <p style="margin:0 0 8px;color:#24766d;font-weight:700;letter-spacing:.08em;text-transform:uppercase">{{ $invoice->organization->name }}</p>
    <h1 style="margin:0 0 18px;font-size:24px">Invoice reminder</h1>
    <p style="white-space:pre-line">{{ $messageBody }}</p>
    <div style="margin-top:24px;padding:18px;border-radius:10px;background:#f3f8f6">
        <strong>{{ $invoice->invoice_number }}</strong><br>
        Outstanding: {{ $invoice->currency }} {{ number_format(max(0, (float) $invoice->total - (float) $invoice->amount_paid - (float) $invoice->credit_total), 2) }}<br>
        Due date: {{ $invoice->due_on?->format('j M Y') ?? '—' }}
    </div>
</main>
</body>
</html>

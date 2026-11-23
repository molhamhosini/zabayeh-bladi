<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>ذبائح بلادي</title>
    <style>
        body { margin: 0; font-family: Tajawal, system-ui, sans-serif; background: #F7F1E6; color: #2B1A1C; display: grid; place-items: center; min-height: 100vh; text-align: center; }
        .card { background: #fff; border-radius: 20px; padding: 32px 24px; max-width: 340px; margin: 16px; box-shadow: 0 8px 24px rgba(0,0,0,.06); }
        h1 { font-size: 22px; margin: 16px 0 8px; color: {{ $paid ? '#2E7D4F' : '#8C1C2B' }}; }
        p { color: #7A6A6C; line-height: 1.7; margin: 0; }
    </style>
</head>
<body data-payment="{{ $paid ? 'paid' : 'not_paid' }}">
<div class="card">
    <img src="{{ asset('brand/logo-mark.svg') }}" width="72" height="72" alt="">
    @if ($paid)
        <h1>تم الدفع بنجاح</h1>
        <p>طلبك رقم {{ $order->number }} مؤكد الدفع. يمكنك العودة إلى التطبيق.</p>
    @elseif ($status === 'cancel')
        <h1>تم إلغاء الدفع</h1>
        <p>لم يُخصم أي مبلغ. يمكنك العودة واختيار طريقة دفع أخرى.</p>
    @else
        <h1>لم يكتمل الدفع</h1>
        <p>لم تتم الموافقة على الدفع. يمكنك العودة إلى التطبيق والمحاولة بطريقة أخرى.</p>
    @endif
</div>
</body>
</html>

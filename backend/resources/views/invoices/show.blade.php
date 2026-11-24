@php
    $money = fn ($v) => number_format((float) $v, 2);
    $methods = ['cod' => 'الدفع عند الاستلام / Cash on delivery', 'card' => 'بطاقة / Card', 'tabby' => 'تابي / Tabby', 'tamara' => 'تمارا / Tamara'];
    $payment = ['unpaid' => 'غير مدفوع / Unpaid', 'paid' => 'مدفوع / Paid', 'refunded' => 'مسترد / Refunded', 'failed' => 'فشل الدفع / Failed'];
    $purposes = ['normal' => null, 'udhiya' => 'أضحية / Udhiya', 'aqiqah' => 'عقيقة / Aqiqah', 'sadaqah' => 'صدقة / Sadaqah'];
    $issued = $order->created_at->timezone('Asia/Dubai');
    $area = $order->address?->area;
    $vendor = $order->branch?->vendor;
    $lineVat = fn ($line) => round((float) $line * $rate / 100, 2);
@endphp
<!doctype html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>فاتورة ضريبية {{ $order->number }}</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;800&display=swap" rel="stylesheet">
<style>
  :root { --maroon:#8C1C2B; --maroon-dark:#4A0E17; --gold:#C8961E; --gold-soft:#F3E7CC; --ink:#231815; --muted:#7A6A66; --line:#E8DED6; --cream:#FBF7F1; }
  * { box-sizing: border-box; }
  body { margin:0; background:#ECE6DF; color:var(--ink); font-family:'Tajawal', 'Segoe UI', Tahoma, sans-serif; font-size:13px; line-height:1.5; }
  .toolbar { position:sticky; top:0; z-index:5; display:flex; gap:8px; justify-content:center; padding:12px; background:var(--maroon-dark); }
  .toolbar a, .toolbar button { font:inherit; font-weight:700; border:0; border-radius:10px; padding:9px 16px; cursor:pointer; text-decoration:none; color:var(--maroon-dark); background:#fff; }
  .toolbar .primary { background:var(--gold); color:#fff; }
  .en { direction:ltr; unicode-bidi:isolate; color:var(--muted); font-size:.86em; }
  .num { font-variant-numeric: tabular-nums; direction:ltr; unicode-bidi:isolate; }

  /* A4 */
  .sheet { width:210mm; min-height:297mm; margin:18px auto; background:#fff; padding:14mm 14mm 10mm; position:relative; overflow:hidden; box-shadow:0 6px 30px rgba(0,0,0,.12); }
  .sheet::before { content:""; position:absolute; inset:0 0 auto 0; height:7mm; background:linear-gradient(90deg, var(--maroon-dark), var(--maroon) 60%, var(--gold)); }
  .watermark { position:absolute; inset:0; display:flex; align-items:center; justify-content:center; pointer-events:none; opacity:.03; }
  .watermark img { width:120mm; }
  header.top { display:flex; justify-content:space-between; align-items:flex-start; gap:12mm; margin-top:4mm; }
  .brand { display:flex; gap:12px; align-items:center; }
  .brand img { width:58px; height:58px; }
  .brand h1 { margin:0; font-size:22px; color:var(--maroon); font-weight:800; }
  .brand .en { font-size:12px; letter-spacing:.5px; }
  .seller { font-size:12px; color:var(--muted); margin-top:6px; }
  .seller b { color:var(--ink); }
  .title { text-align:left; }
  .title .ar { font-size:26px; font-weight:800; color:var(--maroon-dark); }
  .title .en { display:block; font-size:13px; letter-spacing:3px; text-transform:uppercase; color:var(--gold); font-weight:700; }
  .trn { display:inline-block; margin-top:6px; background:var(--gold-soft); color:var(--maroon-dark); border-radius:8px; padding:4px 10px; font-weight:700; font-size:12px; }
  .meta { display:grid; grid-template-columns:repeat(4, 1fr); gap:0; margin:8mm 0 6mm; border:1px solid var(--line); border-radius:12px; overflow:hidden; }
  .meta div { padding:9px 12px; border-inline-start:1px solid var(--line); background:var(--cream); }
  .meta div:first-child { border-inline-start:0; }
  .meta small { display:block; color:var(--muted); font-size:11px; }
  .meta strong { font-size:14px; }
  .parties { display:grid; grid-template-columns:1fr 1fr; gap:6mm; margin-bottom:6mm; }
  .card { border:1px solid var(--line); border-radius:12px; padding:10px 14px; }
  .card h3 { margin:0 0 6px; font-size:12px; color:var(--maroon); text-transform:uppercase; letter-spacing:.3px; }
  .card p { margin:2px 0; }
  table.items { width:100%; border-collapse:separate; border-spacing:0; }
  table.items th { background:var(--maroon-dark); color:#fff; font-weight:700; font-size:11.5px; padding:8px 8px; text-align:right; }
  table.items th .en { color:#E9D3A3; display:block; font-size:10px; }
  table.items th:first-child { border-start-start-radius:10px; }
  table.items th:last-child { border-start-end-radius:10px; }
  table.items td { padding:9px 8px; border-bottom:1px solid var(--line); vertical-align:top; }
  table.items tr:nth-child(even) td { background:#FDFBF8; }
  table.items td.n, table.items th.n { text-align:left; white-space:nowrap; }
  .item-name { font-weight:700; }
  .item-opts { color:var(--muted); font-size:11.5px; }
  .bottom { display:grid; grid-template-columns:1fr 96mm; gap:8mm; margin-top:6mm; align-items:start; }
  .totals { border:1px solid var(--line); border-radius:12px; overflow:hidden; }
  .totals div { display:flex; justify-content:space-between; align-items:center; gap:10px; white-space:nowrap; padding:7px 12px; border-bottom:1px solid var(--line); }
  .totals div:last-child { border-bottom:0; }
  .totals .grand { background:var(--maroon); color:#fff; font-size:16px; font-weight:800; padding:11px 12px; }
  .totals .grand .en { color:#F3E7CC; }
  .pay p { margin:3px 0; }
  .badge { display:inline-block; border-radius:999px; padding:2px 10px; font-weight:700; font-size:11.5px; }
  .badge.paid { background:#E3F4E8; color:#1F7A3E; }
  .badge.unpaid { background:#FCEBD9; color:#9A5A12; }
  footer { position:absolute; left:14mm; right:14mm; bottom:10mm; display:flex; justify-content:space-between; align-items:flex-end; border-top:2px solid var(--gold); padding-top:8px; font-size:11.5px; color:var(--muted); }
  footer .thanks { color:var(--maroon); font-weight:800; font-size:14px; }

  /* 80mm thermal */
  .receipt { width:80mm; margin:18px auto; background:#fff; padding:5mm 4mm; font-size:11.5px; color:#000; box-shadow:0 6px 30px rgba(0,0,0,.12); }
  .receipt .c { text-align:center; }
  .receipt img { width:44px; height:44px; }
  .receipt h2 { margin:4px 0 0; font-size:16px; }
  .receipt .t { margin:6px 0; padding:4px 0; border-top:1px dashed #000; border-bottom:1px dashed #000; text-align:center; font-weight:800; font-size:14px; }
  .receipt .row { display:flex; justify-content:space-between; gap:6px; }
  .receipt .sep { border-top:1px dashed #000; margin:6px 0; }
  .receipt .it { margin:5px 0; }
  .receipt .it .o { font-size:10px; color:#333; }
  .receipt .grand { font-size:15px; font-weight:800; }
  .receipt .en { color:#333; }

  @media print {
    @page { size: {{ $thermal ? '80mm auto' : 'A4' }}; margin:0; }
    body { background:#fff; }
    .toolbar { display:none; }
    .sheet, .receipt { margin:0; box-shadow:none; }
    * { -webkit-print-color-adjust:exact; print-color-adjust:exact; }
  }
</style>
</head>
<body>
<div class="toolbar">
  <button class="primary" onclick="window.print()">🖨 طباعة</button>
  @if ($thermal)
    <a href="{{ route('orders.invoice', $order) }}">مقاس A4</a>
  @else
    <a href="{{ route('orders.invoice', [$order, 'size' => '80mm']) }}">إيصال حراري 80مم</a>
  @endif
</div>

@if (! $thermal)
<div class="sheet">
  <div class="watermark"><img src="{{ asset('brand/logo-mark.svg') }}" alt=""></div>

  <header class="top">
    <div>
      <div class="brand">
        <img src="{{ asset('brand/logo-mark.svg') }}" alt="">
        <div>
          <h1>{{ $company['name_ar'] }}</h1>
          <div class="en">{{ $company['name_en'] }}</div>
        </div>
      </div>
      <div class="seller">
        @if ($company['legal_name_ar'])<div><b>{{ $company['legal_name_ar'] }}</b>@if ($company['legal_name_en']) <span class="en">· {{ $company['legal_name_en'] }}</span>@endif</div>@endif
        @if ($company['address_ar'])<div>{{ $company['address_ar'] }} @if ($company['address_en'])<span class="en"> — {{ $company['address_en'] }}</span>@endif</div>@endif
        <div>
          @if ($company['phone'])<span class="num">{{ $company['phone'] }}</span>@endif
          @if ($company['email']) · <span class="num">{{ $company['email'] }}</span>@endif
          @if ($company['website']) · <span class="num">{{ $company['website'] }}</span>@endif
        </div>
      </div>
    </div>
    <div class="title">
      <div class="ar">فاتورة ضريبية</div>
      <span class="en">Tax Invoice</span>
      @if ($company['trn'])
        <div class="trn">الرقم الضريبي · TRN <span class="num">{{ $company['trn'] }}</span></div>
      @endif
    </div>
  </header>

  <section class="meta">
    <div><small>رقم الفاتورة <span class="en">Invoice No.</span></small><strong class="num">INV-{{ $order->number }}</strong></div>
    <div><small>تاريخ الإصدار <span class="en">Issue date</span></small><strong class="num">{{ $issued->format('Y-m-d H:i') }}</strong></div>
    <div><small>تاريخ التوريد <span class="en">Supply date</span></small><strong class="num">{{ ($order->slot?->date ?? $issued)->format('Y-m-d') }}</strong></div>
    <div><small>رقم الطلب <span class="en">Order No.</span></small><strong class="num">{{ $order->number }}</strong></div>
  </section>

  <section class="parties">
    <div class="card">
      <h3>العميل · Bill to</h3>
      <p><b>{{ $order->user?->name ?: '—' }}</b></p>
      <p class="num">{{ $order->user?->phone }}</p>
      <p>{{ $area?->emirate?->name_ar }}{{ $area ? ' - '.$area->name_ar : '' }}</p>
      @if ($order->address?->details)<p style="color:var(--muted)">{{ $order->address->details }}</p>@endif
    </div>
    <div class="card">
      <h3>التوريد · Supply</h3>
      <p>الفرع: <b>{{ $order->branch?->name_ar }}</b>@if ($vendor && ! $vendor->is_own) <span style="color:var(--muted)">({{ $vendor->name_ar }})</span>@endif</p>
      @if ($order->slot)<p>موعد التوصيل: <span class="num">{{ $order->slot->date->format('Y-m-d') }}</span> · {{ $order->slot->label() }}</p>@endif
      @if ($purposes[$order->purpose] ?? null)<p>نوع الطلب: {{ $purposes[$order->purpose] }}@if ($order->beneficiary_name) — {{ $order->beneficiary_name }}@endif</p>@endif
      @if ($order->donate_to_charity)<p>يُوزّع للجمعيات الخيرية</p>@endif
    </div>
  </section>

  <table class="items">
    <thead>
      <tr>
        <th style="width:28px">#</th>
        <th>الوصف<span class="en">Description</span></th>
        <th class="n">الكمية<span class="en">Qty</span></th>
        <th class="n">سعر الوحدة<span class="en">Unit price</span></th>
        <th class="n">المبلغ الخاضع<span class="en">Taxable amt</span></th>
        <th class="n">الضريبة {{ $rate }}%<span class="en">VAT</span></th>
        <th class="n">الإجمالي<span class="en">Total (AED)</span></th>
      </tr>
    </thead>
    <tbody>
      @foreach ($order->items as $i => $item)
        <tr>
          <td>{{ $i + 1 }}</td>
          <td>
            <div class="item-name">{{ $item->name_ar }} @if ($item->variant_ar)<span style="font-weight:500">· {{ $item->variant_ar }}</span>@endif</div>
            @if (! empty($item->options))
              <div class="item-opts">
                @foreach (collect($item->options)->groupBy('group_ar') as $group => $opts)
                  {{ $group }}: {{ $opts->pluck('name_ar')->join('، ') }}@if (! $loop->last) · @endif
                @endforeach
              </div>
            @endif
          </td>
          <td class="n num">{{ $item->quantity }}</td>
          <td class="n num">{{ $money($item->unit_price) }}</td>
          <td class="n num">{{ $money($item->line_total) }}</td>
          <td class="n num">{{ $money($lineVat($item->line_total)) }}</td>
          <td class="n num"><b>{{ $money($item->line_total + $lineVat($item->line_total)) }}</b></td>
        </tr>
      @endforeach
      @if ((float) $order->delivery_fee > 0)
        <tr>
          <td>{{ $order->items->count() + 1 }}</td>
          <td><div class="item-name">رسوم التوصيل <span class="en">Delivery</span></div></td>
          <td class="n num">1</td>
          <td class="n num">{{ $money($order->delivery_fee) }}</td>
          <td class="n num">{{ $money($order->delivery_fee) }}</td>
          <td class="n num">{{ $money($lineVat($order->delivery_fee)) }}</td>
          <td class="n num"><b>{{ $money($order->delivery_fee + $lineVat($order->delivery_fee)) }}</b></td>
        </tr>
      @endif
    </tbody>
  </table>

  <section class="bottom">
    <div class="card pay">
      <h3>الدفع · Payment</h3>
      <p>{{ $methods[$order->payment_method] ?? $order->payment_method }}</p>
      <p><span class="badge {{ $order->payment_status === 'paid' ? 'paid' : 'unpaid' }}">{{ $payment[$order->payment_status] ?? $order->payment_status }}</span></p>
      @if ($order->payment_reference)<p class="num" style="color:var(--muted)">Ref: {{ $order->payment_reference }}</p>@endif
      @if ($order->notes)<p style="margin-top:8px"><b>ملاحظات:</b> {{ $order->notes }}</p>@endif
    </div>
    <div class="totals">
      <div><span>الخاضع للضريبة <span class="en">Taxable amount</span></span><span class="num">{{ $money($taxable) }}</span></div>
      <div><span>ضريبة القيمة المضافة {{ $rate }}% <span class="en">VAT</span></span><span class="num">{{ $money($order->vat) }}</span></div>
      <div class="grand"><span>الإجمالي المستحق <span class="en">Total AED</span></span><span class="num">AED {{ $money($order->total) }}</span></div>
    </div>
  </section>

  <footer>
    <div>
      <div class="thanks">{{ $company['footer_note'] }}</div>
      <div>جميع الأسعار بالدرهم الإماراتي · All amounts in AED</div>
    </div>
    <div class="num">INV-{{ $order->number }}</div>
  </footer>
</div>
@else
<div class="receipt">
  <div class="c">
    <img src="{{ asset('brand/logo-mark.svg') }}" alt="">
    <h2>{{ $company['name_ar'] }}</h2>
    <div class="en">{{ $company['name_en'] }}</div>
    @if ($company['legal_name_ar'])<div><b>{{ $company['legal_name_ar'] }}</b></div>@endif
    @if ($company['address_ar'])<div>{{ $company['address_ar'] }}</div>@endif
    @if ($company['phone'])<div class="num">{{ $company['phone'] }}</div>@endif
    @if ($company['trn'])<div><b>TRN: <span class="num">{{ $company['trn'] }}</span></b></div>@endif
  </div>
  <div class="t">فاتورة ضريبية · TAX INVOICE</div>
  <div class="row"><span>رقم الفاتورة</span><b class="num">INV-{{ $order->number }}</b></div>
  <div class="row"><span>التاريخ</span><span class="num">{{ $issued->format('Y-m-d H:i') }}</span></div>
  <div class="row"><span>العميل</span><span>{{ $order->user?->name }}</span></div>
  <div class="row"><span>الجوال</span><span class="num">{{ $order->user?->phone }}</span></div>
  <div class="row"><span>المنطقة</span><span>{{ $area?->emirate?->name_ar }}{{ $area ? ' - '.$area->name_ar : '' }}</span></div>
  <div class="sep"></div>
  @foreach ($order->items as $item)
    <div class="it">
      <div class="row"><b>{{ $item->quantity }}× {{ $item->name_ar }}</b><span class="num">{{ $money($item->line_total) }}</span></div>
      @if ($item->variant_ar)<div class="o">{{ $item->variant_ar }}</div>@endif
      @foreach (collect($item->options ?? []) as $o)<div class="o">{{ $o['group_ar'] }}: {{ $o['name_ar'] }}</div>@endforeach
    </div>
  @endforeach
  <div class="sep"></div>
  <div class="row"><span>المجموع</span><span class="num">{{ $money($order->subtotal) }}</span></div>
  <div class="row"><span>التوصيل</span><span class="num">{{ $money($order->delivery_fee) }}</span></div>
  <div class="row"><span>الخاضع للضريبة</span><span class="num">{{ $money($taxable) }}</span></div>
  <div class="row"><span>الضريبة {{ $rate }}%</span><span class="num">{{ $money($order->vat) }}</span></div>
  <div class="sep"></div>
  <div class="row grand"><span>الإجمالي</span><span class="num">{{ $money($order->total) }} AED</span></div>
  <div class="row"><span>الدفع</span><span>{{ $methods[$order->payment_method] ?? $order->payment_method }}</span></div>
  <div class="row"><span>الحالة</span><span>{{ $payment[$order->payment_status] ?? $order->payment_status }}</span></div>
  <div class="sep"></div>
  <div class="c">{{ $company['footer_note'] }}</div>
</div>
@endif

@if ($autoprint)
<script>window.addEventListener('load', () => setTimeout(() => window.print(), 400));</script>
@endif
</body>
</html>

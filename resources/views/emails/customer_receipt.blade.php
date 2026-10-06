<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Order Confirmed</title>
<style>
  body { margin:0; padding:0; background:#f4f4f5; font-family:'Segoe UI',Arial,sans-serif; }
  .wrapper { max-width:600px; margin:40px auto; background:#ffffff; border-radius:12px; overflow:hidden; box-shadow:0 4px 24px rgba(0,0,0,0.08); }
  .header { background:linear-gradient(135deg,#16a34a,#15803d); padding:40px 32px; text-align:center; }
  .header h1 { color:#fff; margin:0; font-size:28px; font-weight:700; }
  .header p { color:#bbf7d0; margin:8px 0 0; font-size:15px; }
  .check { font-size:56px; display:block; margin-bottom:12px; }
  .body { padding:32px; }
  .meta { background:#f0fdf4; border:1px solid #bbf7d0; border-radius:8px; padding:20px; margin-bottom:24px; }
  .meta p { margin:4px 0; font-size:14px; color:#374151; }
  .meta .label { font-weight:600; color:#16a34a; }
  .tracking { background:#16a34a; color:#fff; border-radius:8px; padding:16px 20px; margin-bottom:24px; text-align:center; }
  .tracking .code { font-size:24px; font-weight:800; letter-spacing:3px; }
  .tracking p { margin:4px 0 0; font-size:13px; opacity:0.85; }
  h2 { font-size:16px; font-weight:700; color:#111827; margin:0 0 12px; }
  .items-table { width:100%; border-collapse:collapse; margin-bottom:24px; }
  .items-table th { background:#f9fafb; text-align:left; padding:10px 12px; font-size:13px; color:#6b7280; font-weight:600; border-bottom:2px solid #e5e7eb; }
  .items-table td { padding:12px; font-size:14px; color:#374151; border-bottom:1px solid #f3f4f6; }
  .items-table tr:last-child td { border-bottom:none; }
  .totals { background:#f9fafb; border-radius:8px; padding:16px 20px; margin-bottom:24px; }
  .totals .row { display:flex; justify-content:space-between; font-size:14px; color:#374151; margin-bottom:6px; }
  .totals .total-row { display:flex; justify-content:space-between; font-size:17px; font-weight:700; color:#16a34a; border-top:2px solid #e5e7eb; padding-top:10px; margin-top:6px; }
  .address { background:#fafafa; border:1px solid #e5e7eb; border-radius:8px; padding:16px 20px; margin-bottom:24px; font-size:14px; color:#374151; }
  .address .label { font-weight:600; color:#374151; margin-bottom:4px; }
  .cta { text-align:center; margin-bottom:28px; }
  .cta a { background:#16a34a; color:#fff; padding:14px 32px; border-radius:8px; text-decoration:none; font-weight:600; font-size:15px; display:inline-block; }
  .footer { background:#f9fafb; padding:20px 32px; text-align:center; border-top:1px solid #e5e7eb; }
  .footer p { margin:0; font-size:13px; color:#9ca3af; }
  .footer strong { color:#374151; }
</style>
</head>
<body>
<div class="wrapper">
  <div class="header">
    <span class="check">✅</span>
    <h1>Payment Confirmed!</h1>
    <p>Thank you for your order from {{ $business->name }}</p>
  </div>

  <div class="body">
    <div class="tracking">
      <p>Your Order Tracking Code</p>
      <div class="code">{{ $order->tracking_code }}</div>
      <p>Save this code to track your order anytime</p>
    </div>

    <div class="meta">
      <p><span class="label">Customer:</span> {{ $order->customer_name ?? 'Valued Customer' }}</p>
      <p><span class="label">Phone:</span> {{ $order->customer_phone ?? '—' }}</p>
      <p><span class="label">Order Date:</span> {{ $order->created_at->format('d M Y, g:i A') }}</p>
      <p><span class="label">Payment Ref:</span> {{ $payment->reference }}</p>
      <p><span class="label">Status:</span> <strong style="color:#16a34a">CONFIRMED ✅</strong></p>
    </div>

    <h2>Items Ordered</h2>
    <table class="items-table">
      <thead>
        <tr>
          <th>Item</th>
          <th>Qty</th>
          <th>Price</th>
          <th>Total</th>
        </tr>
      </thead>
      <tbody>
        @foreach($order->items as $item)
        <tr>
          <td>
            {{ $item->item_name }}
            @if($item->size) <br><small style="color:#9ca3af">Size: {{ $item->size }}</small> @endif
            @if($item->color) <small style="color:#9ca3af"> | Color: {{ $item->color }}</small> @endif
          </td>
          <td>{{ $item->quantity }}</td>
          <td>{{ $order->currency }} {{ number_format($item->unit_price, 2) }}</td>
          <td>{{ $order->currency }} {{ number_format($item->total_price, 2) }}</td>
        </tr>
        @endforeach
      </tbody>
    </table>

    <div class="totals">
      @if($order->shipping_fee > 0)
      <div class="row"><span>Subtotal</span><span>{{ $order->currency }} {{ number_format($order->subtotal, 2) }}</span></div>
      <div class="row"><span>Delivery Fee</span><span>{{ $order->currency }} {{ number_format($order->shipping_fee, 2) }}</span></div>
      @endif
      <div class="total-row"><span>TOTAL PAID</span><span>{{ $order->currency }} {{ number_format($order->total_amount, 2) }}</span></div>
    </div>

    @if($order->shipping_address)
    <div class="address">
      <div class="label">📦 Delivery Address</div>
      {{ $order->shipping_address }}
    </div>
    @endif

    <div class="cta">
      <a href="https://wa.me/{{ preg_replace('/[^0-9]/', '', $business->phone ?? '') }}?text={{ urlencode('Hi! Tracking my order #'.$order->tracking_code) }}">
        💬 Track on WhatsApp
      </a>
    </div>
  </div>

  <div class="footer">
    <p>Questions? Contact <strong>{{ $business->name }}</strong></p>
    @if($business->phone)<p>📞 {{ $business->phone }}</p>@endif
    <p style="margin-top:12px; font-size:12px;">This is an automated receipt from {{ $business->name }} powered by Zeen.</p>
  </div>
</div>
</body>
</html>

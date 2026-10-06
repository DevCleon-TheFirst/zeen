<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>New Sale!</title>
<style>
  body { margin:0; padding:0; background:#f4f4f5; font-family:'Segoe UI',Arial,sans-serif; }
  .wrapper { max-width:600px; margin:40px auto; background:#ffffff; border-radius:12px; overflow:hidden; box-shadow:0 4px 24px rgba(0,0,0,0.08); }
  .header { background:linear-gradient(135deg,#1d4ed8,#1e40af); padding:36px 32px; text-align:center; }
  .header h1 { color:#fff; margin:0; font-size:26px; font-weight:700; }
  .header p { color:#bfdbfe; margin:8px 0 0; font-size:15px; }
  .icon { font-size:52px; display:block; margin-bottom:10px; }
  .body { padding:32px; }
  .alert-box { background:#eff6ff; border:1px solid #bfdbfe; border-radius:8px; padding:20px; margin-bottom:24px; }
  .alert-box h2 { margin:0 0 12px; font-size:17px; color:#1d4ed8; }
  .alert-box p { margin:4px 0; font-size:14px; color:#374151; }
  .alert-box .val { font-weight:600; color:#111827; }
  .revenue { background:#1d4ed8; color:#fff; border-radius:8px; padding:20px; margin-bottom:24px; text-align:center; }
  .revenue .amount { font-size:32px; font-weight:800; }
  .revenue p { margin:6px 0 0; font-size:14px; opacity:0.85; }
  h2 { font-size:16px; font-weight:700; color:#111827; margin:0 0 12px; }
  .items-table { width:100%; border-collapse:collapse; margin-bottom:24px; }
  .items-table th { background:#f9fafb; text-align:left; padding:10px 12px; font-size:13px; color:#6b7280; font-weight:600; border-bottom:2px solid #e5e7eb; }
  .items-table td { padding:12px; font-size:14px; color:#374151; border-bottom:1px solid #f3f4f6; }
  .items-table tr:last-child td { border-bottom:none; }
  .stock-section { margin-bottom:24px; }
  .stock-item { display:flex; justify-content:space-between; align-items:center; padding:10px 0; border-bottom:1px solid #f3f4f6; font-size:14px; }
  .stock-item:last-child { border-bottom:none; }
  .stock-badge { padding:3px 10px; border-radius:20px; font-size:12px; font-weight:600; }
  .badge-ok { background:#dcfce7; color:#16a34a; }
  .badge-low { background:#fef9c3; color:#b45309; }
  .badge-out { background:#fee2e2; color:#dc2626; }
  .cta { text-align:center; margin-bottom:28px; }
  .cta a { background:#1d4ed8; color:#fff; padding:14px 32px; border-radius:8px; text-decoration:none; font-weight:600; font-size:15px; display:inline-block; }
  .footer { background:#f9fafb; padding:20px 32px; text-align:center; border-top:1px solid #e5e7eb; }
  .footer p { margin:0; font-size:13px; color:#9ca3af; }
</style>
</head>
<body>
<div class="wrapper">
  <div class="header">
    <span class="icon">🛒</span>
    <h1>New Sale Alert!</h1>
    <p>You just got a new order on {{ $business->name }}</p>
  </div>

  <div class="body">
    <div class="revenue">
      <p>Total Revenue This Sale</p>
      <div class="amount">{{ $order->currency }} {{ number_format($order->total_amount, 2) }}</div>
      <p>Order #{{ $order->tracking_code }} · {{ $order->created_at->format('d M Y, g:i A') }}</p>
    </div>

    <div class="alert-box">
      <h2>📋 Customer Details</h2>
      <p>Name: <span class="val">{{ $order->customer_name ?? 'Unknown' }}</span></p>
      <p>Phone: <span class="val">{{ $order->customer_phone ?? '—' }}</span></p>
      @if($order->shipping_address)
      <p>Delivery To: <span class="val">{{ $order->shipping_address }}</span></p>
      @endif
      <p>Order Status: <span class="val" style="color:#16a34a">CONFIRMED ✅</span></p>
    </div>

    <h2>📦 Items Sold</h2>
    <table class="items-table">
      <thead>
        <tr>
          <th>Item</th>
          <th>Qty Sold</th>
          <th>Unit Price</th>
          <th>Revenue</th>
        </tr>
      </thead>
      <tbody>
        @foreach($order->items as $item)
        <tr>
          <td>
            {{ $item->item_name }}
            @if($item->size) <br><small style="color:#9ca3af">Size: {{ $item->size }}</small> @endif
          </td>
          <td>{{ $item->quantity }}</td>
          <td>{{ $order->currency }} {{ number_format($item->unit_price, 2) }}</td>
          <td>{{ $order->currency }} {{ number_format($item->total_price, 2) }}</td>
        </tr>
        @endforeach
      </tbody>
    </table>

    @php
      $trackedItems = $order->items->filter(fn($i) => $i->catalogItem && $i->catalogItem->track_inventory);
    @endphp

    @if($trackedItems->isNotEmpty())
    <div class="stock-section">
      <h2>📊 Updated Stock Levels</h2>
      @foreach($trackedItems as $item)
        @php
          $stock = $item->catalogItem->stock_quantity ?? 0;
          $badgeClass = $stock === 0 ? 'badge-out' : ($stock <= 3 ? 'badge-low' : 'badge-ok');
          $badgeText = $stock === 0 ? 'OUT OF STOCK' : ($stock <= 3 ? 'LOW STOCK' : 'In Stock');
        @endphp
        <div class="stock-item">
          <span>{{ $item->item_name }}</span>
          <span>
            {{ $stock }} units left
            <span class="stock-badge {{ $badgeClass }}">{{ $badgeText }}</span>
          </span>
        </div>
      @endforeach
    </div>
    @endif

    <div class="cta">
      <a href="https://app.knotax.com.ng/orders">View Order in Dashboard →</a>
    </div>
  </div>

  <div class="footer">
    <p>This alert was sent because a customer completed checkout on <strong>{{ $business->name }}</strong>.</p>
    <p style="margin-top:8px; font-size:12px;">Powered by Zeen</p>
  </div>
</div>
</body>
</html>

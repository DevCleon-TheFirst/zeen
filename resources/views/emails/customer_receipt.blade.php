<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Order Confirmation #{{ $order->tracking_code }}</title>
<style>
  body, table, td, a { -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
  table, td { mso-table-lspace: 0pt; mso-table-rspace: 0pt; }
  img { -ms-interpolation-mode: bicubic; border: 0; outline: none; text-decoration: none; }
  body { margin: 0; padding: 0; width: 100% !important; background-color: #faf8f5; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; color: #241e19; }
</style>
</head>
<body style="margin: 0; padding: 0; background-color: #faf8f5; -webkit-font-smoothing: antialiased;">
@php
    $appUrl = rtrim(config('app.url'), '/');
    $logoUrl = $business->logo ? (str_starts_with($business->logo, 'http') ? $business->logo : $appUrl.'/storage/'.$business->logo) : $appUrl.'/images/logo.png';
@endphp

<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color: #faf8f5; padding: 40px 16px;">
  <tr>
    <td align="center">
      <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="max-width: 580px; background-color: #ffffff; border: 1px solid #e8e2d9; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 12px rgba(36, 30, 25, 0.04);">
        
        <!-- BRAND HEADER -->
        <tr>
          <td align="center" style="padding: 36px 32px 28px; border-bottom: 1px solid #f2ece4; background-color: #ffffff;">
            <a href="{{ $appUrl }}" target="_blank" style="text-decoration: none; display: inline-block;">
              <img src="{{ $logoUrl }}" alt="{{ $business->name }}" height="44" style="height: 44px; max-height: 44px; width: auto; display: block; margin: 0 auto;" />
            </a>
            @if($business->name && $business->name !== 'Zeen')
              <div style="margin-top: 10px; font-size: 13px; font-weight: 600; letter-spacing: 0.5px; text-transform: uppercase; color: #7b6a58;">
                {{ $business->name }}
              </div>
            @endif
          </td>
        </tr>

        <!-- RECEIPT TITLE & STATUS -->
        <tr>
          <td style="padding: 32px 32px 24px;">
            <div style="display: inline-block; padding: 4px 12px; border-radius: 20px; background-color: #f5efeb; font-size: 11px; font-weight: 700; letter-spacing: 0.8px; text-transform: uppercase; color: #7b5537; margin-bottom: 14px;">
              Payment Confirmed
            </div>
            <h1 style="margin: 0 0 8px; font-size: 22px; font-weight: 700; color: #241e19; letter-spacing: -0.3px; line-height: 1.3;">
              Thank you for your order
            </h1>
            <p style="margin: 0; font-size: 14px; color: #7b6a58; line-height: 1.5;">
              We have received your payment and your order is now being processed.
            </p>
          </td>
        </tr>

        <!-- TRACKING CODE CARD -->
        <tr>
          <td style="padding: 0 32px 28px;">
            <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color: #faf8f5; border: 1px solid #e8e2d9; border-radius: 8px; padding: 18px 20px;">
              <tr>
                <td>
                  <div style="font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.6px; color: #8c7e72; margin-bottom: 4px;">
                    Tracking Code
                  </div>
                  <div style="font-family: 'SF Mono', Consolas, Monaco, monospace; font-size: 20px; font-weight: 700; color: #241e19; letter-spacing: 2px;">
                    {{ $order->tracking_code }}
                  </div>
                </td>
                <td align="right" valign="middle">
                  <div style="font-size: 12px; color: #8c7e72;">
                    {{ $order->created_at->format('M d, Y') }}
                  </div>
                </td>
              </tr>
            </table>
          </td>
        </tr>

        <!-- ORDER ITEMS TABLE -->
        <tr>
          <td style="padding: 0 32px 24px;">
            <div style="font-size: 13px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.6px; color: #241e19; margin-bottom: 12px;">
              Order Summary
            </div>
            <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="border-collapse: collapse;">
              <thead>
                <tr style="border-bottom: 1px solid #e8e2d9;">
                  <th align="left" style="padding: 8px 0; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; color: #8c7e72;">Item</th>
                  <th align="center" style="padding: 8px 12px; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; color: #8c7e72;">Qty</th>
                  <th align="right" style="padding: 8px 0; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; color: #8c7e72;">Price</th>
                </tr>
              </thead>
              <tbody>
                @foreach($order->items as $item)
                <tr style="border-bottom: 1px solid #f2ece4;">
                  <td style="padding: 14px 0; font-size: 14px; font-weight: 600; color: #241e19; vertical-align: top;">
                    {{ $item->item_name }}
                    @if($item->size || $item->color)
                      <div style="margin-top: 3px; font-size: 12px; font-weight: 400; color: #8c7e72;">
                        @if($item->size) Size: {{ $item->size }} @endif
                        @if($item->size && $item->color) &middot; @endif
                        @if($item->color) Color: {{ $item->color }} @endif
                      </div>
                    @endif
                  </td>
                  <td align="center" style="padding: 14px 12px; font-size: 13px; color: #7b6a58; vertical-align: top;">
                    {{ $item->quantity }}
                  </td>
                  <td align="right" style="padding: 14px 0; font-size: 14px; font-weight: 600; color: #241e19; vertical-align: top;">
                    {{ $order->currency }} {{ number_format($item->total_price, 2) }}
                  </td>
                </tr>
                @endforeach
              </tbody>
            </table>
          </td>
        </tr>

        <!-- TOTALS BREAKDOWN -->
        <tr>
          <td style="padding: 0 32px 28px;">
            <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="border-collapse: collapse;">
              @if($order->shipping_fee > 0)
              <tr>
                <td style="padding: 4px 0; font-size: 13px; color: #7b6a58;">Subtotal</td>
                <td align="right" style="padding: 4px 0; font-size: 13px; color: #241e19;">{{ $order->currency }} {{ number_format($order->subtotal, 2) }}</td>
              </tr>
              <tr>
                <td style="padding: 4px 0; font-size: 13px; color: #7b6a58;">Delivery Fee</td>
                <td align="right" style="padding: 4px 0; font-size: 13px; color: #241e19;">{{ $order->currency }} {{ number_format($order->shipping_fee, 2) }}</td>
              </tr>
              @endif
              <tr>
                <td style="padding: 12px 0 0; font-size: 15px; font-weight: 700; color: #241e19; border-top: 1px solid #e8e2d9;">Total Paid</td>
                <td align="right" style="padding: 12px 0 0; font-size: 17px; font-weight: 700; color: #241e19; border-top: 1px solid #e8e2d9;">{{ $order->currency }} {{ number_format($order->total_amount, 2) }}</td>
              </tr>
            </table>
          </td>
        </tr>

        <!-- CUSTOMER & DELIVERY INFO -->
        @if($order->shipping_address || $order->customer_name)
        <tr>
          <td style="padding: 0 32px 32px;">
            <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color: #faf8f5; border: 1px solid #e8e2d9; border-radius: 8px; padding: 18px 20px;">
              <tr>
                <td style="font-size: 13px; color: #241e19; line-height: 1.5;">
                  <div style="font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.6px; color: #8c7e72; margin-bottom: 6px;">
                    Delivery Information
                  </div>
                  @if($order->customer_name)
                    <div style="font-weight: 600; color: #241e19;">{{ $order->customer_name }}</div>
                  @endif
                  @if($order->customer_phone)
                    <div style="color: #7b6a58; font-size: 12px;">{{ $order->customer_phone }}</div>
                  @endif
                  @if($order->shipping_address)
                    <div style="margin-top: 6px; color: #241e19; font-size: 13px;">{{ $order->shipping_address }}</div>
                  @endif
                </td>
              </tr>
            </table>
          </td>
        </tr>
        @endif

        <!-- CTA BUTTON -->
        @if($business->phone)
        <tr>
          <td align="center" style="padding: 0 32px 36px;">
            <a href="https://wa.me/{{ preg_replace('/[^0-9]/', '', $business->phone) }}?text={{ urlencode('Hi! Tracking my order #'.$order->tracking_code) }}" target="_blank" style="display: inline-block; background-color: #291e17; color: #faf8f5; text-decoration: none; font-size: 13px; font-weight: 600; letter-spacing: 0.5px; padding: 14px 28px; border-radius: 8px; box-shadow: 0 2px 6px rgba(41, 30, 23, 0.15);">
              Track Order via WhatsApp
            </a>
          </td>
        </tr>
        @endif

        <!-- FOOTER -->
        <tr>
          <td align="center" style="padding: 24px 32px; background-color: #faf8f5; border-top: 1px solid #e8e2d9;">
            <p style="margin: 0; font-size: 12px; color: #8c7e72; line-height: 1.5;">
              If you have any questions, reply to this email or contact <strong style="color: #241e19;">{{ $business->name }}</strong>.
            </p>
            @if($business->email || $business->phone)
              <p style="margin: 6px 0 0; font-size: 12px; color: #8c7e72;">
                @if($business->email) {{ $business->email }} @endif
                @if($business->email && $business->phone) &middot; @endif
                @if($business->phone) {{ $business->phone }} @endif
              </p>
            @endif
          </td>
        </tr>

      </table>
    </td>
  </tr>
</table>

</body>
</html>

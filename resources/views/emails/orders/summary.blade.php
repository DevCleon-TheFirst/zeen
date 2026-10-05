<x-mail::message>
# Your Order Summary

Hi there,

Here is the summary of the order you just prepared at **{{ $businessName }}**.

<x-mail::table>
| Item | Size | Color | Qty | Price |
| :--- | :--- | :--- | :--- | :--- |
@foreach ($cart['items'] as $item)
| {{ $item['name'] }} | {{ $item['size'] ?? '-' }} | {{ $item['color'] ?? '-' }} | {{ $item['quantity'] ?? 1 }} | {{ $currency }} {{ number_format($item['price'], 2) }} |
@endforeach
</x-mail::table>

**Total Amount Due:** {{ $currency }} {{ number_format($amount, 2) }}

You can complete your payment securely by clicking the button below.

<x-mail::button :url="$checkoutUrl" color="success">
Complete Payment
</x-mail::button>

If you have any questions, feel free to reply to this email or chat with us on Telegram!

Thanks,<br>
{{ $businessName }}
</x-mail::message>

{{-- resources/views/emails/order-placed.blade.php --}}
@extends('emails.layout')
@section('content')
<p class="greeting">Order Confirmed! 🎉</p>
<p class="text">Hi {{ $order->buyer->name }}, your order has been placed successfully. Here's your summary:</p>

<div class="info-box">
  <p><strong>Order #{{ $order->order_number }}</strong></p>
  <p>Placed on {{ $order->created_at->format('d M Y, g:i A') }}</p>
</div>

<table class="data-table">
  @foreach($order->items as $item)
  <tr>
    <td>{{ $item->product_name }} <span style="color:#9ca3af">× {{ $item->quantity }}</span></td>
    <td>{{ number_format($item->subtotal) }} MMK</td>
  </tr>
  @endforeach
  <tr><td>Shipping</td><td>{{ number_format($order->shipping_fee) }} MMK</td></tr>
  <tr><td>Tax (5%)</td><td>{{ number_format($order->tax_amount) }} MMK</td></tr>
  @if($order->coupon_discount_amount > 0)
  <tr><td>Coupon Discount</td><td style="color:#059669">−{{ number_format($order->coupon_discount_amount) }} MMK</td></tr>
  @endif
  <tr class="total"><td>Total</td><td>{{ number_format($order->total_amount) }} MMK</td></tr>
</table>

@if($order->shipping_address)
<hr class="divider">
<p class="text" style="font-weight:600; margin-bottom:8px;">Shipping To</p>
<p class="text" style="margin-bottom:4px;">
  {{ $order->shipping_address['name'] ?? $order->buyer->name }}<br>
  {{ $order->shipping_address['address'] ?? '' }}<br>
  {{ implode(', ', array_filter([$order->shipping_address['city'] ?? '', $order->shipping_address['state'] ?? ''])) }}<br>
  {{ $order->shipping_address['phone'] ?? '' }}
</p>
@endif

<div style="text-align:center; margin-top:28px;">
  <a href="{{ config('app.frontend_url') }}/order-tracking?order={{ $order->order_number }}" class="btn">Track My Order</a>
</div>

<p class="text" style="font-size:13px; color:#9ca3af; text-align:center; margin-top:16px;">
  Questions? Reply to this email or visit our <a href="{{ config('app.frontend_url') }}/help" style="color:#059669;">Help Center</a>.
</p>
@endsection
@section('footer_note')This order confirmation was sent to {{ $order->buyer->email }}@endsection
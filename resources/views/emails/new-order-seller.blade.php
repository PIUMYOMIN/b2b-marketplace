{{-- resources/views/emails/new-order-seller.blade.php --}}
@extends('emails.layout')
@section('content')
<p class="greeting">🛒 New Order Received!</p>
<p class="text">Hi {{ $notifiable->name ?? 'Seller' }}, you have a new order in your store. Please process it promptly.</p>

<div class="info-box">
  <p><strong>Order #{{ $order->order_number }}</strong></p>
  <p>Placed {{ $order->created_at->diffForHumans() }}</p>
  <p>Payment: <strong>{{ ucfirst($order->payment_method ?? 'N/A') }}</strong> &nbsp;·&nbsp; Status: <strong>{{ ucfirst($order->payment_status ?? 'pending') }}</strong></p>
</div>

<table class="data-table">
  @foreach($order->items as $item)
  <tr>
    <td>{{ $item->product_name }} × {{ $item->quantity }}</td>
    <td>{{ number_format($item->subtotal) }} MMK</td>
  </tr>
  @endforeach
  <tr class="total"><td>Order Total</td><td>{{ number_format($order->total_amount) }} MMK</td></tr>
</table>

@if($order->shipping_address)
<hr class="divider">
<p class="text" style="font-weight:600; margin-bottom:6px;">Ship To</p>
<p class="text">
  {{ $order->shipping_address['name'] ?? 'Customer' }}<br>
  {{ $order->shipping_address['address'] ?? '' }},
  {{ $order->shipping_address['city'] ?? '' }}<br>
  {{ $order->shipping_address['phone'] ?? '' }}
</p>
@endif

<div style="text-align:center; margin-top:28px;">
  <a href="{{ config('app.frontend_url') }}/seller/dashboard?tab=orders" class="btn">Go to Order Dashboard</a>
</div>

<p class="text" style="font-size:13px; text-align:center; color:#9ca3af; margin-top:12px;">
  Please confirm and process this order within 24 hours.
</p>
@endsection
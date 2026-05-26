{{-- resources/views/emails/order-status-changed.blade.php --}}
@extends('emails.layout')
@php
  $statusConfig = [
    'processing'  => ['label'=>'Processing',     'color'=>'#3b82f6', 'bg'=>'#eff6ff'],
    'confirmed'   => ['label'=>'Confirmed',       'color'=>'#8b5cf6', 'bg'=>'#f5f3ff'],
    'shipped'     => ['label'=>'Shipped',         'color'=>'#f59e0b', 'bg'=>'#fffbeb'],
    'out_for_delivery'=>['label'=>'Out for Delivery','color'=>'#f97316','bg'=>'#fff7ed'],
    'delivered'   => ['label'=>'Delivered',       'color'=>'#10b981', 'bg'=>'#ecfdf5'],
    'cancelled'   => ['label'=>'Cancelled',       'color'=>'#ef4444', 'bg'=>'#fef2f2'],
    'refunded'    => ['label'=>'Refunded',         'color'=>'#6b7280', 'bg'=>'#f9fafb'],
  ];
  $cfg = $statusConfig[$order->status] ?? ['label'=>ucfirst($order->status),'color'=>'#374151','bg'=>'#f9fafb'];
@endphp
@section('content')
<p class="greeting">Order Update</p>
<p class="text">Hi {{ $order->buyer->name }}, your order status has been updated.</p>
<p class="text" style="font-size:14px; color:#6b7280;">သင့်အော်ဒါအခြေအနေကို ပြင်ဆင်ပြီးပါပြီ။</p>

<div style="background:{{ $cfg['bg'] }}; border-radius:12px; padding:20px; text-align:center; margin:20px 0;">
  <p style="font-size:13px; color:#6b7280; margin-bottom:6px;">Order #{{ $order->order_number }}</p>
  <span class="status-badge" style="background:{{ $cfg['color'] }}; color:#fff; font-size:15px; padding:8px 20px;">
    {{ $cfg['label'] }}
  </span>
  @if($order->delivery?->tracking_number)
  <p style="font-size:13px; color:#6b7280; margin-top:10px;">Tracking: <strong>{{ $order->delivery->tracking_number }}</strong></p>
  @endif
</div>

@if($order->status === 'shipped' || $order->status === 'out_for_delivery')
<div class="warning-box">
  <p>Estimated delivery: {{ $order->delivery?->estimated_delivery_date?->timezone(config('app.timezone'))->translatedFormat('d M Y') ?? '2–5 business days' }}</p>
</div>
@endif

@if($order->status === 'delivered')
<p class="text">Your order has been delivered! We hope you love what you ordered. Please take a moment to leave a review — it helps other buyers and supports the seller.</p>
@endif

<div style="text-align:center; margin-top:24px;">
  <a href="{{ config('app.frontend_url') }}/order-tracking?order={{ $order->order_number }}" class="btn">View Order Details</a>
</div>
@endsection

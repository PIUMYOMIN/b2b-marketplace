{{-- resources/views/emails/order-delivered-thank-you.blade.php --}}
@extends('emails.layout')

@section('content')

{{-- ── Hero checkmark ── --}}
<div style="text-align:center; margin-bottom:28px;">
    <div style="
        display:inline-flex;
        align-items:center;
        justify-content:center;
        width:72px; height:72px;
        background:linear-gradient(135deg,#10b981 0%,#059669 100%);
        border-radius:50%;
        margin-bottom:16px;
    ">
        <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="#ffffff" stroke-width="2.5"
             stroke-linecap="round" stroke-linejoin="round">
            <polyline points="20 6 9 17 4 12"/>
        </svg>
    </div>
    <p class="greeting" style="margin-bottom:4px;">Delivered! Thank you 🙏</p>
    <p style="font-size:14px; color:#6b7280; margin:0;">
        We're so glad your order arrived safely.
    </p>
</div>

{{-- ── Personal greeting ── --}}
<p class="text">
    Hi <strong>{{ $order->buyer->name }}</strong>,
</p>
<p class="text">
    Your order has been successfully delivered and marked as complete.
    We truly appreciate your trust in Pyonea and hope you are completely happy with your purchase.
</p>

{{-- ── Order summary box ── --}}
<div class="info-box">
    <p><strong>Order #{{ $order->order_number }}</strong></p>
    <p>Delivered on {{ now()->format('d M Y, g:i A') }}</p>
    @if($order->seller?->sellerProfile?->store_name)
        <p>Sold by {{ $order->seller->sellerProfile->store_name }}</p>
    @endif
</div>

{{-- ── Item list ── --}}
<table class="data-table">
    @foreach($order->items as $item)
    <tr>
        <td>
            {{ $item->product_name }}
            <span style="color:#9ca3af; font-weight:400;"> × {{ $item->quantity }}</span>
        </td>
        <td>{{ number_format($item->subtotal) }} MMK</td>
    </tr>
    @endforeach

    <tr>
        <td>Shipping</td>
        <td>{{ number_format($order->shipping_fee) }} MMK</td>
    </tr>

    @if($order->coupon_discount_amount > 0)
    <tr>
        <td>Coupon Discount</td>
        <td style="color:#059669;">−{{ number_format($order->coupon_discount_amount) }} MMK</td>
    </tr>
    @endif

    <tr class="total">
        <td>Total Paid</td>
        <td>{{ number_format($order->total_amount) }} MMK</td>
    </tr>
</table>

<hr class="divider">

{{-- ── Leave a review CTA ── --}}
<div style="
    background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
    border-radius:12px;
    padding:20px 24px;
    margin:20px 0;
    text-align:center;
">
    <p style="font-size:22px; margin-bottom:6px;">⭐</p>
    <p style="font-size:15px; font-weight:700; color:#92400e; margin-bottom:6px;">
        How was your experience?
    </p>
    <p style="font-size:13px; color:#b45309; margin-bottom:16px;">
        Your review helps other buyers and supports the seller.
        It only takes 30 seconds!
    </p>
    @php
        $firstItem = $order->items->first();
        $reviewUrl = config('app.frontend_url') . '/products/'
            . ($firstItem?->product?->slug_en ?? $firstItem?->product_id ?? '');
    @endphp
    <a href="{{ $reviewUrl }}" class="btn"
       style="background:linear-gradient(135deg,#f59e0b 0%,#d97706 100%); padding:12px 28px;">
        Leave a Review
    </a>
</div>

{{-- ── Shop again CTA ── --}}
<div style="text-align:center; margin: 24px 0 8px;">
    <a href="{{ config('app.frontend_url') }}/products" class="btn-outline">
        🛍 Shop Again at Pyonea
    </a>
</div>

<hr class="divider">

{{-- ── Support line ── --}}
<p class="text" style="font-size:13px; color:#9ca3af; text-align:center; margin-top:8px;">
    Something not right?
    <a href="{{ config('app.frontend_url') }}/help" style="color:#059669;">Contact Support</a>
    or reply to this email — we're here to help.
</p>

@endsection

@section('footer_note')
    This confirmation was sent to {{ $order->buyer->email }}
    for order #{{ $order->order_number }}.
@endsection
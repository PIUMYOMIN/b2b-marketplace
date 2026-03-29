{{-- resources/views/emails/order-otp.blade.php --}}
@extends('emails.layout')

@section('content')
<p class="greeting">Your Order Confirmation Code 🔐</p>
<p class="text">Hi {{ $userName }}, use the code below to confirm your order on Pyonea. This code expires in <strong>10 minutes</strong>.</p>

<div style="text-align:center; margin:32px 0;">
    <div style="display:inline-block; background:#f0fdf4; border:2px dashed #10b981; border-radius:14px; padding:24px 40px;">
        <div style="font-size:11px; font-weight:600; color:#6b7280; text-transform:uppercase; letter-spacing:2px; margin-bottom:10px;">
            Confirmation Code
        </div>
        <div style="font-size:44px; font-weight:800; letter-spacing:12px; color:#059669; font-family:'Courier New', Courier, monospace;">
            {{ $otp }}
        </div>
        <div style="font-size:12px; color:#9ca3af; margin-top:8px;">
            Expires in 10 minutes
        </div>
    </div>
</div>

<div class="info-box">
    <p>💰 Order total: <strong>{{ $orderTotal }}</strong></p>
</div>

<div class="warning-box">
    <p>⚠️ <strong>Never share this code.</strong> Pyonea staff will never ask for it. If you did not request this, please ignore this email — no order will be placed.</p>
</div>

<p class="text" style="font-size:13px; color:#9ca3af; text-align:center; margin-top:20px;">
    This code was generated for a checkout session on
    <a href="{{ config('app.frontend_url') }}" style="color:#059669;">pyonea.com</a>.
</p>
@endsection

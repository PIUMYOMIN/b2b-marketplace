{{-- resources/views/emails/delivery-status-updated.blade.php --}}
@extends('emails.layout')
@php
    $statusConfig = [
        'awaiting_pickup' => ['label' => 'Awaiting pickup', 'label_my' => 'ကောက်ယူရန် စောင့်နေပါသည်', 'color' => '#6366f1', 'bg' => '#eef2ff'],
        'picked_up' => ['label' => 'Picked up', 'label_my' => 'ကောက်ယူပြီးပါပြီ', 'color' => '#0ea5e9', 'bg' => '#f0f9ff'],
        'in_transit' => ['label' => 'In transit', 'label_my' => 'သယ်ယူပို့ဆောင်နေပါသည်', 'color' => '#f59e0b', 'bg' => '#fffbeb'],
        'out_for_delivery' => ['label' => 'Out for delivery', 'label_my' => 'ပို့ဆောင်ရန် ထွက်ခွာနေပါသည်', 'color' => '#f97316', 'bg' => '#fff7ed'],
        'delivered' => ['label' => 'Delivered', 'label_my' => 'ပို့ဆောင်ပြီးပါပြီ', 'color' => '#10b981', 'bg' => '#ecfdf5'],
        'failed' => ['label' => 'Delivery failed', 'label_my' => 'ပို့ဆောင်မှု မအောင်မြင်ပါ', 'color' => '#ef4444', 'bg' => '#fef2f2'],
        'cancelled' => ['label' => 'Delivery cancelled', 'label_my' => 'ပို့ဆောင်မှု ပယ်ဖျက်ထားပါသည်', 'color' => '#6b7280', 'bg' => '#f9fafb'],
        'returned' => ['label' => 'Returned', 'label_my' => 'ပြန်ပို့ထားပါသည်', 'color' => '#6b7280', 'bg' => '#f9fafb'],
    ];
    $cfg = $statusConfig[$delivery->status] ?? ['label' => ucfirst(str_replace('_', ' ', $delivery->status)), 'label_my' => '', 'color' => '#374151', 'bg' => '#f9fafb'];
@endphp

@section('content')
<p class="greeting">Delivery Update</p>
<p class="text">
    Hi {{ $notifiable->name ?? $order?->buyer?->name ?? 'there' }},
    the delivery status for order #{{ $order?->order_number }} has been updated.
</p>
<p class="text" style="font-size:14px; color:#6b7280;">
    အော်ဒါ #{{ $order?->order_number }} ၏ ပို့ဆောင်မှုအခြေအနေကို ပြင်ဆင်ပြီးပါပြီ။
</p>

<div style="background:{{ $cfg['bg'] }}; border-radius:12px; padding:20px; text-align:center; margin:20px 0;">
    <p style="font-size:13px; color:#6b7280; margin-bottom:6px;">Order #{{ $order?->order_number }}</p>
    <span class="status-badge" style="background:{{ $cfg['color'] }}; color:#fff; font-size:15px; padding:8px 20px;">
        {{ $cfg['label'] }}
    </span>
    @if($cfg['label_my'])
        <p style="font-size:13px; color:#4b5563; margin-top:10px;">{{ $cfg['label_my'] }}</p>
    @endif
    @if($delivery->tracking_number)
        <p style="font-size:13px; color:#6b7280; margin-top:10px;">
            Tracking: <strong>{{ $delivery->tracking_number }}</strong>
        </p>
    @endif
</div>

<table class="data-table">
    @if($delivery->carrier_name)
    <tr>
        <td>Carrier</td>
        <td>{{ $delivery->carrier_name }}</td>
    </tr>
    @endif
    @if($delivery->assigned_driver_name)
    <tr>
        <td>Driver</td>
        <td>{{ $delivery->assigned_driver_name }}</td>
    </tr>
    @endif
    @if($delivery->estimated_delivery_date)
    <tr>
        <td>Estimated delivery</td>
        <td>{{ $delivery->estimated_delivery_date->timezone(config('app.timezone'))->translatedFormat('d M Y') }}</td>
    </tr>
    @endif
</table>

<div style="text-align:center; margin-top:24px;">
    <a href="{{ config('app.frontend_url') }}/order-tracking?order={{ $order?->order_number }}" class="btn">
        Track Delivery
    </a>
</div>

<p class="text" style="font-size:13px; color:#9ca3af; text-align:center; margin-top:12px;">
    Need help with this delivery? Reply to this email or contact Pyonea Support.
</p>
@endsection

@section('footer_note')
    This delivery update was sent for order #{{ $order?->order_number }}.
@endsection

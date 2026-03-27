{{-- resources/views/emails/product-review.blade.php --}}
@extends('emails.layout')
@section('content')
    <p class="greeting">⭐ New Product Review</p>
    <p class="text">Hi {{ $seller->name }}, a buyer has left a review on one of your products.</p>

    <div class="info-box">
        <p><strong>{{ $review->product->name_en ?? $review->product->name_mm ?? 'Your Product' }}</strong></p>
        <p style="margin-top:6px;">
            @for($i = 1; $i <= 5; $i++){{ $i <= $review->rating ? '★' : '☆' }}@endfor
            &nbsp; <strong>{{ $review->rating }}/5</strong>
        </p>
        @if($review->comment)
            <p style="margin-top:8px; font-style:italic;">"{{ $review->comment }}"</p>
        @endif
        <p style="margin-top:6px; font-size:12px; color:#6b7280;">
            — {{ $review->user->name ?? 'Anonymous' }}, {{ $review->created_at->format('d M Y') }}
        </p>
    </div>

    @if($review->rating <= 3)
        <p class="text">Consider reaching out to this customer to resolve any issues and improve your service.</p>
    @else
        <p class="text">Great feedback! This review will help other buyers discover your products.</p>
    @endif

    <div style="text-align:center; margin-top:24px;">
        <a href="{{ config('app.frontend_url') }}/seller/dashboard?tab=reviews" class="btn">View All Reviews</a>
    </div>
@endsection
{{-- resources/views/emails/welcome.blade.php --}}
@extends('emails.layout')
@section('content')
<p class="greeting">Welcome to Pyonea! 👋</p>
<p class="text">Hi {{ $user->name }}, your account has been created. {{ $user->type === 'seller' ? "You're one step away from starting to sell on Myanmar's leading B2B marketplace." : "You can now browse thousands of products from verified sellers across Myanmar." }}</p>

@if($user->type === 'buyer')
<div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin:20px 0;">
  @foreach([['🛍️','Browse Products','Discover products from verified sellers','products'],['❤️','Save to Wishlist','Save items you love','wishlist'],['📦','Track Orders','Real-time order tracking','order-tracking'],['💬','Contact Sellers','Message sellers directly','sellers']] as $f)
  <div style="background:#f9fafb; border-radius:10px; padding:14px; text-align:center;">
    <div style="font-size:24px; margin-bottom:6px;">{{ $f[0] }}</div>
    <div style="font-size:13px; font-weight:600; color:#111827;">{{ $f[1] }}</div>
    <div style="font-size:12px; color:#6b7280; margin-top:2px;">{{ $f[2] }}</div>
  </div>
  @endforeach
</div>
@endif

@if($user->type === 'seller')
<div class="info-box">
  <p>Your seller application is under review. We'll email you within 2 business days once it's approved.</p>
</div>
@endif

<div style="text-align:center; margin-top:28px;">
  <a href="{{ config('app.frontend_url') }}/{{ $user->type === 'seller' ? 'seller/dashboard' : '' }}" class="btn">
    {{ $user->type === 'seller' ? 'Go to Seller Dashboard' : 'Start Shopping' }}
  </a>
</div>
@endsection
{{-- resources/views/emails/seller-approved.blade.php --}}
@extends('emails.layout')
@section('content')
<p class="greeting">🎉 Your Store Is Approved!</p>
<p class="text">Hi {{ $seller->store_name }}, congratulations — your seller account has been approved by the Pyonea team. You can now start listing products and selling to buyers across Myanmar.</p>

<div class="info-box">
  <p>✅ Seller ID: <strong>{{ $seller->store_id }}</strong></p>
  <p>✅ Store URL: <a href="{{ config('app.frontend_url') }}/sellers/{{ $seller->store_slug }}" style="color:#059669;">pyonea.com/sellers/{{ $seller->store_slug }}</a></p>
  <p>✅ Commission rate: <strong>{{ number_format($seller->seller_tier === 'gold' ? 4 : ($seller->seller_tier === 'silver' ? 5 : 6), 0) }}%</strong> ({{ ucfirst($seller->seller_tier ?? 'Bronze') }} tier)</p>
</div>

<p class="text" style="font-weight:600; margin-top:20px;">Quick start checklist:</p>
<p class="text">1. Add your first product &rarr; Dashboard → Products → Add Product<br>
2. Set up delivery zones &rarr; Dashboard → Delivery Zones<br>
3. Write your store policies &rarr; Dashboard → Store Profile → Policies<br>
4. Customise your store logo and banner &rarr; Dashboard → Store Profile → Images</p>

<div style="text-align:center; margin-top:28px;">
  <a href="{{ config('app.frontend_url') }}/seller/dashboard" class="btn">Go to Seller Dashboard</a>
</div>
@endsection
{{-- resources/views/emails/seller-rejected.blade.php --}}
@extends('emails.layout')
@section('content')
<p class="greeting">Application Update</p>
<p class="text">Hi {{ $seller->store_name }}, thank you for applying to sell on Pyonea. After reviewing your application, we were unable to approve it at this time.</p>

@if($reason)
<div class="warning-box">
  <p style="font-weight:600; margin-bottom:6px;">Reason:</p>
  <p>{{ $reason }}</p>
</div>
@endif

<p class="text">You're welcome to reapply after addressing the issues above. Please ensure all required documents are valid, clearly photographed, and match your business registration details.</p>

<div style="text-align:center; margin-top:28px;">
  <a href="{{ config('app.frontend_url') }}/seller/dashboard?tab=documents" class="btn-outline">Update Documents & Reapply</a>
</div>

<p class="text" style="font-size:13px; color:#9ca3af; margin-top:20px;">
  If you believe this decision is an error, please contact our support team at <a href="mailto:support@pyonea.com" style="color:#059669;">support@pyonea.com</a>.
</p>
@endsection
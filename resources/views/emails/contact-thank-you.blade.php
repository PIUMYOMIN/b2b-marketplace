{{-- resources/views/emails/contact-thank-you.blade.php --}}
@extends('emails.layout')

@section('content')
<p class="greeting">Thank you for contacting Pyonea</p>
<p class="text">Hi {{ $data['name'] }}, we received your message and our team will get back to you as soon as possible.</p>

<div class="info-box">
    <p><strong>Subject:</strong> {{ $data['subject'] }}</p>
    @if(!empty($data['phone']))
    <p><strong>Phone:</strong> {{ $data['phone'] }}</p>
    @endif
    <p><strong>Submitted:</strong> {{ now()->timezone(config('app.timezone'))->format('M j, Y g:i A') }}</p>
</div>

<hr class="divider">

<p class="text" style="font-weight:600; margin-bottom:8px;">Your message</p>
<div style="background:#f9fafb; border-radius:10px; padding:16px 20px; font-size:15px; color:#374151; line-height:1.7; white-space:pre-wrap;">{{ $data['message'] }}</div>

<p class="text" style="margin-top:20px;">
    Typical response time is within 1–2 business days. If your request is urgent, you can also reach us at
    <a href="mailto:{{ config('mail.addresses.support.address', 'support@pyonea.com') }}" style="color:#059669;">support@pyonea.com</a>
    or call {{ config('app.business_phone', '+95 9 792 115 547') }}.
</p>

<div style="text-align:center; margin-top:28px;">
    <a href="{{ config('app.frontend_url') }}" class="btn">Visit Pyonea</a>
</div>
@endsection

@section('footer_note')
This is an automated confirmation — please do not reply to this email unless you need to add more details.
@endsection

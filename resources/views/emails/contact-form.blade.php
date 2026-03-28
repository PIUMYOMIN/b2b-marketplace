{{-- resources/views/emails/contact-form.blade.php --}}
@extends('emails.layout')

@section('content')
<p class="greeting">📬 New Contact Message</p>
<p class="text">A visitor has submitted a message via the Pyonea contact form. Details are below.</p>

<div class="info-box">
    <p><strong>From:</strong> {{ $data['name'] }}</p>
    <p><strong>Email:</strong> <a href="mailto:{{ $data['email'] }}" style="color:#059669;">{{ $data['email'] }}</a></p>
    @if(!empty($data['phone']))
    <p><strong>Phone:</strong> {{ $data['phone'] }}</p>
    @endif
    <p><strong>Subject:</strong> {{ $data['subject'] }}</p>
</div>

<hr class="divider">

<p class="text" style="font-weight:600; margin-bottom:8px;">Message</p>
<div style="background:#f9fafb; border-radius:10px; padding:16px 20px; font-size:15px; color:#374151; line-height:1.7; white-space:pre-wrap;">{{ $data['message'] }}</div>

<div style="text-align:center; margin-top:28px;">
    <a href="mailto:{{ $data['email'] }}?subject=Re: {{ $data['subject'] }}" class="btn">Reply to {{ $data['name'] }}</a>
</div>

<p class="text" style="font-size:12px; color:#9ca3af; text-align:center; margin-top:16px;">
    This message was submitted from the contact form at
    <a href="{{ config('app.frontend_url') }}/contact" style="color:#059669;">pyonea.com/contact</a>
</p>
@endsection

@section('footer_note')Internal notification — do not forward this email.@endsection

{{-- resources/views/emails/newsletter-confirm.blade.php --}}
@extends('emails.layout')
@section('content')
    <p class="greeting">Almost there! ✉️</p>
    <p class="text">Hi {{ $name ?? 'there' }}, please confirm your email address to start receiving Pyonea updates,
        promotions, and new seller highlights.</p>

    <div style="text-align:center; margin:28px 0;">
        <a href="{{ config('app.frontend_url') }}/newsletter/confirm?token={{ $token }}" class="btn">Confirm My
            Subscription</a>
    </div>

    <p class="text" style="font-size:13px; color:#9ca3af; text-align:center;">
        This confirmation link expires in 48 hours. If you didn't sign up, you can safely ignore this email.
    </p>
@endsection
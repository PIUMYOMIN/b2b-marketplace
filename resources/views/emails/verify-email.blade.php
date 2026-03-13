{{-- Header with logo --}}
<div style="text-align: center; margin-bottom: 20px;">
    <img src="{{ config('app.logo_url') }}" alt="{{ config('app.name') }}" style="max-width: 150px; height: auto;">
</div>

@component('mail::message')
# Verify Your Email Address

Thanks for signing up! Please click the button below to verify your email address.

@component('mail::button', ['url' => $url, 'color' => 'success'])
Verify Email Address
@endcomponent

If you did not create an account, no further action is required.

Thanks,<br>
{{ config('app.name') }}
@endcomponent

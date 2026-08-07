{{-- Brand logo for email headers — uses APP_LOGO_URL (default: {frontend}/icon.png) --}}
@php
    $logoUrl = config('app.logo_url');
    $logoAlt = config('app.name', 'Pyonea');
    $logoSize = (int) ($size ?? 56);
@endphp
@if($logoUrl)
    <img src="{{ $logoUrl }}" alt="{{ $logoAlt }}" width="{{ $logoSize }}" height="{{ $logoSize }}"
        style="display:block; margin:0 auto {{ !empty($showTagline) ? '10px' : '0' }}; border:0; border-radius:50%; outline:none; text-decoration:none;">
@else
    <div style="font-size:24px; font-weight:800; color:#ffffff; letter-spacing:-0.5px; margin-bottom:{{ !empty($showTagline) ? '4px' : '0' }};">
        Pyonea<span style="color:rgba(255,255,255,.7);">.com</span>
    </div>
@endif
@if(!empty($showTagline))
    <div style="font-size:12px; color:rgba(255,255,255,.8); margin-top:4px;">Myanmar's Trusted B2B Marketplace · မြန်မာ့ B2B လက်ကားဈေးကွက်</div>
@endif

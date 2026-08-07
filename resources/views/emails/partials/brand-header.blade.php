{{-- Text brand header for email templates --}}
<div @if(empty($inline)) class="logo" @endif
    style="font-size:24px; font-weight:800; color:#ffffff; letter-spacing:-0.5px;">
    Pyonea<span @if(empty($inline)) class="logo-accent" @endif style="color:rgba(255,255,255,.7);">.com</span>
</div>
@if(empty($hideTagline))
    <div @if(empty($inline)) class="tagline" @endif
        style="font-size:12px; color:rgba(255,255,255,.8); margin-top:4px;">
        Myanmar's Trusted B2B Marketplace
    </div>
@endif

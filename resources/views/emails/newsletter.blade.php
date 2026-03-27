{{-- resources/views/emails/newsletter.blade.php --}}
@extends('emails.layout')
@section('content')
    <p class="greeting">{{ $campaign->subject }}</p>
    {!! $campaign->body_html !!}
@endsection
@section('unsubscribe_url'){{ config('app.frontend_url') }}/unsubscribe?token={{ $token }}@endsection
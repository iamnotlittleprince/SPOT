@extends('mail.layout')
@section('content')
<h1 style="margin:0 0 16px;font-size:26px;line-height:1.3;color:#17394f">{{ $copy['title'] }}</h1>
<p translate="no" style="margin:0 0 24px;font-size:16px;line-height:1.7">{{ $copy['intro'] }}</p>
@include('mail.partials.action')
<p style="margin:24px 0 0;padding:16px;background:#edf5fa;border:1px solid #c9dce8;border-radius:8px;font-size:15px;line-height:1.6">{{ $copy['expiry'] }}</p>
<p style="margin:20px 0 0;font-size:15px;line-height:1.7">{{ $copy['ignore'] }}</p>
<p style="margin:16px 0 0;font-size:15px;line-height:1.7;font-weight:bold">{{ $copy['note'] }}</p>
@include('mail.partials.fallback')
@endsection

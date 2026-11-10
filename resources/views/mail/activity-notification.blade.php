@extends('mail.layout')
@section('content')
<h1 style="margin:0 0 16px;font-size:26px;line-height:1.3;color:#17394f">{{ $copy['title'] }}</h1>
<p style="margin:0 0 24px;font-size:16px;line-height:1.7">{{ $copy['intro'] }}</p>
@foreach ($items as $item)
<div style="margin:0 0 20px;padding:16px;background:#edf5fa;border:1px solid #c9dce8;border-radius:8px;overflow-wrap:anywhere;word-break:break-word">
<h2 translate="no" style="margin:0 0 10px;font-size:18px;line-height:1.4;color:#17394f">{{ $item['title'] }}</h2>
<p translate="no" style="margin:0 0 10px;font-size:15px;line-height:1.7;white-space:pre-line">{{ $item['body'] }}</p>
<p translate="no" style="margin:0;font-size:13px;line-height:1.6;color:#496171">{{ $item['actor_name'] }}</p>
</div>
@endforeach
@include('mail.partials.action')
<p style="margin:24px 0 0;font-size:14px;line-height:1.7">{{ $copy['preferences'] }}</p>
@include('mail.partials.fallback')
@endsection

<!doctype html>
<html lang="{{ $mailLanguage }}">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>{{ $copy['subject'] }}</title>
<style>@media only screen and (max-width:480px) { .mail-outer { padding:16px 8px !important; } .mail-content, .mail-header { padding:24px 20px !important; } }</style>
</head>
<body style="margin:0;padding:0;background:#edf3f7;color:#20323f;font-family:Arial,Helvetica,sans-serif">
<div style="display:none;max-height:0;overflow:hidden" aria-hidden="true">{{ $copy['intro'] }}</div>
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#edf3f7"><tr><td class="mail-outer" align="center" style="padding:32px 16px">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:600px;background:#fff;border:1px solid #ccdce6;border-radius:16px;overflow:hidden">
<tr><td class="mail-header" style="padding:28px 28px 24px;background:#075681;color:#fff"><div style="font-size:30px;font-weight:bold;letter-spacing:1px">Spot</div><p style="margin:12px 0 0;font-size:12px;line-height:1.5;font-weight:bold;letter-spacing:1px">{{ $copy['tag'] }}</p></td></tr>
<tr><td class="mail-content" style="padding:28px;overflow-wrap:anywhere;word-break:break-word">
@yield('content')
</td></tr></table>
<p style="margin:20px 0 0;color:#496171;font-size:13px;line-height:1.6">{{ $copy['footer'] }}</p>
</td></tr></table>
</body></html>

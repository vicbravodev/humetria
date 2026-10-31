{{--
    The page of every email: the frame, the card and the footer under it, 600 px wide. Tables only, since Outlook has no
    flexbox nor grid; the theme (themes/humetria.css) is inlined into each element when it is sent. The slot is HTML,
    not Markdown: each email lays itself out with the components next to this one.
--}}
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="es-MX">
<head>
<title>{{ config('app.name') }}</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
<meta name="color-scheme" content="light">
<meta name="supported-color-schemes" content="light">
{{-- Figtree where the client loads web fonts (Apple Mail, iOS); the theme falls back to the system sans. --}}
<link href="https://fonts.googleapis.com/css2?family=Figtree:wght@400;500;600&display=swap" rel="stylesheet">
<style>
@media only screen and (max-width: 600px) {
.inner-body, .footer { width: 100% !important; }
.content-cell { padding: 28px 24px !important; }
.fact { display: block !important; width: 100% !important; padding: 0 0 12px !important; }
}
</style>
</head>
<body>
@if (! empty($preheader))
{{-- What the inbox shows after the subject; hidden in the email itself. --}}
<div class="preheader" style="display: none; max-height: 0; mso-hide: all;">{{ $preheader }}</div>
@endif
<table class="wrapper" width="100%" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td align="center" class="frame">
<table class="inner-body" align="center" width="600" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td class="content-cell">
{!! $header ?? '' !!}
{!! $slot !!}
</td>
</tr>
</table>
<table class="footer" align="center" width="600" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td class="footer-cell" align="center">
{!! $footer ?? '' !!}
</td>
</tr>
</table>
</td>
</tr>
</table>
</body>
</html>

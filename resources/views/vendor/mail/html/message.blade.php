@props([
    'preheader' => null,
    // Why the person gets this email, at the foot.
    'reason' => null,
    // An email to an employee carries their company (a CompanySummary): its logo or name, "Confidencial" and the seal
    // "hecho con humetría". Without it, the email is Humetría's: its logo and the legal line.
    'company' => null,
])
<x-mail::layout :preheader="$preheader">
<x-slot:header>
@if ($company)
<table class="brand" width="100%" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td align="left">
@if ($company->logo)
<img src="{{ $company->logo->url }}" width="{{ $company->logo->width }}" height="{{ $company->logo->height }}" alt="{{ $company->tradeName }}" class="company-logo">
@else
<span class="company-name">{{ $company->tradeName }}</span>
@endif
</td>
<td align="right" class="confidential"><img src="{{ asset('mail/lock.png') }}" width="14" height="14" alt="" class="inline-icon"> Confidencial</td>
</tr>
</table>
@else
<table class="brand" width="100%" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td align="left">
<a href="{{ config('app.url') }}"><img src="{{ asset('brand/humetria-horizontal-color.png') }}" class="logo" width="120" height="24" alt="Humetría"></a>
</td>
</tr>
</table>
@endif
</x-slot:header>

{!! $slot !!}

<x-slot:footer>
@if ($reason)
<p class="reason">{!! $reason !!}</p>
@endif
@if ($company)
<p class="seal">hecho con <strong>humetría</strong></p>
@else
<p class="legal">Humetría{{ config('mail.legal.address') ? ' · '.config('mail.legal.address') : '' }}@if (config('mail.legal.privacy_url')) · <a href="{{ config('mail.legal.privacy_url') }}">Aviso de privacidad</a>@endif</p>
@endif
</x-slot:footer>
</x-mail::layout>

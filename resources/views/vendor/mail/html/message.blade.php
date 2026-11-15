@props([
    'preheader' => null,
    // Why the person gets this email, at the foot.
    'reason' => null,
    // An email of a company carries it (a CompanySummary): its logo, or its initials and name. Without it, the email is
    // Humetría's: its logo and the legal line. Every one ends with the gray seal "hecho con humetría" (decision A15).
    'company' => null,
    // The survey's: "Confidencial" next to the company.
    'confidential' => false,
    // The one link of the person to everything of theirs (their portal), when the email goes to a person of the company.
    'portalUrl' => null,
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
<table cellpadding="0" cellspacing="0" role="presentation"><tr><td class="badge initials" width="32" height="32">{{ $company->initials() }}</td><td class="company-cell"><span class="company-name">{{ $company->tradeName }}</span></td></tr></table>
@endif
</td>
@if ($confidential)
<td align="right" class="confidential"><img src="{{ asset('mail/lock.png') }}" width="14" height="14" alt="" class="inline-icon"> Confidencial</td>
@endif
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
@if ($portalUrl)
<p class="reason">Todo lo suyo, en un solo lugar: <a href="{{ $portalUrl }}">Su portal</a>.</p>
@endif
<p class="seal">hecho con <strong>humetría</strong></p>
@if (! $company)
<p class="legal">Humetría{{ config('mail.legal.address') ? ' · '.config('mail.legal.address') : '' }}@if (config('mail.legal.privacy_url')) · <a href="{{ config('mail.legal.privacy_url') }}">Aviso de privacidad</a>@endif</p>
@endif
</x-slot:footer>
</x-mail::layout>

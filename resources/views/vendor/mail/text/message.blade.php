@props(['preheader' => null, 'reason' => null, 'company' => null, 'confidential' => false])
<x-mail::layout>
{!! $slot !!}
<x-slot:footer>
{!! $reason !!}

hecho con humetría{{ $company ? '' : ' · Humetría'.(config('mail.legal.address') ? ' · '.config('mail.legal.address') : '') }}
</x-slot:footer>
</x-mail::layout>

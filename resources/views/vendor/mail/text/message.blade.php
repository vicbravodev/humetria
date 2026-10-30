@props(['preheader' => null, 'reason' => null, 'company' => null])
<x-mail::layout>
{!! $slot !!}
<x-slot:footer>
{!! $reason !!}

{{ $company ? 'hecho con humetría' : 'Humetría'.(config('mail.legal.address') ? ' · '.config('mail.legal.address') : '') }}
</x-slot:footer>
</x-mail::layout>

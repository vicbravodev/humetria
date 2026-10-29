<x-mail::layout>
<x-slot:head>
{{-- Figtree where the client loads web fonts (Apple Mail, iOS); the theme falls back to the system sans. --}}
<link href="https://fonts.googleapis.com/css2?family=Figtree:wght@400;500;600&display=swap" rel="stylesheet">
<style>
@media only screen and (max-width: 600px) {
.content-cell, .header-cell {
padding-left: 24px !important;
padding-right: 24px !important;
}
}
</style>
</x-slot:head>

<x-slot:header>
<x-mail::header :url="config('app.url')">
{{ config('app.name') }}
</x-mail::header>
</x-slot:header>

{!! $slot !!}

@isset($subcopy)
<x-slot:subcopy>
<x-mail::subcopy>
{!! $subcopy !!}
</x-mail::subcopy>
</x-slot:subcopy>
@endisset

<x-slot:footer>
<x-mail::footer>
Enviado por Humetría, la plataforma para aplicar la NOM-035.
</x-mail::footer>
</x-slot:footer>
</x-mail::layout>

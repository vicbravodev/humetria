{{-- The same link to whoever has not answered yet, with the last day up front. --}}
<x-mail::message :company="$company" :portal-url="$portalUrl ?? null" :confidential="true" preheader="Son unos {{ $minutes }} minutos y puede omitir cualquier pregunta.">
<x-slot:reason>Le escribimos porque aún no recibimos su opinión. Si ya la envió, ignore este mensaje.@if ($contact) ¿Dudas? Escriba a <a href="mailto:{{ $contact }}">{{ $contact }}</a>.@endif</x-slot:reason>
<h1>{{ $title }}</h1>
<p>Hola, {{ $name }}:</p>
<p>Su opinión cuenta igual que la de todas las personas de {{ $company->tradeName }}. El sistema no guarda quién contestó qué y solo muestra resultados de grupos de {{ $minGroup }} personas o más.</p>
@include('mail.partials.facts', ['facts' => ['Tiempo' => "Unos {$minutes} minutos", 'Último día' => $lastDay]])
<x-mail::action>
<x-mail::button :url="$actionUrl">{{ $actionText }}</x-mail::button>
</x-mail::action>
@include('mail.partials.link', ['url' => $actionUrl, 'formal' => true])
</x-mail::message>

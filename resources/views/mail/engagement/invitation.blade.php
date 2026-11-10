{{-- The first email of a climate or pulse survey: what it is, what the system does not keep, and how long it takes. --}}
<x-mail::message :company="$company" :confidential="true" preheader="Unos {{ $minutes }} minutos, desde su celular.">
<x-slot:reason>Recibe este correo porque {{ $company->tradeName }} lo registró como parte de su equipo.@if ($contact) ¿Dudas? Escriba a <a href="mailto:{{ $contact }}">{{ $contact }}</a>.@endif</x-slot:reason>
<h1>Dé su opinión sobre el trabajo en {{ $company->tradeName }}</h1>
<p>Hola, {{ $name }}:</p>
<p>{{ $company->tradeName }} quiere saber cómo se vive el trabajo para decidir qué mejorar. Puede dejar sin contestar cualquier pregunta.</p>
@include('mail.partials.callout', ['tone' => 'accent', 'icon' => 'lock', 'lines' => [
    e("El sistema no guarda quién contestó qué y solo muestra resultados de grupos de {$minGroup} personas o más."),
]])
@include('mail.partials.facts', ['facts' => ['Tiempo' => "Unos {$minutes} minutos", 'Desde' => 'Su celular o computadora', 'Hasta el' => $lastDay]])
<x-mail::action>
<x-mail::button :url="$actionUrl">{{ $actionText }}</x-mail::button>
</x-mail::action>
<p class="note">El enlace es personal: no lo reenvíe.</p>
@include('mail.partials.link', ['url' => $actionUrl, 'formal' => true])
</x-mail::message>

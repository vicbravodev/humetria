{{-- The survey about seats and rests (criterio C-19): anonymous, short, from the company; no NOM-035. --}}
<x-mail::message :company="$company" preheader="Unos {{ $minutes }} minutos, desde su celular. Sus respuestas son anónimas.">
<x-slot:reason>Recibe este correo porque {{ $company->tradeName }} lo registró como parte de su centro de trabajo.@if ($contact) ¿Dudas? Escriba a <a href="mailto:{{ $contact }}">{{ $contact }}</a>.@endif</x-slot:reason>
<h1>Cuéntenos cómo son los asientos y las pausas en su trabajo</h1>
<p>Hola, {{ $name }}:</p>
<p>{{ $company->tradeName }} quiere saber si en {{ $workCenter }} hay asientos disponibles, si puede usarlos y si las pausas se respetan. Es parte del cumplimiento de la Ley Silla.</p>
@include('mail.partials.callout', ['tone' => 'accent', 'icon' => 'lock', 'lines' => [
    e("Sus respuestas son anónimas: nadie de su empresa puede ver lo que contesta. Recursos Humanos solo recibe resultados de grupos de {$minGroup} o más personas."),
]])
@include('mail.partials.facts', ['facts' => ['Tiempo' => "Unos {$minutes} minutos", 'Desde' => 'Su celular o computadora', 'Hasta el' => $lastDay]])
<x-mail::action>
<x-mail::button :url="$actionUrl">{{ $actionText }}</x-mail::button>
</x-mail::action>
<p class="note">El enlace es personal y se usa una sola vez: no lo reenvíe.</p>
@include('mail.partials.link', ['url' => $actionUrl, 'formal' => true])
</x-mail::message>

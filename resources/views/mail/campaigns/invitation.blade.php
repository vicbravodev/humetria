{{-- The first email of an evaluation: what it is, that the answers about work are anonymous, and how long it takes. --}}
<x-mail::message :company="$company" preheader="Unos {{ $minutes }} minutos, desde su celular. Sus respuestas sobre su trabajo son anónimas.">
<x-slot:reason>Recibe este correo porque {{ $company->tradeName }} lo registró para la evaluación NOM-035.@if ($contact) ¿Dudas? Escriba a <a href="mailto:{{ $contact }}">{{ $contact }}</a>.@endif</x-slot:reason>
<h1>Le invitamos a contestar la evaluación de su centro de trabajo</h1>
<p>Hola, {{ $name }}:</p>
<p>{{ $company->tradeName }} aplica la evaluación de factores de riesgo psicosocial que pide la NOM-035-STPS-2018. Sirve para identificar y prevenir lo que en el trabajo le puede causar estrés.</p>
@include('mail.partials.callout', ['tone' => 'accent', 'icon' => 'lock', 'lines' => [
    e("Nadie de su empresa puede ver lo que contesta sobre su trabajo. Recursos Humanos solo recibe resultados de grupos de {$minGroup} o más personas."),
    // Guía I is identified (criterio C-07): the email says so.
    'La parte sobre acontecimientos traumáticos sí lleva su nombre, para que la empresa pueda ofrecerle atención si la necesita.',
]])
@include('mail.partials.facts', ['facts' => ['Tiempo' => "Unos {$minutes} minutos", 'Desde' => 'Su celular o computadora', 'Hasta el' => $lastDay]])
<x-mail::action>
<x-mail::button :url="$actionUrl">{{ $actionText }}</x-mail::button>
</x-mail::action>
<p class="note">El enlace es personal: no lo reenvíe.</p>
@include('mail.partials.link', ['url' => $actionUrl, 'formal' => true])
</x-mail::message>

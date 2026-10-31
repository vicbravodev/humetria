{{-- Guía I after a severe traumatic event (criterio C-05): short, and clear that it carries the person's name (C-07). --}}
<x-mail::message :company="$company" preheader="Preguntas de sí o no. La empresa puede ofrecerle atención si la necesita.">
<h1>Una evaluación breve después de lo ocurrido</h1>
<p>Hola, {{ $name }}:</p>
<p>Por lo que sucedió en {{ $workCenter }}, la NOM-035 pide que conteste la Guía I. Empieza con 6 preguntas de sí o no y toma unos {{ $minutes }} minutos.</p>
@include('mail.partials.callout', ['tone' => 'warning', 'icon' => 'info', 'lines' => [
    'Esta guía <strong>sí lleva su nombre</strong>, para que la empresa pueda ofrecerle atención si la necesita. Solo la ven las personas autorizadas para darle seguimiento.',
]])
<p>Puede contestar hasta el {{ $lastDay }}.</p>
<x-mail::action>
<x-mail::button :url="$actionUrl">{{ $actionText }}</x-mail::button>
</x-mail::action>
@if ($contact)
<p class="note">Si necesita hablar con alguien antes, puede escribir a <a href="mailto:{{ $contact }}">{{ $contact }}</a>.</p>
@endif
@include('mail.partials.link', ['url' => $actionUrl, 'formal' => true])
</x-mail::message>

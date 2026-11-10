{{-- To a manager, in usted: the feedback of their team is ready in their portal. No figure, no name, no comment. --}}
<x-mail::message :company="$company" preheader="Resultados de su equipo, en su portal.">
<x-slot:reason>Recibe este correo porque {{ $company->tradeName }} lo registró como jefe o jefa de un equipo.</x-slot:reason>
<h1>Ya puede ver la retroalimentación de su equipo</h1>
<p>Hola, {{ $name }}:</p>
<p>{{ $company->tradeName }} cerró {{ $survey }}. Ya están los resultados de su equipo en su portal.</p>
@include('mail.partials.callout', ['tone' => 'accent', 'icon' => 'lock', 'lines' => [
    e('Solo verá resultados de grupo y un resumen de los comentarios: nunca lo que escribió o contestó alguien.'),
]])
<x-mail::action>
<x-mail::button :url="$actionUrl">{{ $actionText }}</x-mail::button>
</x-mail::action>
<p class="note">El enlace es personal: no lo reenvíe.</p>
@include('mail.partials.link', ['url' => $actionUrl, 'formal' => true])
</x-mail::message>

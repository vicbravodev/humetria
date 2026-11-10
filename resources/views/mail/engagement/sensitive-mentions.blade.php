{{-- To who manages the complaints channel: the comments of a survey had mentions of possible harassment or violence. No text, no count, no name. --}}
<x-mail::message :company="$company" preheader="Conviene difundir el canal de quejas.">
<x-slot:reason>Recibes este correo porque administras el canal de quejas de {{ $company->tradeName }}.</x-slot:reason>
<h1>Hubo menciones de posible acoso o violencia en los comentarios</h1>
<p>En los comentarios de {{ $survey }} hubo menciones de este tipo.</p>
<p>Te recomendamos difundir el canal de quejas entre tus personas.</p>
<x-mail::action>
<x-mail::button :url="$actionUrl">{{ $actionText }}</x-mail::button>
</x-mail::action>
</x-mail::message>

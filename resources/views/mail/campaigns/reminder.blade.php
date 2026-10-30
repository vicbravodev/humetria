{{-- A new link to whoever already got one and still has a guide pending, with the last day up front. --}}
<x-mail::message :company="$company" preheader="Toma unos {{ $minutes }} minutos y es confidencial.">
<x-slot:reason>Le escribimos porque su evaluación sigue pendiente. Si ya la contestó, ignore este mensaje.</x-slot:reason>
<h1>{{ $title }}</h1>
<p>Hola, {{ $name }}:</p>
<p>Aún no recibimos su evaluación. Su opinión cuenta igual que la de todas las personas de {{ $workCenter }}, y sigue siendo confidencial.</p>
<table class="box" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td width="52" style="padding-right: 0;">
<table width="52" cellpadding="0" cellspacing="0" role="presentation"><tr><td class="badge" height="56" style="padding: 8px 0;"><span style="display: block; font-size: 13px; line-height: 18px;">{{ $month }}</span><span style="display: block; font-size: 22px; line-height: 24px;">{{ $day }}</span></td></tr></table>
</td>
<td>
<p class="title">Último día: {{ $lastDayLong }}</p>
<p class="sub">Si ya envió una de las guías, no tiene que contestarla otra vez.</p>
</td>
</tr>
</table>
<x-mail::action>
<x-mail::button :url="$actionUrl">{{ $actionText }}</x-mail::button>
</x-mail::action>
@include('mail.partials.link', ['url' => $actionUrl, 'formal' => true])
</x-mail::message>

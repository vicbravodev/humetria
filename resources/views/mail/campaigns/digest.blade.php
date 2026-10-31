{{-- The owner's Monday digest of an open evaluation: the arc of answered against the target and the areas that lack the most. --}}
<x-mail::message :preheader="$behind ? $behind['name'].' es el área que más falta. Un recordatorio suele subir la participación.' : null">
<x-slot:reason>Te mandamos este resumen cada lunes mientras haya una evaluación en curso.</x-slot:reason>
<h1>{{ $headline }}</h1>
<p>{{ $summary }}</p>
<table class="chart" cellpadding="0" cellspacing="0" role="presentation">
<tr>
{{-- The arc is the cell's background: where a client drops it (Outlook on Windows), the number still reads. --}}
<td class="arc" width="140" height="101" background="{{ asset("mail/arc/{$percent}.png") }}" style="background-image: url('{{ asset("mail/arc/{$percent}.png") }}');"><span class="arc-number">{{ $completed }}</span><span class="arc-of">de {{ $target }}</span></td>
<td width="28"></td>
<td style="vertical-align: bottom;">
@foreach ($areas as $area)
<table width="100%" cellpadding="0" cellspacing="0" role="presentation" class="bar-row" @if (! $loop->last) style="margin-bottom: 10px;" @endif>
<tr>
<td>{{ $area['name'] }}</td>
<td align="right" style="white-space: nowrap;"><strong>{{ $area['completed'] }}</strong> <span class="arc-of" style="display: inline;">de {{ $area['total'] }}</span></td>
</tr>
<tr>
<td colspan="2">@include('mail.partials.bar', ['value' => $area['total'] === 0 ? 0 : (int) round(100 * $area['completed'] / $area['total'])])</td>
</tr>
</table>
@endforeach
</td>
</tr>
</table>
<x-mail::action>
<x-mail::button :url="$actionUrl">{{ $actionText }}</x-mail::button>
@if ($remind)
<x-mail::button :url="$actionUrl" color="outline">Ver avance</x-mail::button>
@endif
</x-mail::action>
</x-mail::message>

{{-- The owner's email when an evaluation closes: participation, how many questionnaires sit at each risk level, and what follows. --}}
<x-mail::message :preheader="$preheader">
<x-slot:reason>Recibes este aviso porque eres el owner de {{ $tradeName }}.</x-slot:reason>
<h1>{{ $workCenter }} cerró con {{ $answered }} de {{ $invited }} respuestas</h1>
<table class="chart" cellpadding="0" cellspacing="0" role="presentation">
<tr>
{{-- The arc is the cell's background: where a client drops it (Outlook on Windows), the number still reads. --}}
<td class="arc" width="140" height="101" background="{{ asset("mail/arc/closed-{$participation}.png") }}" style="background-image: url('{{ asset("mail/arc/closed-{$participation}.png") }}');"><span class="arc-number">{{ $participation }} %</span></td>
<td width="24"></td>
<td style="vertical-align: middle;">
@if ($high !== null)
<p style="margin: 0;"><strong>{{ $high }} de {{ $answered }} cuestionarios</strong> quedan en riesgo alto o muy alto.@if ($domain) El dominio con más cuestionarios en riesgo alto o muy alto es <strong>{{ $domain['label'] }}</strong>, con {{ $domainShare }} %.@endif</p>
@elseif ($belowMinimum)
<p style="margin: 0;">Con menos de {{ $minGroup }} cuestionarios no se muestran niveles de riesgo, para cuidar el anonimato.</p>
@else
<p style="margin: 0;">Contestaron {{ $answered }} de {{ $invited }} personas.</p>
@endif
</td>
</tr>
</table>
@if ($levels)
{{-- One segment per level, as wide as its share; a level with none takes no room. --}}
<table class="risk-bar" width="100%" cellpadding="0" cellspacing="0" role="presentation">
<tr>
@foreach (array_filter($levels, fn (array $level): bool => $level['count'] > 0) as $level)
<td width="{{ round(100 * $level['count'] / max(1, $answered), 2) }}%" height="12" style="background-color: {{ $level['color'] }};@if (! $loop->last) border-right: 2px solid #ffffff;@endif"></td>
@endforeach
</tr>
</table>
<table cellpadding="0" cellspacing="0" role="presentation" style="margin: 0 0 22px;">
<tr>
@foreach ($levels as $level)
<td class="legend"><span class="swatch" style="background-color: {{ $level['color'] }};"></span> {{ $level['label'] }} <strong>{{ $level['count'] }}</strong></td>
@endforeach
</tr>
</table>
@endif
@if ($assessment > 0 || $report)
<table class="rule" cellpadding="0" cellspacing="0" role="presentation">
<tr><td colspan="2" class="meta" style="color: #14211f; font-weight: 600;">Lo que sigue</td></tr>
@if ($assessment > 0)
<tr>
<td width="26" style="vertical-align: top; padding-top: 2px;"><img src="{{ asset('mail/hand-heart.png') }}" width="16" height="16" alt=""></td>
<td class="meta" style="color: #14211f;">{{ trans_choice('{1} :count persona requiere|[2,*] :count personas requieren', $assessment) }} valoración clínica (Guía I)</td>
</tr>
@endif
@if ($report)
<tr>
<td width="26" style="vertical-align: top; padding-top: 2px;"><img src="{{ asset('mail/file-text.png') }}" width="16" height="16" alt=""></td>
<td class="meta" style="color: #14211f;">Completa el informe 7.7 con las conclusiones y las acciones de intervención.</td>
</tr>
@endif
</table>
@endif
<x-mail::action>
<x-mail::button :url="$actionUrl">{{ $actionText }}</x-mail::button>
</x-mail::action>
</x-mail::message>

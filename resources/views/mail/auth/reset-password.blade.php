{{-- A new password: whose account, the link, and from where and when it was asked for. --}}
<x-mail::message preheader="El enlace vence en {{ $minutes }} minutos.">
<x-slot:reason>Te escribimos porque alguien pidió cambiar tu contraseña en Humetría.</x-slot:reason>
<h1>Crea una contraseña nueva</h1>
<p>Recibimos una solicitud para cambiar la contraseña de {{ $email }}.</p>
<x-mail::action>
<x-mail::button :url="$actionUrl">{{ $actionText }}</x-mail::button>
</x-mail::action>
<p class="note">El enlace vence en {{ $minutes }} minutos. Si no fuiste tú, ignora este correo: tu contraseña no cambia.</p>
@if ($origin)
<table class="rule" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td width="26" style="vertical-align: top; padding-top: 2px;"><img src="{{ asset('mail/monitor.png') }}" width="16" height="16" alt=""></td>
<td class="meta">Solicitud desde {{ $origin }}<br><span style="font-size: 12px;">Ubicación aproximada: <a href="https://db-ip.com">IP Geolocation by DB-IP</a></span></td>
</tr>
</table>
@endif
@include('mail.partials.link', ['url' => $actionUrl, 'formal' => false])
</x-mail::message>

{{-- The link to join a company: who invited, the role and what it allows, the company, and whom the link works for. --}}
<x-mail::message preheader="Entrarás como {{ $role }}. La invitación vence en {{ $days }} días.">
<x-slot:reason>Recibes este correo porque {{ $inviter === null ? "te invitaron a {$tradeName}" : "{$inviter} te invitó" }}. Si no esperabas esta invitación, ignórala: nadie entrará con tu correo.</x-slot:reason>
<h1>{{ $subject }}</h1>
<p>Entrarás como <strong>{{ $role }}</strong>: {{ $does }}</p>
<table class="box" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td width="40" style="padding-right: 0;">
<table width="40" cellpadding="0" cellspacing="0" role="presentation"><tr><td class="badge avatar" width="40" height="40">{{ $initials }}</td></tr></table>
</td>
<td>
<p class="title">{{ $tradeName }}</p>
<p class="sub">{{ $size }}</p>
</td>
</tr>
</table>
<x-mail::action>
<x-mail::button :url="$actionUrl">{{ $actionText }}</x-mail::button>
</x-mail::action>
<p class="note">La invitación vence el {{ $expiresOn }} y solo funciona con {{ $email }}.</p>
@include('mail.partials.link', ['url' => $actionUrl, 'formal' => false])
</x-mail::message>

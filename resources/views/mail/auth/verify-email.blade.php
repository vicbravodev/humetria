{{-- Confirms that an email is the person's before their account uses it. --}}
<x-mail::message preheader="El enlace vence en {{ $minutes }} minutos.">
<x-slot:reason>Te escribimos porque se creó una cuenta de Humetría con este correo.</x-slot:reason>
<h1>Verifica tu correo</h1>
<p>Confirma que {{ $email }} es tu correo para entrar a Humetría.</p>
<x-mail::action>
<x-mail::button :url="$actionUrl">{{ $actionText }}</x-mail::button>
</x-mail::action>
<p class="note">El enlace vence en {{ $minutes }} minutos. Si no creaste una cuenta, ignora este correo.</p>
@include('mail.partials.link', ['url' => $actionUrl, 'formal' => false])
</x-mail::message>

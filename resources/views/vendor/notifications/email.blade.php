{{--
    Every notification email: one reading line on the left, the action right after what it is for,
    and the fine print (expiry, "ignore this") next to the action. No salutation and no exclamation marks.
    The subject is the title, so the email says the same thing as the inbox; the greeting is the quiet line above it.
    The panel says "tú"; an email to an employee (`formal`) says "usted", as the survey does.
--}}
<x-mail::message>
@if (! empty($greeting))
<p class="greeting">{{ $greeting }}</p>
@endif

# {{ $subject ?? config('app.name') }}

@foreach ($introLines as $line)
{{ $line }}

@endforeach
@isset($actionText)
<x-mail::button :url="$actionUrl" :color="$level === 'error' ? 'error' : 'primary'" align="left">
{{ $actionText }}
</x-mail::button>
@endisset
@foreach ($outroLines as $line)
<p class="note">{{ $line }}</p>
@endforeach
@if (! empty($salutation))

{{ $salutation }}
@endif
@isset($actionText)
<x-slot:subcopy>
¿El botón no abre? {{ ($formal ?? false) ? 'Copie este enlace en su navegador' : 'Copia este enlace en tu navegador' }}: <span class="break-all">[{{ $displayableActionUrl }}]({{ $actionUrl }})</span>
</x-slot:subcopy>
@endisset
</x-mail::message>

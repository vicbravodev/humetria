{{--
    A notification without a view of its own: the headline (its subject), the greeting, its lines, the action and the
    fine print after it. Humetría's emails each have their view in resources/views/mail; this one is the fallback.
--}}
<x-mail::message :preheader="$preheader ?? null">
<h1>{{ $subject ?? config('app.name') }}</h1>
@if (! empty($greeting))
<p>{{ $greeting }}:</p>
@endif
@foreach ($introLines as $line)
<p>{{ $line }}</p>
@endforeach
@isset($actionText)
<x-mail::action>
<x-mail::button :url="$actionUrl">{{ $actionText }}</x-mail::button>
</x-mail::action>
@endisset
@foreach ($outroLines as $line)
<p class="note">{{ $line }}</p>
@endforeach
@isset($actionText)
@include('mail.partials.link', ['url' => $actionUrl, 'formal' => $formal ?? false])
@endisset
</x-mail::message>

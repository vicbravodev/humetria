@props([
    'url',
    // primary: the one action of the email; outline: a second one next to it.
    'color' => 'primary',
    'align' => 'left',
])
<a href="{{ $url }}" class="button button-{{ $color }}" target="_blank" rel="noopener">{!! $slot !!}</a>

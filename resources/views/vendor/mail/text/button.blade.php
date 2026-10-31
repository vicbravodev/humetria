@props(['url', 'color' => 'primary', 'align' => 'left'])
{!! trim(strip_tags($slot)) !!}: {{ $url }}

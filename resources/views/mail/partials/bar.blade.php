{{-- A thin bar filled to `$value` percent, as a table so Outlook draws it. --}}
<table class="bar-track" width="100%" cellpadding="0" cellspacing="0" role="presentation">
<tr>
@if ($value > 0)
<td class="bar-fill" width="{{ $value }}%" style="background-color: {{ $color ?? '#0b7a6f' }};"></td>
@endif
@if ($value < 100)
<td></td>
@endif
</tr>
</table>

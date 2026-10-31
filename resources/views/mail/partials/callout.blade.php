{{-- A highlighted note with its icon: accent for what protects the person, warning for what does carry their name. Each line is HTML. --}}
<table class="callout callout-{{ $tone }}" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td class="callout-icon" width="38"><img src="{{ asset("mail/{$icon}.png") }}" width="20" height="20" alt=""></td>
<td>
@foreach ($lines as $line)
<p @if (! $loop->last) style="margin-bottom: 8px;" @endif>{!! $line !!}</p>
@endforeach
</td>
</tr>
</table>

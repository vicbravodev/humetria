{{-- A few facts side by side between two rules, label over value; stacked on a phone. --}}
<table class="facts" cellpadding="0" cellspacing="0" role="presentation">
<tr>
@foreach ($facts as $label => $value)
<td class="fact"><span class="label">{{ $label }}</span> <span class="value">{{ $value }}</span></td>
@endforeach
</tr>
</table>

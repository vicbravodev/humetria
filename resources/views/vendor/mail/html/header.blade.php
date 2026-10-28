@props(['url'])
{{-- The logo as a PNG (Gmail and Outlook do not show SVG), on the same left line as the text of the card. --}}
<tr>
<td class="header">
<table class="header-inner" align="center" width="100%" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td class="header-cell">
<a href="{{ $url }}" style="display: inline-block;">
<img src="{{ asset('brand/humetria-horizontal-color.png') }}" class="logo" width="140" height="28" alt="Humetría">
</a>
</td>
</tr>
</table>
</td>
</tr>

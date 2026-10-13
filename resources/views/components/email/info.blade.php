{{-- Daftar label : nilai. rows = [[label, nilai, tebal?], ...]; baris bernilai null dilewati. --}}
@props(['rows'])
@php
    $font = "font-family:'Segoe UI',Arial,Helvetica,sans-serif;";
@endphp
<table role="presentation" width="100%" border="0" cellpadding="0" cellspacing="0" style="margin:0 0 20px 0;border-top:1px solid #E4E4E7;">
@foreach ($rows as $r)
@continue($r === null || ($r[1] ?? null) === null)
<tr>
<td width="38%" valign="top" style="padding:7px 12px 7px 0;border-bottom:1px solid #E4E4E7;{{ $font }}font-size:13px;line-height:19px;color:#71717A;">{{ $r[0] }}</td>
<td valign="top" style="padding:7px 0;border-bottom:1px solid #E4E4E7;{{ $font }}font-size:13px;line-height:19px;color:#18181B;{{ ($r[2] ?? false) ? 'font-weight:bold;' : '' }}">{{ $r[1] }}</td>
</tr>
@endforeach
</table>

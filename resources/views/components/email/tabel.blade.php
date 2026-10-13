{{--
    Tabel data dengan judul seksi. head = label kolom; rows = array baris (array nilai);
    kanan = index kolom yang rata kanan (angka); total = baris total (tebal), opsional.
--}}
@props(['judul' => null, 'head', 'rows', 'kanan' => [], 'total' => null])
@php
    $font = "font-family:'Segoe UI',Arial,Helvetica,sans-serif;";
    $rata = fn ($i) => in_array($i, $kanan, true) ? 'right' : 'left';
    $pad = fn ($i, $n) => $i === $n - 1 ? '7px 0 7px 8px' : ($i === 0 ? '7px 8px 7px 0' : '7px 8px');
    $n = count($head);
@endphp
@if ($judul)
<p style="margin:0 0 6px 0;{{ $font }}font-size:13px;line-height:19px;font-weight:bold;color:#145C33;">{{ $judul }}</p>
@endif
<table role="presentation" width="100%" border="0" cellpadding="0" cellspacing="0" style="margin:0 0 20px 0;">
<tr>
@foreach ($head as $i => $h)
<th align="{{ $rata($i) }}" valign="bottom" style="padding:{{ $pad($i, $n) }};border-bottom:2px solid #1B7A43;{{ $font }}font-size:12px;line-height:17px;font-weight:bold;color:#3F3F46;text-align:{{ $rata($i) }};">{{ $h }}</th>
@endforeach
</tr>
@foreach ($rows as $row)
<tr>
@foreach (array_values($row) as $i => $sel)
<td align="{{ $rata($i) }}" valign="top" style="padding:{{ $pad($i, $n) }};border-bottom:1px solid #E4E4E7;{{ $font }}font-size:13px;line-height:19px;color:#27272A;text-align:{{ $rata($i) }};">{{ $sel }}</td>
@endforeach
</tr>
@endforeach
@if ($total)
<tr>
@foreach (array_values($total) as $i => $sel)
<td align="{{ $rata($i) }}" valign="top" style="padding:{{ $pad($i, $n) }};{{ $font }}font-size:13px;line-height:19px;font-weight:bold;color:#18181B;text-align:{{ $rata($i) }};">{{ $sel }}</td>
@endforeach
</tr>
@endif
</table>

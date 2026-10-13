{{-- Kotak sorotan dengan garis kiri. warna = merah (alasan penolakan/pembatalan) | hijau (catatan/info). --}}
@props(['label' => null, 'warna' => 'hijau'])
@php
    $font = "font-family:'Segoe UI',Arial,Helvetica,sans-serif;";
    [$garis, $latar, $teks] = $warna === 'merah' ? ['#DC2626', '#FEF2F2', '#7F1D1D'] : ['#1B7A43', '#F1F8F3', '#14532D'];
@endphp
<table role="presentation" width="100%" border="0" cellpadding="0" cellspacing="0" style="margin:0 0 20px 0;">
<tr>
<td width="4" bgcolor="{{ $garis }}" style="background-color:{{ $garis }};font-size:0;line-height:0;">&nbsp;</td>
<td bgcolor="{{ $latar }}" style="background-color:{{ $latar }};padding:12px 16px;{{ $font }}font-size:14px;line-height:21px;color:{{ $teks }};">
@if ($label)
<span style="display:block;font-size:11px;line-height:16px;font-weight:bold;letter-spacing:0.5px;text-transform:uppercase;">{{ $label }}</span>
@endif
{{ $slot }}
</td>
</tr>
</table>

{{--
    Kerangka email notifikasi. HTML tabel + style inline (tanpa flex/grid/class CSS) supaya tampil
    sama di Outlook desktop, Gmail, dan mobile. Lebar konten 600px.
    Props: judul (heading), intro (kalimat pembuka). Isi lewat slot, pakai komponen x-email.*.
--}}
@props(['judul', 'intro' => null])
@php
    $font = "font-family:'Segoe UI',Arial,Helvetica,sans-serif;";
@endphp
<!DOCTYPE html>
<html lang="id" xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<title>{{ $judul }}</title>
<!--[if mso]>
<style type="text/css">
body, table, td, th, p, a, span { font-family: Arial, Helvetica, sans-serif !important; }
table { border-collapse: collapse; }
</style>
<![endif]-->
</head>
<body style="margin:0;padding:0;background-color:#E8F3EC;-webkit-text-size-adjust:100%;">
<table role="presentation" width="100%" border="0" cellpadding="0" cellspacing="0" bgcolor="#E8F3EC" style="background-color:#E8F3EC;">
<tr>
<td align="center" style="padding:24px 12px;">
<table role="presentation" width="600" border="0" cellpadding="0" cellspacing="0" style="width:600px;max-width:600px;">
<tr>
<td bgcolor="#145C33" style="background-color:#145C33;padding:16px 28px;{{ $font }}font-size:16px;font-weight:bold;color:#ffffff;">
Utility Sewa Ekspedisi
</td>
</tr>
<tr>
<td bgcolor="#ffffff" style="background-color:#ffffff;padding:28px;border-left:1px solid #D5E5DA;border-right:1px solid #D5E5DA;border-bottom:1px solid #D5E5DA;">
<p style="margin:0 0 8px 0;{{ $font }}font-size:19px;line-height:26px;font-weight:bold;color:#145C33;">{{ mb_strtoupper($judul) }}</p>
@if ($intro)
<p style="margin:0 0 16px 0;{{ $font }}font-size:14px;line-height:21px;color:#3F3F46;">{{ $intro }}</p>
@endif
{{ $slot }}
</td>
</tr>
<tr>
<td align="center" style="padding:16px 28px;{{ $font }}font-size:12px;line-height:18px;color:#4B6B57;">
Ini notifikasi otomatis dari Utility Sewa Ekspedisi. Buka aplikasi untuk detail dan tindak lanjut — mohon tidak membalas email ini.
</td>
</tr>
</table>
</td>
</tr>
</table>
</body>
</html>

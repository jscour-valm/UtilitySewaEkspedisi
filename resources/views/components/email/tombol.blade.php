{{-- Tombol aksi: tabel + sel berwarna (Outlook tidak mendukung padding/radius pada <a>). --}}
@props(['url'])
<table role="presentation" border="0" cellpadding="0" cellspacing="0" align="center" style="margin:8px auto 0 auto;">
<tr>
<td align="center" bgcolor="#1B7A43" style="background-color:#1B7A43;border-radius:6px;">
<a href="{{ $url }}" target="_blank" style="display:inline-block;padding:11px 26px;font-family:'Segoe UI',Arial,Helvetica,sans-serif;font-size:14px;line-height:20px;font-weight:bold;color:#ffffff;text-decoration:none;">{{ $slot }}</a>
</td>
</tr>
</table>

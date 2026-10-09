@php
    use App\Helpers\FormatHelper as F;

    $selisih = (! $isVendor && $hargaLama !== null) ? $hargaUsulan - $hargaLama : null;
    $persen = ($selisih !== null && $hargaLama > 0) ? abs($selisih) / $hargaLama * 100 : null;
@endphp
<x-mail::message>
    # {{ mb_strtoupper($judul) }}

    {{ $intro }}

    @if ($alasanPenolakan)
    <p style="text-align:center;font-size:20px;line-height:1.4;font-style:italic;font-weight:600;
        color:#145C33;margin:20px 0;">
        <span style="font-size:40px;line-height:0;color:#1B7A43;vertical-align:-12px;">
            &ldquo;
        </span>{{ $alasanPenolakan }}
        <span style="font-size:40px;line-height:0;color:#1B7A43;vertical-align:-12px;">
            &rdquo;
        </span>
    </p>
    @endif

    <x-mail::table>
        | | |
        |:--|:--|
        | Cabang Pengaju | {{ $namaCabang }} ({{ $objek->id_cabang_pengaju }}) |
        | Nama Ekspedisi | {{ $namaVendor ?: '-' }} |
        @if ($isVendor)
        | No. Telepon | {{ $vendor?->no_telepon ?: '-' }} |
        | Alamat Kantor | {{ $vendor?->alamat_kantor ?: '-' }} |
        @else
        | Jenis | {{ $objek->jenis === 'sewa_truk' ? 'Sewa Truk' : 'Kiriman Rutin' }} |
        | Area | {{ $area }} ({{ $objek->cabang_code }}) |
        @if ($barang)
        | Jenis Barang | {{ $barang }} |
        @endif
        | Harga Master Saat Ini | {{ $hargaLama !== null ? F::rupiah($hargaLama) : 'Belum ada' }} |
        | Harga Usulan | **{{ F::rupiah($hargaUsulan) }}** |
        @if ($selisih !== null && $selisih != 0)
        | Selisih | {{ $selisih > 0 ? '+' : '−' }}{{ F::rupiah(abs($selisih)) }}{{ $persen !== null ? ' (' . number_format($persen, 1, ',', '.') . '%)' : '' }} |
        @endif
        @endif
        | Diajukan oleh | {{ $objek->submittedBy?->name ?? '-' }} ({{ $objek->submitted_at?->format('d/m/Y H:i') ?? '-' }}) |
    </x-mail::table>

    @if (! $isVendor && $objek->catatan)
    **Catatan pengaju:** {{ $objek->catatan }}
    @endif

    @if ($riwayat->isNotEmpty())
    **Riwayat keputusan**
    <x-mail::table>
        | Tahap | Oleh | Keputusan | Waktu |
        |:--|:--|:--|:--|
        @foreach ($riwayat as $log)
        | {{ $log->role_approver ?? '-' }} | {{ $log->approver?->name ?? '-' }} | {{ $log->status === 'approved' ? ($log->role_approver === 'WM' ? 'Divalidasi' : 'Disetujui') : 'Ditolak' }}{{ $log->alasan_penolakan ? ' — ' . $log->alasan_penolakan : '' }} | {{ $log->decided_at?->format('d/m/Y H:i') }} |
        @endforeach
    </x-mail::table>
    @endif

    <x-mail::button :url="$url" color="primary">
    Buka Detail
    </x-mail::button>
</x-mail::message>

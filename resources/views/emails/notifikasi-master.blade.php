{{-- Email proses vendor baru / usulan harga master. HTML biasa (bukan markdown) lewat komponen x-email.*. --}}
@php
    use App\Helpers\FormatHelper as F;

    $selisih = (! $isVendor && $hargaLama !== null) ? $hargaUsulan - $hargaLama : null;
    $persen = ($selisih !== null && $hargaLama > 0) ? abs($selisih) / $hargaLama * 100 : null;

    // [label, nilai, tebal?] — baris bernilai null tidak ditampilkan
    $info = [
        ['Cabang Pengaju', $namaCabang . ' (' . $objek->id_cabang_pengaju . ')'],
        ['Nama Ekspedisi', $namaVendor ?: '-'],
    ];
    if ($isVendor) {
        $info[] = ['No. Telepon', $vendor?->no_telepon ?: '-'];
        $info[] = ['Alamat Kantor', $vendor?->alamat_kantor ?: '-'];
    } else {
        $info[] = ['Jenis', $objek->jenis === 'sewa_truk' ? 'Sewa Truk' : 'Kiriman Rutin'];
        $info[] = ['Area', $area . ' (' . $objek->cabang_code . ')'];
        $info[] = $barang ? ['Jenis Barang', $barang] : null;
        $info[] = ['Harga Master Saat Ini', $hargaLama !== null ? F::rupiah($hargaLama) : 'Belum ada'];
        $info[] = ['Harga Usulan', F::rupiah($hargaUsulan), true];
        $info[] = ($selisih !== null && $selisih != 0)
            ? ['Selisih', ($selisih > 0 ? '+' : '−') . F::rupiah(abs($selisih)) . ($persen !== null ? ' (' . number_format($persen, 1, ',', '.') . '%)' : '')]
            : null;
    }
    $info[] = ['Diajukan oleh', ($objek->submittedBy?->name ?? '-') . ' (' . ($objek->submitted_at?->format('d/m/Y H:i') ?? '-') . ')'];

    $barisRiwayat = $riwayat->map(fn ($log) => [
        $log->role_approver ?? '-',
        $log->approver?->name ?? '-',
        ($log->status === 'approved' ? ($log->role_approver === 'WM' ? 'Divalidasi' : 'Disetujui') : 'Ditolak')
            . ($log->alasan_penolakan ? ' — ' . $log->alasan_penolakan : ''),
        $log->decided_at?->format('d/m/Y H:i') ?? '-',
    ])->all();
@endphp
<x-email.layout :judul="$judul" :intro="$intro">
@if ($alasanPenolakan)
<x-email.kotak warna="merah" label="Alasan">{{ $alasanPenolakan }}</x-email.kotak>
@endif
<x-email.info :rows="$info" />
@if (! $isVendor && $objek->catatan)
<x-email.kotak label="Catatan pengaju">{{ $objek->catatan }}</x-email.kotak>
@endif
@if ($barisRiwayat)
<x-email.tabel judul="Riwayat keputusan" :head="['Tahap', 'Oleh', 'Keputusan', 'Waktu']" :rows="$barisRiwayat" />
@endif
<x-email.tombol :url="$url">Buka Detail</x-email.tombol>
</x-email.layout>

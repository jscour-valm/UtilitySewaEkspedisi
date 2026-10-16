{{-- Email notifikasi pengajuan sewa (semua tipe). HTML biasa (bukan markdown) lewat komponen x-email.*. --}}
@php
    use App\Helpers\FormatHelper as F;
    use Carbon\Carbon;

    $p = $pengajuan;
    $rasio = (float) $p->rasio_sewa;
    $overAmbang = $rasio > $ambangRasio;
    $persen = fn ($n) => number_format($n, 2, ',', '.') . '%';
    $namaVendorLengkap = trim((($badanUsaha && $badanUsaha !== '-') ? $badanUsaha . ' ' : '') . $vendor);

    $alur = $p->alurApproval();
    $alasanAlur = [];
    if (in_array('WC', $alur, true)) {
        $alasanAlur[] = 'tujuan penyewaan PAC';
    }
    if (in_array('WH', $alur, true)) {
        $alasanAlur[] = ($p->pakaiRasio() && $overAmbang)
            ? 'rasio sewa ' . $persen($rasio) . ' melebihi batas ' . $persen($ambangRasio)
            : 'area kirim baru (aturan alur saat pengajuan dibuat)';
    }
    $areaBaru = $p->areaBaru();
    $toAcb = $dokumen && $dokumen['tipe'] === 'TO-ACB';

    // [label, nilai, tebal?] — baris bernilai null tidak ditampilkan
    $info = [
        ['No. Pengajuan', $p->kode],
        ['Cabang', $namaCabang . ' (' . $p->id_cabang . ')'],
        ['Jenis', $isRutin ? 'Kiriman Rutin' : 'Sewa Truk'],
        ['Nama Ekspedisi', $namaVendorLengkap ?: '-'],
        ['Area Kirim', $area],
        ['Tgl Pengiriman', Carbon::parse($p->tanggal_pengiriman)->format('d/m/Y')],
        (! $isRutin && $p->kendaraan) ? ['Tipe / Nopol', ($p->kendaraan->jenis_kendaraan ?: '-') . ' / ' . ($p->kendaraan->plat_nomor_truk ?: '-')] : null,
        (! $isRutin && $p->kendaraan) ? ['Kapasitas Kendaraan', $p->kendaraan->muatan_maksimal ? F::ton($p->kendaraan->muatan_maksimal) : '-'] : null,
        ['Tujuan Penyewaan', ($p->tujuan_penyewaan ?: '-') . ($p->kategori_toko ? ' (' . $p->kategori_toko . ')' : '')],
        $p->tujuan_penyewaan === 'PAC' ? ['Cabang Tujuan', $p->labelCabangTujuan() ?? '-'] : null,
        $dokumen ? ['Jumlah ' . ($toAcb ? 'TO-ACB' : 'Surat Jalan'), $dokumen['jumlah_dokumen']] : null,
        // Baris opsional hanya tampil kalau ada isinya. Biaya tambahan (termasuk helper) ada di "Rincian biaya".
        ($dokumen && ! $toAcb && $dokumen['jumlah_toko']) ? ['Jumlah Toko', $dokumen['jumlah_toko']] : null,
        ($dokumen && $dokumen['total_berat_kg'] > 0) ? ['Tonase', F::kg($dokumen['total_berat_kg'])] : null,
        $p->pakaiRasio() ? ['Nilai ' . ($isRutin ? 'Muatan' : 'SJ'), F::rupiah($p->value_muatan)] : null,
        $p->pakaiRasio() ? ['Rasio Sewa', $persen($rasio) . ' (' . ($overAmbang ? 'di atas' : 'di bawah') . ' ambang ' . $persen($ambangRasio) . ')', $overAmbang] : null,
        ['Alur Approval', implode(' → ', $alur) . ($alasanAlur ? ' — ' . implode(', ', $alasanAlur) : '')],
        ['Diajukan oleh', ($p->submittedBy
            ? $p->submittedBy->name . ' — ' . $p->submittedBy->username . ' · ' . $p->submittedBy->getRoleLabel() . ' · ' . $p->id_cabang
            : '-') . ' (' . Carbon::parse($p->submitted_at)->format('d/m/Y H:i') . ' WIB)'],
    ];

    $barisBarang = $isRutin
        ? $p->detailKirimanRutin->map(fn ($d) => [$d->jenisBarang?->nama_barang ?? '-', F::trimDecimal($d->quantity), F::rupiah($d->harga_satuan), F::rupiah($d->subtotal)])->all()
        : [];

    $barisBiaya = array_merge(
        [[$isRutin ? 'Biaya kiriman' : 'Biaya sewa', F::rupiah($p->harga_sewa)]],
        collect($biaya)->map(fn ($b) => [$b['nama'], F::rupiah($b['jumlah'])])->all(),
    );

    $barisRiwayat = $riwayat->map(fn ($log) => [
        $log->peran ?? '-',
        $log->approver?->name ?? '-',
        (strtolower($log->status) === 'approved' ? ($log->peran === 'WM' ? 'Divalidasi' : 'Disetujui') : 'Ditolak')
            . ($log->alasan_penolakan ? ' — ' . $log->alasan_penolakan : ''),
        Carbon::parse($log->decided_at)->format('d/m/Y H:i'),
    ])->all();

    $barisDokumen = collect($dokumen['items'] ?? [])->map(fn ($d) => $toAcb
        ? [$d['nomor'], $d['last_shipment_no'] ?? '-', F::kg($d['berat'] ?? 0), F::rupiah($d['nilai'] ?? 0)]
        : [$d['nomor'], $d['customer'] ?? '-', $d['kota'] ?? '-', F::kg($d['berat'] ?? 0), F::rupiah($d['nilai'] ?? 0)])->all();
@endphp
<x-email.layout :judul="$judul" :intro="$intro">
@if ($alasanPenolakan)
<x-email.kotak warna="merah" label="Alasan">{{ $alasanPenolakan }}</x-email.kotak>
@endif
@if ($areaBaru)
<x-email.kotak label="Info">Pengajuan ini menambah area kirim baru: {{ implode(', ', $areaBaru) }}.</x-email.kotak>
@endif
<x-email.info :rows="$info" />
@if ($p->catatan_pengajuan)
<x-email.kotak label="Catatan pengaju">{{ $p->catatan_pengajuan }}</x-email.kotak>
@endif
@if ($barisBarang)
<x-email.tabel judul="Rincian barang kiriman" :head="['Jenis Barang', 'Qty', 'Harga Satuan', 'Subtotal']" :rows="$barisBarang" :kanan="[1, 2, 3]" />
@endif
<x-email.tabel judul="Rincian biaya" :head="['Komponen', 'Jumlah']" :rows="$barisBiaya" :kanan="[1]" :total="['Total biaya', F::rupiah($totalBiayaSewa)]" />
@if ($barisRiwayat)
<x-email.tabel judul="Riwayat approval" :head="['Tahap', 'Approver', 'Keputusan', 'Waktu']" :rows="$barisRiwayat" />
@endif
@if ($barisDokumen && $toAcb)
<x-email.tabel judul="Daftar TO-ACB" :head="['No TO-ACB', 'Last Shipment', 'Berat', 'Nilai']" :rows="$barisDokumen" :kanan="[2, 3]"
    :total="['Total', '', F::kg($dokumen['total_berat_kg'] ?? 0), F::rupiah($dokumen['total_nilai'] ?? 0)]" />
@elseif ($barisDokumen)
<x-email.tabel judul="Daftar Surat Jalan" :head="['No SJ', 'Customer', 'Kota', 'Berat', 'Nilai']" :rows="$barisDokumen" :kanan="[3, 4]"
    :total="['Total', '', '', F::kg($dokumen['total_berat_kg'] ?? 0), F::rupiah($dokumen['total_nilai'] ?? 0)]" />
@endif
<x-email.tombol :url="$url">Buka Detail Pengajuan</x-email.tombol>
</x-email.layout>

@php
    use App\Helpers\FormatHelper as F;

    $p = $pengajuan;
    $tglKirim = \Carbon\Carbon::parse($p->tanggal_pengiriman)->format('d/m/Y');
    $rasio = (float) $p->rasio_sewa;
    $overAmbang = $rasio > $ambangRasio;
    $namaVendorLengkap = trim((($badanUsaha && $badanUsaha !== '-') ? $badanUsaha . ' ' : '') . $vendor);
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

    @php
        $alur = $p->alurApproval();
        $alasanAlur = [];
        if (in_array('WC', $alur, true)) { $alasanAlur[] = 'tujuan penyewaan PAC'; }
        if (in_array('WH', $alur, true)) {
            $alasanAlur[] = (!$isRutin && $overAmbang)
                ? 'rasio sewa ' . number_format($rasio, 2, ',', '.') . '% melebihi ambang ' . number_format($ambangRasio, 2, ',', '.') . '%'
                : 'ada area kirim baru';
        }
    @endphp
    @if (count($alur) > 1)
        **Alur approval: {{ implode(' → ', $alur) }}**{{ $alasanAlur ? ' — ' . implode(', ', $alasanAlur) : '' }}.
    @endif

    <x-mail::table>
        | | |
        |:--|:--|
        | No. Pengajuan | #{{ $p->id_pengajuan_sewa }} |
        | Cabang | {{ $namaCabang }} ({{ $p->id_cabang }}) |
        | Jenis | {{ $isRutin ? 'Kiriman Rutin' : 'Sewa Truk' }} |
        | Nama Ekspedisi | {{ $namaVendorLengkap ?: '-' }} |
        | Area Kirim | {{ $area }} |
        | Tgl Pengiriman | {{ $tglKirim }} |
        @if (!$isRutin && $p->kendaraan)
        | Tipe / Nopol | {{ $p->kendaraan->jenis_kendaraan ?: '-' }} / {{ $p->kendaraan->plat_nomor_truk ?: '-' }} |
        | Kapasitas Kendaraan | {{ $p->kendaraan->muatan_maksimal ? F::ton($p->kendaraan->muatan_maksimal) : '-' }} |
        @endif
        | Tujuan Penyewaan | {{ $p->tujuan_penyewaan ?: '-' }}{{ $p->kategori_toko ? ' (' . $p->kategori_toko . ')' : '' }} |
        @if ($p->tujuan_penyewaan === 'PAC')
        | Cabang Asal Barang | {{ $p->id_cabang_asal ?: '-' }} |
        @endif
        @if ($dokumen)
        | Jumlah {{ $dokumen['tipe'] === 'TO-ACB' ? 'TO-ACB' : 'Surat Jalan' }} | {{ $dokumen['jumlah_dokumen'] }} |
        @if ($dokumen['tipe'] !== 'TO-ACB')
        | Jumlah Toko | {{ $dokumen['jumlah_toko'] ?: '-' }} |
        @endif
        | Tonase | {{ $dokumen['total_berat_kg'] > 0 ? F::kg($dokumen['total_berat_kg']) : '-' }} |
        @endif
        | Nilai {{ $isRutin ? 'Muatan' : 'SJ' }} | {{ F::rupiah($p->value_muatan) }} |
        | Helper Harian | {{ $helper ? F::rupiah($helper['jumlah']) : '-' }} |
        @unless ($isRutin)
        | Rasio Sewa | {{ number_format($rasio, 2, ',', '.') }}% ({{ $overAmbang ? 'di atas' : 'di bawah' }} ambang {{ number_format($ambangRasio, 2, ',', '.') }}%) |
        @endunless
        | Alur Approval | {{ implode(' → ', $alur) }} |
        | Diajukan oleh | {{ $p->submittedBy?->name ?? '-' }} ({{ \Carbon\Carbon::parse($p->submitted_at)->format('d/m/Y H:i') }}) |
    </x-mail::table>

    @if ($isRutin && $p->detailKirimanRutin->isNotEmpty())
        **Rincian barang kiriman**
        <x-mail::table>
            | Jenis Barang | Qty | Harga Satuan | Subtotal |
            |:--|--:|--:|--:|
            @foreach ($p->detailKirimanRutin as $d)
            | {{ $d->jenisBarang?->nama_barang ?? '-' }} | {{ F::trimDecimal($d->quantity) }} | {{ F::rupiah($d->harga_satuan) }} | {{ F::rupiah($d->subtotal) }} |
            @endforeach
        </x-mail::table>
    @endif

    **Rincian biaya**
    <x-mail::table>
        | Komponen | Jumlah |
        |:--|--:|
        | {{ $isRutin ? 'Biaya kiriman' : 'Biaya sewa' }} | {{ F::rupiah($p->harga_sewa) }} |
        @foreach ($biaya as $b)
        | {{ $b['nama'] }} | {{ F::rupiah($b['jumlah']) }} |
        @endforeach
        | **Total biaya** | **{{ F::rupiah($totalBiayaSewa) }}** |
    </x-mail::table>

    @if ($p->catatan_pengajuan)
    **Catatan pengaju:** {{ $p->catatan_pengajuan }}
    @endif
    
    @if ($riwayat->isNotEmpty())
    **Riwayat approval**
    <x-mail::table>
        | Tahap | Approver | Keputusan | Waktu |
        |:--|:--|:--|:--|
        @foreach ($riwayat as $log)
        | {{ $log->peran ?? '-' }} | {{ $log->approver?->name ?? '-' }} | {{ strtolower($log->status) === 'approved' ? ($log->peran === 'WM' ? 'Divalidasi' : 'Disetujui') : 'Ditolak' }}{{ $log->alasan_penolakan ? ' — ' . $log->alasan_penolakan : '' }} | {{ \Carbon\Carbon::parse($log->decided_at)->format('d/m/Y H:i') }} |
        @endforeach
    </x-mail::table>
    @endif

    @if ($dokumen)
    Daftar {{ $dokumen['tipe'] === 'TO-ACB' ? 'TO-ACB' : 'Surat Jalan' }} lengkap terlampir dalam file Excel.
    @endif

    <x-mail::button :url="$url" color="primary">
    Buka Detail Pengajuan
    </x-mail::button>
</x-mail::message>

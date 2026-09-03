@extends('layouts.app')

@section('title', 'Persetujuan Pengajuan Sewa - WM')

@section('content')
@php
    $totalBiayaTambahan = $pengajuan->biayaTambahan?->sum('jumlah') ?? 0;
    $totalBiayaSewa     = $pengajuan->harga_sewa + $totalBiayaTambahan;
    $ambang             = (float) $ambangRasio;
    $rasio              = (float) $pengajuan->rasio_sewa;
    $overAmbang         = $rasio > $ambang;
    $rasioPct           = min($rasio / $ambang, 1) * 100;
    $rejections         = $approvalLogs->filter(fn($log) => strtolower($log->status) === 'rejected');
@endphp

<div class="space-y-4 {{ $isPending ? 'pb-15' : 'pb-6' }}">

    {{-- ==================== HEADER: identitas + status + alur ==================== --}}
    <div class="rounded-xl bg-white shadow-sm border border-gray-100 p-5">
        <div class="flex items-start justify-between gap-5 flex-wrap">
            <div class="min-w-0">
                <h1 class="text-[22px] font-bold tracking-tight text-gray-900">
                    {{ $pengajuan->armada->perusahaan->badan_usaha }} {{ $pengajuan->armada->perusahaan->nama_perusahaan }}
                </h1>
                <p class="text-[13px] text-gray-500 mt-1">
                    Diajukan oleh <strong class="text-gray-700">{{ $pengajuan->submittedBy?->name ?? 'Unknown' }}</strong>
                    &middot; Cab. {{ $pengajuan->id_cabang }}
                    &middot; {{ \Carbon\Carbon::parse($pengajuan->submitted_at)->translatedFormat('d M Y') }}
                </p>
            </div>
            <x-status-badge-large :status="strtolower($pengajuan->status_pengajuan)" />
        </div>

        {{-- Alur persetujuan: pill --}}
        @if($pengajuan->kategori_approval === 'over_threshold')
            <div class="flex items-center gap-2.5 mt-3.5 flex-wrap">
                <span class="text-[11px] font-semibold tracking-[0.09em] text-gray-500">ALUR</span>
                <div class="flex items-center gap-2.5">
                    <div class="flex items-center gap-2 rounded-full bg-amber-50 border border-amber-200 py-1 pl-1.5 pr-3">
                        <span class="flex h-[19px] w-[19px] items-center justify-center rounded-full bg-amber-500 text-[11px] font-bold text-white">1</span>
                        <span class="text-[12.5px] font-semibold text-amber-800">WM &middot; Anda</span>
                    </div>
                    <span class="h-0.5 w-5 bg-gray-200"></span>
                    <div class="flex items-center gap-2 rounded-full border border-gray-200 py-1 pl-1.5 pr-3">
                        <span class="flex h-[19px] w-[19px] items-center justify-center rounded-full border border-dashed border-gray-300 text-[11px] font-bold text-gray-400">2</span>
                        <span class="text-[12.5px] text-gray-400">WH &middot; menunggu</span>
                    </div>
                </div>
            </div>
        @endif

        {{-- Verdict strip: rasio vs ambang, total, value muatan --}}
        <div class="flex items-stretch flex-wrap mt-5 border-t border-gray-100 pt-4">
            <div class="flex-1 min-w-[220px] basis-60 pr-6">
                <p class="text-[11px] font-semibold tracking-[0.09em] text-gray-500">RASIO SEWA</p>
                <div class="flex items-baseline gap-2.5 mt-1.5">
                    <span class="text-[34px] font-bold tracking-tight {{ $overAmbang ? 'text-red-600' : 'text-green-700' }}">
                        {{ number_format($rasio, 2, ',', '.') }}%
                    </span>
                    <span class="text-[13px] font-semibold {{ $overAmbang ? 'text-red-600' : 'text-green-700' }}">
                        {{ $overAmbang ? 'di atas ambang' : 'di bawah ambang' }}
                    </span>
                </div>
                <p class="text-xs text-gray-400 mt-0.5">
                    Sewa &divide; Value Muatan &middot; ambang {{ number_format($ambang, 2, ',', '.') }}%
                </p>
                <div class="relative h-1.5 rounded-full bg-gray-100 mt-3">
                    <div class="absolute inset-y-0 left-0 rounded-full {{ $overAmbang ? 'bg-red-500' : 'bg-green-600' }}"
                        style="width: {{ $rasioPct }}%"></div>
                    <div class="absolute -top-1.5 -bottom-1.5 right-0 w-0.5 bg-red-400"></div>
                </div>
                <div class="flex justify-between text-[11px] text-gray-400 mt-1.5">
                    <span>0%</span><span>{{ number_format($ambang, 2, ',', '.') }}%</span>
                </div>
            </div>

            <div class="w-px bg-gray-100"></div>

            <div class="flex-1 min-w-[160px] basis-44 px-6">
                <p class="text-[11px] font-semibold tracking-[0.09em] text-gray-500">TOTAL BIAYA SEWA</p>
                <p class="text-[22px] font-bold tracking-tight text-gray-900 mt-2">Rp {{ number_format($totalBiayaSewa, 0, ',', '.') }}</p>
                <p class="text-xs text-gray-400 mt-1">
                    {{ $totalBiayaTambahan > 0
                        ? 'Termasuk Rp ' . number_format($totalBiayaTambahan, 0, ',', '.') . ' biaya tambahan'
                        : 'Sewa armada, tanpa biaya tambahan' }}
                </p>
            </div>

            <div class="w-px bg-gray-100"></div>

            <div class="flex-1 min-w-[160px] basis-44 pl-6">
                <p class="text-[11px] font-semibold tracking-[0.09em] text-gray-500">VALUE MUATAN</p>
                <p class="text-[22px] font-bold tracking-tight text-gray-900 mt-2">Rp {{ number_format($pengajuan->value_muatan, 0, ',', '.') }}</p>
                <p class="text-xs text-gray-400 mt-1">
                    {{ $pengajuan->armada->jenis_kendaraan }} &middot; {{ \App\Helpers\FormatHelper::ton($pengajuan->armada->muatan_maksimal) }}
                </p>
            </div>
        </div>
    </div>

    {{-- ==================== SATU CARD, TIGA KOLOM ==================== --}}
    <div class="rounded-xl bg-white shadow-sm border border-gray-100 p-6
                grid grid-cols-[1.25fr_1px_1fr_1px_1fr] gap-x-6">

        {{-- Kolom 1: Informasi Armada --}}
        <div class="min-w-0">
            <p class="text-[11px] font-semibold tracking-[0.09em] text-gray-500 mb-1">INFORMASI ARMADA</p>

            <div class="flex justify-between gap-4 py-2.5 border-b border-gray-50">
                <span class="text-sm text-gray-500">Jenis Kendaraan</span>
                <span class="text-sm font-semibold text-gray-900">{{ $pengajuan->armada->jenis_kendaraan }}</span>
            </div>
            <div class="flex justify-between gap-4 py-2.5 border-b border-gray-50">
                <span class="text-sm text-gray-500">Plat Nomor</span>
                <span class="text-sm font-semibold text-gray-900 tabular-nums">{{ $pengajuan->armada->plat_nomor_truk }}</span>
            </div>
            <div class="flex items-center justify-between gap-4 py-2.5 border-b border-gray-50">
                <span class="text-sm text-gray-500">Skill / Area</span>
                <span class="flex flex-wrap justify-end gap-1.5">
                    @foreach(array_filter(array_map('trim', explode(',', $pengajuan->armada->id_skill ?? ''))) as $skill)
                        <span class="rounded-md bg-gray-100 px-2 py-0.5 text-xs font-semibold text-gray-700">{{ $skill }}</span>
                    @endforeach
                </span>
            </div>
            <div class="flex justify-between gap-4 py-2.5 border-b border-gray-50">
                <span class="text-sm text-gray-500">Kapasitas Muatan</span>
                <span class="text-sm font-semibold text-gray-900">{{ \App\Helpers\FormatHelper::ton($pengajuan->armada->muatan_maksimal) }}</span>
            </div>
            <div class="flex justify-between gap-4 py-2.5 border-b border-gray-50">
                <span class="text-sm text-gray-500">Badan Usaha &amp; Perusahaan</span>
                <span class="text-sm font-semibold text-gray-900 text-right">
                    {{ $pengajuan->armada->perusahaan->badan_usaha }} {{ $pengajuan->armada->perusahaan->nama_perusahaan }}
                </span>
            </div>
            <div class="flex justify-between gap-4 py-2.5 border-b border-gray-50">
                <span class="text-sm text-gray-500">No. Telepon</span>
                <a href="tel:{{ $pengajuan->armada->perusahaan->no_telepon }}" class="text-sm font-semibold text-green-800 hover:underline">
                    {{ $pengajuan->armada->perusahaan->no_telepon }}
                </a>
            </div>
            <div class="flex justify-between gap-4 py-2.5">
                <span class="text-sm text-gray-500">Alamat Kantor</span>
                <span class="text-sm font-semibold text-gray-900 text-right">{{ $pengajuan->armada->perusahaan->alamat_kantor }}</span>
            </div>
        </div>

        <div class="bg-gray-100"></div>

        {{-- Kolom 2: Detail Pengajuan --}}
        <div class="min-w-0">
            <p class="text-[11px] font-semibold tracking-[0.09em] text-gray-500 mb-1">DETAIL PENGAJUAN</p>

            <div class="flex justify-between gap-4 py-2.5 border-b border-gray-50">
                <span class="text-sm text-gray-500">Tanggal Pengiriman</span>
                <span class="text-sm font-semibold text-gray-900">{{ \Carbon\Carbon::parse($pengajuan->tanggal_pengiriman)->translatedFormat('d M Y') }}</span>
            </div>
            <div class="flex justify-between gap-4 py-2.5 border-b border-gray-50">
                <span class="text-sm text-gray-500">Tujuan Penyewaan</span>
                <span class="text-sm font-semibold text-gray-900">{{ $pengajuan->tujuan_penyewaan }}</span>
            </div>
            <div class="flex justify-between gap-4 py-2.5 border-b border-gray-50">
                <span class="text-sm text-gray-500">Kategori Toko</span>
                <span class="text-sm font-semibold text-gray-900">{{ $pengajuan->kategori_toko }}</span>
            </div>
            <div class="flex justify-between gap-4 py-2.5">
                <span class="text-sm text-gray-500">Cabang</span>
                <span class="text-sm font-semibold text-gray-900">{{ $pengajuan->id_cabang }}</span>
            </div>
        </div>

        <div class="bg-gray-100"></div>

        {{-- Kolom 3: Rincian Biaya --}}
        <div class="min-w-0">
            <p class="text-[11px] font-semibold tracking-[0.09em] text-gray-500 mb-1">RINCIAN BIAYA</p>

            <div class="flex justify-between gap-4 py-2.5 border-b border-gray-50">
                <span class="text-sm text-gray-500">Sewa Armada</span>
                <span class="text-sm font-semibold text-gray-900 tabular-nums">Rp {{ number_format($pengajuan->harga_sewa, 0, ',', '.') }}</span>
            </div>

            @forelse($pengajuan->biayaTambahan ?? [] as $biaya)
                <div class="flex justify-between gap-4 py-2.5 border-b border-gray-50">
                    <span class="text-sm text-gray-500">{{ $biaya->jenisBiaya->nama_biaya ?? '-' }}</span>
                    <span class="text-sm font-semibold text-gray-900 tabular-nums">Rp {{ number_format($biaya->jumlah, 0, ',', '.') }}</span>
                </div>
            @empty
                <div class="flex justify-between gap-4 py-2.5 border-b border-gray-50">
                    <span class="text-sm text-gray-500">Biaya Tambahan</span>
                    <span class="text-sm text-gray-400">Tidak ada</span>
                </div>
            @endforelse

            <div class="flex justify-between gap-4 pt-3 pb-1">
                <span class="text-sm font-bold text-gray-900">Total Biaya Sewa</span>
                <span class="text-base font-bold text-gray-900 tabular-nums">Rp {{ number_format($totalBiayaSewa, 0, ',', '.') }}</span>
            </div>
        </div>
    </div>

    {{-- ==================== DOKUMEN PENGEMUDI ==================== --}}
    <div class="rounded-xl bg-white shadow-sm border border-gray-100 p-6">
        <p class="text-[11px] font-semibold tracking-[0.09em] text-gray-500">DOKUMEN PENGEMUDI</p>
        <p class="text-[13px] text-gray-400 mt-1 mb-4">Klik untuk memperbesar dan memverifikasi</p>

        <div class="grid grid-cols-2 gap-4">
            @foreach([
                ['label' => 'Foto KTP Pengemudi', 'path' => $pengajuan->armada->ktp_supir, 'kosong' => 'Belum ada foto KTP'],
                ['label' => 'Foto SIM Pengemudi', 'path' => $pengajuan->armada->sim_supir, 'kosong' => 'Belum ada foto SIM'],
            ] as $dok)
                <div>
                    <p class="text-[13px] font-semibold text-gray-700 mb-2">{{ $dok['label'] }}</p>
                    @if($dok['path'])
                        {{-- object-contain --}}
                        <div class="doc-zoom relative flex h-52 cursor-zoom-in items-center justify-center overflow-hidden rounded-lg border border-gray-200 bg-gray-50"
                            data-src="{{ asset($dok['path']) }}" data-label="{{ $dok['label'] }}">
                            <img src="{{ asset($dok['path']) }}" alt="{{ $dok['label'] }}" class="h-full w-full object-cover">
                            <span class="absolute bottom-2 right-2 flex items-center gap-1.5 rounded-md bg-black/70 px-2 py-1 text-[11px] font-semibold text-white">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0zM10 7v6m-3-3h6" />
                                </svg>
                                Perbesar
                            </span>
                        </div>
                    @else
                        <div class="flex h-52 flex-col items-center justify-center gap-2 rounded-lg border-2 border-dashed border-gray-200 bg-gray-50">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-7 w-7 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0" />
                            </svg>
                            <p class="text-[13px] text-gray-400">{{ $dok['kosong'] }}</p>
                            <p class="text-xs text-gray-300">Wajib diverifikasi sebelum disetujui</p>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    </div>

    {{-- ==================== DOKUMEN SJ/TO-ACB ==================== --}}
    <div class="rounded-xl bg-white shadow-sm border border-gray-100 p-6">
        <p class="text-[11px] font-semibold tracking-[0.09em] text-gray-500">
            DOKUMEN
            @if($pengajuan->tujuan_penyewaan === 'Toko')
                SJ
            @elseif($pengajuan->tujuan_penyewaan === 'PAC')
                TO-ACB
            @else
                Dokumen tidak tersedia untuk tujuan penyewaan ini.
            @endif
        </p>

        <div class="overflow-x-auto rounded-lg border border-gray-100">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-100 bg-gray-50 text-[11px] font-semibold tracking-wide text-gray-500">
                        <th class="px-3.5 py-2.5 text-left">NO DOKUMEN</th>
                        <th class="px-3.5 py-2.5 text-right">VALUE MUATAN</th>
                        <th class="px-3.5 py-2.5 text-right">QTY</th>
                        <th class="px-3.5 py-2.5 text-right">TOTAL WEIGHT</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($dokumenSj ?? [] as $d)
                        <tr class="border-b border-gray-50 hover:bg-gray-50">
                            <td class="px-3.5 py-3 text-gray-800">
                                {{ $d['no_dokumen'] ?? '-' }}
                            </td>
                            <td class="px-3.5 py-3 text-right tabular-nums text-gray-700">
                                {{ $d['value_muatan'] ? 'Rp ' . number_format($d['value_muatan'], 0, ',', '.') : '-' }}
                            </td>
                            <td class="px-3.5 py-3 text-right tabular-nums text-gray-700">
                                {{ $d['qty'] ?? '-' }}
                            </td>
                            <td class="px-3.5 py-3 text-right tabular-nums text-gray-700">
                                {{ $d['total_weight'] ? \App\Helpers\FormatHelper::ton($d['total_weight']) : '-' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-3.5 py-4 text-center text-[13px] text-gray-400">
                                Belum ada dokumen SJ/TO-ACB terlampir.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- ==================== PERBANDINGAN VENDOR ==================== --}}
    <div class="rounded-xl bg-white shadow-sm border border-gray-100 p-6">
        <p class="text-[11px] font-semibold tracking-[0.09em] text-gray-500">PERBANDINGAN VENDOR</p>
        <p class="text-[13px] text-gray-400 mt-1 mb-3.5">Armada lain dengan skill {{ $pengajuan->armada->id_skill }}</p>

        <div class="overflow-x-auto rounded-lg border border-gray-100">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-100 bg-gray-50 text-[11px] font-semibold tracking-wide text-gray-500">
                        <th class="px-3.5 py-2.5 text-left">NAMA VENDOR</th>
                        <th class="px-3.5 py-2.5 text-right">HARGA SEWA</th>
                        <th class="px-3.5 py-2.5 text-right">SELISIH</th>
                        <th class="px-3.5 py-2.5 text-right">MUATAN</th>
                        <th class="px-3.5 py-2.5 text-left">BADAN USAHA</th>
                    </tr>
                </thead>
                <tbody>
                    {{-- Baseline: pengajuan yang sedang dinilai --}}
                    <tr class="border-b border-gray-100 bg-green-50/60">
                        <td class="px-3.5 py-3">
                            <span class="flex items-center gap-2 font-bold text-gray-900">
                                {{ $pengajuan->armada->perusahaan->badan_usaha }} {{ $pengajuan->armada->perusahaan->nama_perusahaan }}
                                <span class="rounded bg-avian-green px-1.5 py-0.5 text-[10.5px] font-bold tracking-wide text-white">PENGAJUAN INI</span>
                            </span>
                        </td>
                        <td class="px-3.5 py-3 text-right font-bold text-gray-900 tabular-nums">Rp {{ number_format($pengajuan->harga_sewa, 0, ',', '.') }}</td>
                        <td class="px-3.5 py-3 text-right text-gray-400">&mdash;</td>
                        <td class="px-3.5 py-3 text-right">{{ \App\Helpers\FormatHelper::ton($pengajuan->armada->muatan_maksimal) }}</td>
                        <td class="px-3.5 py-3">{{ $pengajuan->armada->perusahaan->badan_usaha }}</td>
                    </tr>

                    @forelse($vendorLain ?? [] as $v)
                        @php
                            $selisih = $v['harga_sewa'] ? $v['harga_sewa'] - $pengajuan->harga_sewa : null;
                        @endphp
                        <tr class="border-b border-gray-50 hover:bg-gray-50">
                            <td class="px-3.5 py-3 text-gray-800">{{ $v['nama_vendor'] }}</td>
                            <td class="px-3.5 py-3 text-right tabular-nums text-gray-700">
                                @if($v['harga_sewa'])
                                    Rp {{ number_format($v['harga_sewa'], 0, ',', '.') }}
                                @else
                                    <span class="italic text-gray-400">Belum ada riwayat</span>
                                @endif
                            </td>
                            <td class="px-3.5 py-3 text-right tabular-nums text-[13px] font-semibold
                                        {{ is_null($selisih) ? 'text-gray-400' : ($selisih < 0 ? 'text-green-700' : 'text-red-600') }}">
                                @if(is_null($selisih) || $selisih == 0)
                                    &mdash;
                                @else
                                    {{ $selisih < 0 ? '−' : '+' }}Rp {{ number_format(abs($selisih), 0, ',', '.') }}
                                @endif
                            </td>
                            <td class="px-3.5 py-3 text-right text-gray-700">{{ $v['muatan'] }}</td>
                            <td class="px-3.5 py-3 text-gray-700">{{ $v['badan_usaha'] ?? '-' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-3.5 py-4 text-center text-[13px] text-gray-400">
                                Tidak ada vendor lain dengan skill yang sama.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- ==================== RIWAYAT VENDOR ==================== --}}
    <div class="rounded-xl bg-white shadow-sm border border-gray-100 p-6">
        <p class="text-[11px] font-semibold tracking-[0.09em] text-gray-500">
            RIWAYAT {{ strtoupper($pengajuan->armada->perusahaan->badan_usaha . ' ' . $pengajuan->armada->perusahaan->nama_perusahaan) }}
        </p>
        <p class="text-[13px] text-gray-400 mt-1 mb-3.5">
            {{ $pengajuan->armada->jenis_kendaraan }} &middot; {{ $pengajuan->armada->plat_nomor_truk }}
        </p>

        <div class="overflow-x-auto rounded-lg border border-gray-100">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-100 bg-gray-50 text-[11px] font-semibold tracking-wide text-gray-500">
                        <th class="px-3.5 py-2.5 text-left">TANGGAL</th>
                        <th class="px-3.5 py-2.5 text-right">VALUE MUATAN</th>
                        <th class="px-3.5 py-2.5 text-right">HARGA SEWA</th>
                        <th class="px-3.5 py-2.5 text-right">RASIO</th>
                        <th class="px-3.5 py-2.5 text-left">STATUS</th>
                        <th class="px-3.5 py-2.5 text-left">APPROVER</th>
                        <th class="px-3.5 py-2.5 text-left">CATATAN</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($historyVendor ?? [] as $h)
                        @php
                            $st      = strtolower($h->status_pengajuan ?? '');
                            $catatan = $h->approvalLogs?->first(fn($log) => strtolower($log->status) === 'rejected')?->alasan_penolakan;
                        @endphp
                        <tr class="border-b border-gray-50 hover:bg-gray-50">
                            <td class="px-3.5 py-3 tabular-nums text-gray-800">
                                {{ $h->submitted_at ? \Carbon\Carbon::parse($h->submitted_at)->format('d/m/Y') : '-' }}
                            </td>
                            <td class="px-3.5 py-3 text-right tabular-nums text-gray-700">
                                {{ $h->value_muatan ? 'Rp ' . number_format($h->value_muatan, 0, ',', '.') : '-' }}
                            </td>
                            <td class="px-3.5 py-3 text-right tabular-nums text-gray-700">
                                {{ $h->harga_sewa ? 'Rp ' . number_format($h->harga_sewa, 0, ',', '.') : '-' }}
                            </td>
                            <td class="px-3.5 py-3 text-right tabular-nums font-semibold
                                        {{ $h->rasio_sewa > $ambang ? 'text-red-600' : 'text-gray-700' }}">
                                {{ $h->rasio_sewa ? number_format($h->rasio_sewa, 2, ',', '.') . '%' : '-' }}
                            </td>
                            <td class="px-3.5 py-3">
                                @if(in_array($st, ['approved', 'disetujui']))
                                    <span class="rounded-full bg-green-100 px-2.5 py-0.5 text-[11.5px] font-semibold text-green-700">Approve</span>
                                @elseif(in_array($st, ['rejected', 'ditolak']))
                                    <span class="rounded-full bg-red-100 px-2.5 py-0.5 text-[11.5px] font-semibold text-red-600">Reject</span>
                                @else
                                    <span class="rounded-full bg-gray-100 px-2.5 py-0.5 text-[11.5px] font-semibold text-gray-500">{{ ucfirst($h->status_pengajuan ?? '-') }}</span>
                                @endif
                            </td>
                            <td class="px-3.5 py-3 text-gray-700">{{ $h->approvalLogs?->first()?->approver?->name ?? '-' }}</td>
                            {{-- Catatan dipotong: kolom bebas jangan sampai melebarkan baris --}}
                            <td class="px-3.5 py-3 max-w-[220px] truncate text-[13.5px] text-gray-600" title="{{ $catatan }}">
                                {{ $catatan ?: '—' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-3.5 py-4 text-center text-[13px] text-gray-400">
                                Belum ada riwayat pengajuan untuk armada ini.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- ==================== RIWAYAT PENOLAKAN ==================== --}}
    @if($rejections->count() > 0)
        <div class="rounded-xl bg-white shadow-sm border border-gray-100 p-6">
            <p class="text-[11px] font-semibold tracking-[0.09em] text-gray-500 mb-4">RIWAYAT PENOLAKAN</p>
            <div class="space-y-3">
                @foreach($rejections as $rejection)
                    <div class="rounded-lg border border-red-100 bg-red-50 p-4">
                        <div class="flex items-start justify-between gap-4">
                            <p class="text-sm font-semibold text-red-900">Penolakan oleh {{ $rejection->approver?->name ?? 'Unknown' }}</p>
                            <span class="text-xs text-red-600 shrink-0">{{ \Carbon\Carbon::parse($rejection->decided_at)->translatedFormat('d M Y, H:i') }}</span>
                        </div>
                        @if($rejection->alasan_penolakan)
                            <p class="text-sm text-red-700 mt-2">{{ $rejection->alasan_penolakan }}</p>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- ==================== STICKY ACTION BAR ==================== --}}
    @if($isPending)
    <div class="fixed bottom-0 left-16 right-0 z-30 flex flex-wrap items-center justify-between gap-5
            border-t border-gray-200 bg-white/95 px-6 py-3.5 backdrop-blur">
        <div class="flex min-w-0 items-center gap-5">
            <div>
                <p class="text-[11px] font-semibold tracking-wide text-gray-500">
                    {{ strtoupper($pengajuan->armada->perusahaan->badan_usaha . ' ' . $pengajuan->armada->perusahaan->nama_perusahaan) }}
                    &middot; {{ $pengajuan->armada->plat_nomor_truk }}
                </p>
                <p class="text-[15px] font-bold text-gray-900 mt-0.5">
                    Rp {{ number_format($totalBiayaSewa, 0, ',', '.') }}
                    <span class="font-normal text-gray-300">&middot;</span>
                    <span class="{{ $overAmbang ? 'text-red-600' : 'text-green-700' }}">Rasio {{ number_format($rasio, 2, ',', '.') }}%</span>
                </p>
            </div>
            <span class="hidden items-center gap-2 rounded-lg border border-amber-200 bg-amber-50 px-3 py-1.5 text-[13px] font-semibold text-amber-800 lg:flex">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                Menunggu persetujuan Anda
            </span>
        </div>

        <div class="flex shrink-0 gap-2.5">
            <button type="button" id="reject-btn"
                class="flex items-center gap-2 rounded-lg border border-red-200 bg-white px-5 py-2.5 text-sm font-semibold text-red-600 transition hover:bg-red-50">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                </svg>
                Tolak
            </button>
            <button type="button" id="approve-btn"
                class="flex items-center gap-2 rounded-lg bg-avian-green px-5 py-2.5 text-sm font-semibold text-white transition hover:brightness-90">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                </svg>
                Setujui
            </button>
        </div>
    </div>
</div>

<form id="approval-form" class="hidden">@csrf</form>

{{-- ==================== MODAL: KONFIRMASI SETUJUI ==================== --}}
<div id="modal-approve" class="fixed inset-0 z-50 hidden items-center justify-center p-6">
    <div class="modal-backdrop absolute inset-0 bg-black/45"></div>
    <div class="relative z-10 w-full max-w-md rounded-2xl bg-white p-6 shadow-xl">
        <h3 class="text-[17px] font-bold text-gray-900">Setujui pengajuan ini?</h3>
        <p class="text-[13.5px] text-gray-500 mt-1.5">
            @if($pengajuan->kategori_approval === 'over_threshold')
                Persetujuan Anda meneruskan pengajuan ke WH (Tier 2).
            @else
                Persetujuan Anda menyelesaikan pengajuan ini.
            @endif
        </p>

        <div class="mt-4 overflow-hidden rounded-xl border border-gray-100">
            <div class="flex justify-between border-b border-gray-50 px-3.5 py-2.5">
                <span class="text-[13.5px] text-gray-500">Vendor</span>
                <span class="text-[13.5px] font-semibold text-gray-900">
                    {{ $pengajuan->armada->perusahaan->badan_usaha }} {{ $pengajuan->armada->perusahaan->nama_perusahaan }}
                </span>
            </div>
            <div class="flex justify-between border-b border-gray-50 px-3.5 py-2.5">
                <span class="text-[13.5px] text-gray-500">Total biaya sewa</span>
                <span class="text-[13.5px] font-semibold text-gray-900">Rp {{ number_format($totalBiayaSewa, 0, ',', '.') }}</span>
            </div>
            <div class="flex justify-between px-3.5 py-2.5">
                <span class="text-[13.5px] text-gray-500">Rasio sewa</span>
                <span class="text-[13.5px] font-semibold {{ $overAmbang ? 'text-red-600' : 'text-green-700' }}">
                    {{ number_format($rasio, 2, ',', '.') }}% ({{ $overAmbang ? 'di atas ambang' : 'di bawah ambang' }})
                </span>
            </div>
        </div>

        <div class="mt-5 flex justify-end gap-2.5">
            <button type="button" class="modal-cancel rounded-lg border border-gray-200 bg-white px-5 py-2.5 text-sm text-gray-600 transition hover:bg-gray-50">Batal</button>
            <button type="button" id="confirm-approve"
                class="rounded-lg bg-avian-green px-5 py-2.5 text-sm font-semibold text-white transition hover:brightness-90 disabled:opacity-60">
                Ya, setujui
            </button>
        </div>
    </div>
</div>

{{-- ==================== MODAL: TOLAK ==================== --}}
<div id="modal-reject" class="fixed inset-0 z-50 hidden items-center justify-center p-6">
    <div class="modal-backdrop absolute inset-0 bg-black/45"></div>
    <div class="relative z-10 w-full max-w-md rounded-2xl bg-white p-6 shadow-xl">
        <h3 class="text-[17px] font-bold text-gray-900">Alasan penolakan</h3>
        <p class="text-[13.5px] text-gray-500 mt-1.5">
            Alasan akan dikirimkan ke pengaju, {{ $pengajuan->submittedBy?->name ?? 'pengaju' }}.
        </p>

        <textarea id="rejection-reason-input" rows="4"
            placeholder="Contoh: Harga sewa terlalu tinggi dibanding vendor lain..."
            class="mt-3.5 w-full resize-none rounded-lg border border-gray-300 px-3.5 py-3 text-sm text-gray-800
                   focus:border-red-400 focus:outline-none focus:ring-1 focus:ring-red-400"></textarea>
        <p id="rejection-error" class="mt-1 hidden text-xs text-red-500">Alasan penolakan tidak boleh kosong.</p>

        {{-- Alasan cepat: isi textarea, tetap bisa diedit --}}
        <div class="mt-2.5 flex flex-wrap gap-2">
            @foreach(['Harga di atas vendor lain', 'Dokumen tidak lengkap', 'Rasio terlalu tinggi'] as $preset)
                <button type="button" class="reason-preset rounded-md bg-gray-100 px-2.5 py-1.5 text-xs font-semibold text-gray-600 transition hover:bg-gray-200">
                    {{ $preset }}
                </button>
            @endforeach
        </div>

        <div class="mt-5 flex justify-end gap-2.5">
            <button type="button" class="modal-cancel rounded-lg border border-gray-200 bg-white px-5 py-2.5 text-sm text-gray-600 transition hover:bg-gray-50">Batal</button>
            <button type="button" id="confirm-reject"
                class="rounded-lg bg-red-600 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-red-700 disabled:opacity-60">
                Konfirmasi tolak
            </button>
        </div>
    </div>
</div>

{{-- ==================== LIGHTBOX DOKUMEN ==================== --}}
<div id="doc-lightbox" class="fixed inset-0 z-[60] hidden cursor-zoom-out items-center justify-center bg-black/80 p-10">
    {{-- Tombol X --}}
    <button id="lightbox-close"
        class="absolute top-4 right-4 flex h-9 w-9 items-center justify-center rounded-full bg-white/15 text-white transition hover:bg-white/30 cursor-pointer z-10"
        onclick="event.stopPropagation()">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
        </svg>
    </button>
    <img id="doc-lightbox-img" src="" alt="" class="max-h-full max-w-full rounded-lg object-contain">
</div>
@endif

<script>
(function () {
    const csrf = document.querySelector('#approval-form [name="_token"]').value;

    // ---------- modal helpers ----------
    const show = el => { el.classList.remove('hidden'); el.classList.add('flex'); };
    const hide = el => { el.classList.add('hidden'); el.classList.remove('flex'); };

    const modalApprove = document.getElementById('modal-approve');
    const modalReject  = document.getElementById('modal-reject');
    const lightbox     = document.getElementById('doc-lightbox');
    const textarea     = document.getElementById('rejection-reason-input');
    const errorMsg     = document.getElementById('rejection-error');

    document.getElementById('approve-btn').addEventListener('click', () => show(modalApprove));
    document.getElementById('reject-btn').addEventListener('click', () => {
        textarea.value = '';
        errorMsg.classList.add('hidden');
        show(modalReject);
        setTimeout(() => textarea.focus(), 80);
    });

    document.querySelectorAll('.modal-cancel, .modal-backdrop').forEach(el =>
        el.addEventListener('click', () => { hide(modalApprove); hide(modalReject); })
    );
    document.addEventListener('keydown', e => {
        if (e.key === 'Escape') { hide(modalApprove); hide(modalReject); hide(lightbox); }
    });

    document.querySelectorAll('.reason-preset').forEach(btn =>
        btn.addEventListener('click', () => {
            textarea.value = btn.textContent.trim();
            errorMsg.classList.add('hidden');
            textarea.focus();
        })
    );

    // ---------- lightbox dokumen ----------
    document.querySelectorAll('.doc-zoom').forEach(box =>
        box.addEventListener('click', () => {
            const img = document.getElementById('doc-lightbox-img');
            img.src = box.dataset.src;
            img.alt = box.dataset.label;
            show(lightbox);
        })
    );
    lightbox.addEventListener('click', () => hide(lightbox));
    document.getElementById('lightbox-close').addEventListener('click', () => hide(lightbox));

    // ---------- submit ----------
    let busy = false;

    function send(url, payload, btn, busyLabel) {
        if (busy) return;
        busy = true;
        const original = btn.textContent;
        btn.disabled = true;
        btn.textContent = busyLabel;

        fetch(url, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrf, 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify(payload),
        })
        .then(res => res.json())
        .then(data => {
            if (data.error) throw new Error(data.error);
            window.location.href = '{{ route("dashboard") }}';
        })
        .catch(err => {
            busy = false;
            btn.disabled = false;
            btn.textContent = original;
            alert('Terjadi kesalahan: ' + err.message);
        });
    }

    document.getElementById('confirm-approve').addEventListener('click', function () {
        send('{{ route("approval.approve", $pengajuan->id_pengajuan_sewa) }}', {}, this, 'Memproses…');
    });

    document.getElementById('confirm-reject').addEventListener('click', function () {
        const reason = textarea.value.trim();
        if (!reason) {
            errorMsg.classList.remove('hidden');
            textarea.focus();
            return;
        }
        errorMsg.classList.add('hidden');
        send('{{ route("approval.reject", $pengajuan->id_pengajuan_sewa) }}', { alasan_penolakan: reason }, this, 'Memproses…');
    });
})();
</script>
@endsection
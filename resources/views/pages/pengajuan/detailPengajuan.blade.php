@extends('layouts.app')

@section('title', 'Detail Pengajuan Sewa')

@php
    $totalBiayaTambahan = $pengajuan->biayaTambahan?->sum('jumlah') ?? 0;
    $totalBiayaSewa     = $pengajuan->harga_sewa + $totalBiayaTambahan;
    $ambang             = (float) $ambangRasio;
    $rasio              = (float) $pengajuan->rasio_sewa;
    $overAmbang         = $rasio > $ambang;
    $rasioPct           = min($rasio / $ambang, 1) * 100;
@endphp

@section('content')
<div class="space-y-4 pb-6">

    {{-- ==================== HEADER: identitas + status + alur ==================== --}}
    <div class="rounded-xl bg-white shadow-sm border border-gray-100 p-5">
        <div class="flex items-start justify-between gap-5 flex-wrap">
            <div class="min-w-0">
                <h1 class="text-[22px] font-bold tracking-tight text-gray-900">
                    {{ $pengajuan->armada->perusahaan->badan_usaha }} {{ $pengajuan->armada->perusahaan->nama_perusahaan }}
                </h1>
                <p class="text-[13px] text-gray-500 mt-1">
                    Diajukan pada {{ \Carbon\Carbon::parse($pengajuan->submitted_at)->translatedFormat('d M Y') }}
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
                        <span class="text-[12.5px] font-semibold text-amber-800">WM &middot; Menunggu</span>
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

    {{-- ==================== KATEGORI APPROVAL ALERT ==================== --}}
    @if($pengajuan->kategori_approval === 'over_threshold')
        <x-alert-info title="Perlu Persetujuan Berlapis">
            @php
                $reasons = [];
                if($pengajuan->rasio_sewa > $ambangRasio) {
                    $reasons[] = "Rasio Sewa " . number_format($pengajuan->rasio_sewa, 2, ',', '.') . "% melebihi threshold " . number_format($ambangRasio, 2, ',', '.') . "%";
                }
                if($pengajuan->tujuan_penyewaan === 'PAC') {
                    $reasons[] = "Tujuan penyewaan PAC";
                }
                $message = "Alasan: " . implode(", ", $reasons) . " — pengajuan ini akan diteruskan ke WH setelah disetujui WM.";
            @endphp
            <p class="text-sm text-orange-800">{{ $message }}</p>
        </x-alert-info>
    @endif
    
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

    {{-- ==================== CATATAN PENGAJUAN ==================== --}}
    @if($pengajuan->catatan_pengajuan)
    <div class="rounded-xl bg-white shadow-sm border border-gray-100 p-6">
        <p class="text-xs font-semibold uppercase tracking-widest text-gray-400 mb-3">Catatan</p>
        <p class="text-sm text-gray-700 whitespace-pre-wrap leading-relaxed">{{ $pengajuan->catatan_pengajuan }}</p>
    </div>
    @endif

    {{-- ==================== DOKUMEN PENGEMUDI ==================== --}}
    <div class="rounded-xl bg-white shadow-sm border border-gray-100 p-6">
        <p class="text-[11px] font-semibold tracking-[0.09em] text-gray-500">DOKUMEN PENGEMUDI</p>
        <p class="text-[13px] text-gray-400 mt-1 mb-4">Klik untuk memperbesar dan melihat</p>

        <div class="grid grid-cols-2 gap-4">
            @foreach([
                ['label' => 'Foto KTP Pengemudi', 'path' => $pengajuan->armada->ktp_supir, 'kosong' => 'Belum ada foto KTP'],
                ['label' => 'Foto SIM Pengemudi', 'path' => $pengajuan->armada->sim_supir, 'kosong' => 'Belum ada foto SIM'],
            ] as $dok)
                <div>
                    <p class="text-[13px] font-semibold text-gray-700 mb-2">{{ $dok['label'] }}</p>
                    @if($dok['path'])
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
                            <p class="text-xs text-gray-300">Foto yang jelas akan membantu proses verifikasi</p>
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

        <div class="overflow-x-auto rounded-lg border border-gray-100 mt-4">
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
                    @forelse($pengajuan->suratJalan ?? [] as $sj)
                        <tr class="border-b border-gray-50 hover:bg-gray-50">
                            <td class="px-3.5 py-3 text-gray-800">
                                {{ $sj->no_sj ?? '-' }}
                            </td>
                            <td class="px-3.5 py-3 text-right tabular-nums text-gray-700">
                                {{ $sj->nilai ? 'Rp ' . number_format($sj->nilai, 0, ',', '.') : '-' }}
                            </td>
                            <td class="px-3.5 py-3 text-right tabular-nums text-gray-700">
                                {{ $sj->qty ?? '-' }}
                            </td>
                            <td class="px-3.5 py-3 text-right tabular-nums text-gray-700">
                                {{ $sj->total_weight ? \App\Helpers\FormatHelper::ton($sj->total_weight) : '-' }}
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

    {{-- ==================== TIMELINE RIWAYAT PERSETUJUAN ==================== --}}
    <div class="rounded-xl bg-white shadow-sm border border-gray-100 p-6">
        <p class="text-[11px] font-semibold tracking-[0.09em] text-gray-500 mb-6">RIWAYAT PERSETUJUAN</p>

        @if(isset($timeline) && $timeline->count() > 0)
        <div class="relative">
            {{-- Vertical line --}}
            <div class="absolute left-4 top-2 bottom-2 w-px bg-gray-200"></div>
            <div class="space-y-6">
                @foreach($timeline as $entry)
                @if($entry['type'] === 'resubmit')
                    {{-- Synthetic resubmit entry --}}
                    <div class="relative flex gap-4 pl-10">
                        <div class="absolute left-2.5 top-1 h-3 w-3 rounded-full border-2 bg-blue-400 border-blue-400 -translate-x-1/2"></div>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center justify-between">
                                <p class="text-sm font-medium text-gray-800">{{ (isset($entry['aktor']) && $entry['aktor']) ? $entry['aktor']->name : 'Unknown' }}</p>
                                <span class="text-xs text-gray-400">{{ \Carbon\Carbon::parse($entry['decided_at'] ?? now())->translatedFormat('d M Y, H:i') }}</span>
                            </div>
                            <p class="text-xs text-gray-500 mt-0.5">Direvisi & Diajukan Ulang</p>
                        </div>
                    </div>
                @else
                    {{-- Approval log entry --}}
                    @php
                        $logStatus = strtolower($entry['status'] ?? '');
                        $dotColor = match($logStatus) {
                            'approved' => 'bg-avian-green border-avian-green',
                            'rejected' => 'bg-red-400 border-red-400',
                            default    => 'bg-white border-gray-300',
                        };
                        $logLabel = match($logStatus) {
                            'approved' => 'Disetujui',
                            'rejected' => 'Ditolak',
                            default    => 'Menunggu',
                        };
                        $approverName = (isset($entry['approver']) && $entry['approver']) ? $entry['approver']->name : 'Unknown';
                    @endphp
                    <div class="relative flex gap-4 pl-10">
                        <div class="absolute left-2.5 top-1 h-3 w-3 rounded-full border-2 {{ $dotColor }} -translate-x-1/2"></div>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center justify-between">
                                <p class="text-sm font-medium text-gray-800">{{ $approverName }}</p>
                                <span class="text-xs text-gray-400">{{ \Carbon\Carbon::parse($entry['decided_at'] ?? now())->translatedFormat('d M Y, H:i') }}</span>
                            </div>
                            <p class="text-xs text-gray-500 mt-0.5">{{ $logLabel }}</p>
                            @if($entry['reason'] ?? false)
                            <p class="mt-2 text-xs text-red-600 bg-red-50 rounded-lg px-3 py-2">{{ $entry['reason'] }}</p>
                            @endif
                        </div>
                    </div>
                @endif
                @endforeach
            </div>
        </div>
        @else
        <div class="flex flex-col items-center justify-center py-8 text-center">
            <div class="flex h-10 w-10 items-center justify-center rounded-full bg-gray-100 mb-3">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </div>
            <p class="text-sm text-gray-500">Belum ada aktivitas persetujuan</p>
            <p class="text-xs text-gray-400 mt-1">Riwayat akan muncul setelah WM mulai memproses pengajuan ini</p>
        </div>
        @endif
    </div>

</div>

{{-- Floating Edit Button --}}
@if(auth()->id() == $pengajuan->submitted_by && in_array(strtolower($pengajuan->status_pengajuan), ['pending', 'rejected']))
<div class="fixed bottom-6 right-6">
    <a href="{{ route('pengajuan.edit', $pengajuan->id_pengajuan_sewa) }}"
        class="group relative flex h-14 w-14 items-center justify-center rounded-full bg-avian-green text-white shadow-lg hover:shadow-xl transition-all hover:scale-105"
        title="Edit Pengajuan">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
        </svg>
        <span class="absolute right-full mr-3 whitespace-nowrap rounded bg-gray-900 px-3 py-1.5 text-xs text-white opacity-0 transition group-hover:opacity-100 pointer-events-none">
            Edit Pengajuan
        </span>
    </a>
</div>
@endif

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

<script>
(function () {
    // ---------- lightbox dokumen ----------
    document.querySelectorAll('.doc-zoom').forEach(box =>
        box.addEventListener('click', () => {
            const lightbox = document.getElementById('doc-lightbox');
            const img = document.getElementById('doc-lightbox-img');
            img.src = box.dataset.src;
            img.alt = box.dataset.label;
            lightbox.classList.remove('hidden');
            lightbox.classList.add('flex');
        })
    );

    const lightbox = document.getElementById('doc-lightbox');
    lightbox.addEventListener('click', () => {
        lightbox.classList.add('hidden');
        lightbox.classList.remove('flex');
    });

    document.getElementById('lightbox-close').addEventListener('click', () => {
        lightbox.classList.add('hidden');
        lightbox.classList.remove('flex');
    });

    // Tutup lightbox saat tekan Escape
    document.addEventListener('keydown', e => {
        if (e.key === 'Escape') {
            lightbox.classList.add('hidden');
            lightbox.classList.remove('flex');
        }
    });
})();
</script>

@endsection

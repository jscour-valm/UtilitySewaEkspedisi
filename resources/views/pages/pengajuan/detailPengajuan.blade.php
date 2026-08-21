@extends('layouts.app')

@section('title', 'Detail Pengajuan Sewa')

@section('content')
<div class="space-y-5">

    {{-- Hero Status Bar --}}
    @php
        $statusKey = strtolower($pengajuan->status_pengajuan ?? '');
        $statusConfig = [
            'pending'  => ['bg' => 'bg-blue-50',  'border' => 'border-blue-200',  'dot' => 'bg-blue-400',  'text' => 'text-blue-700',  'label' => 'Menunggu Persetujuan'],
            'approved' => ['bg' => 'bg-green-50', 'border' => 'border-green-200', 'dot' => 'bg-avian-green','text' => 'text-avian-green','label' => 'Disetujui'],
            'rejected' => ['bg' => 'bg-red-50',   'border' => 'border-red-200',   'dot' => 'bg-red-400',   'text' => 'text-red-600',   'label' => 'Ditolak'],
        ];
        $sc = $statusConfig[$statusKey] ?? ['bg' => 'bg-gray-50', 'border' => 'border-gray-200', 'dot' => 'bg-gray-400', 'text' => 'text-gray-600', 'label' => $pengajuan->status_pengajuan];
    @endphp

    <div class="rounded-xl bg-white shadow-sm border border-gray-100 overflow-hidden">
        {{-- Accent strip --}}
        <div class="h-1 w-full
            @if($statusKey === 'approved') bg-avian-green
            @elseif($statusKey === 'rejected') bg-red-400
            @else bg-blue-400
            @endif">
        </div>
        <div class="px-6 py-5 flex items-center justify-between">
            <div class="flex items-center gap-4">
                <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-gray-100">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-3-3v6m-7 4h14a2 2 0 002-2V7l-5-5H5a2 2 0 00-2 2v14a2 2 0 002 2z" />
                    </svg>
                </div>
                <div>
                    <h1 class="text-base font-semibold text-gray-900">{{ $pengajuan->armada->perusahaan->nama_perusahaan }}</h1>
                    <p class="text-xs text-gray-400 mt-0.5">Diajukan pada {{ \Carbon\Carbon::parse($pengajuan->submitted_at)->translatedFormat('d M Y') }} &middot; Pukul {{ \Carbon\Carbon::parse($pengajuan->submitted_at)->translatedFormat('H:i') }}</p>
                </div>
            </div>
            <div class="flex items-center gap-2 rounded-full px-4 py-1.5 {{ $sc['bg'] }} {{ $sc['border'] }} border">
                <span class="h-2 w-2 rounded-full {{ $sc['dot'] }}"></span>
                <span class="text-sm font-medium {{ $sc['text'] }}">{{ $sc['label'] }}</span>
            </div>
        </div>
    </div>

    {{-- 2 kolom: Armada + Detail Pengajuan --}}
    <div class="grid grid-cols-2 gap-5">

        {{-- Armada Info --}}
        <div class="rounded-xl bg-white shadow-sm border border-gray-100 p-6">
            <p class="text-xs font-semibold uppercase tracking-widest text-gray-400 mb-4">Informasi Armada</p>
            <div class="space-y-4">
                <div class="flex justify-between items-start">
                    <span class="text-sm text-gray-500">Jenis Kendaraan</span>
                    <span class="text-sm font-medium text-gray-800 text-right">{{ $pengajuan->armada->nama_kendaraan }}</span>
                </div>
                <div class="flex justify-between items-start">
                    <span class="text-sm text-gray-500">Plat Nomor</span>
                    <span class="text-sm font-mono font-medium text-gray-800">{{ $pengajuan->armada->plat_nomor }}</span>
                </div>
                <div class="flex justify-between items-start">
                    <span class="text-sm text-gray-500">Skill / Area</span>
                    <span class="text-sm font-medium text-gray-800 text-right">{{ $pengajuan->armada->id_skill }}</span>
                </div>
                <div class="flex justify-between items-start">
                    <span class="text-sm text-gray-500">Kapasitas Muatan</span>
                    <span class="text-sm font-medium text-gray-800">{{ (int)$pengajuan->armada->muatan_maksimal }} Ton</span>
                </div>
            </div>
        </div>

        {{-- Detail Pengajuan --}}
        <div class="rounded-xl bg-white shadow-sm border border-gray-100 p-6">
            <p class="text-xs font-semibold uppercase tracking-widest text-gray-400 mb-4">Detail Pengajuan</p>
            <div class="space-y-4">
                <div class="flex justify-between items-start">
                    <span class="text-sm text-gray-500">Tanggal Pengiriman</span>
                    <span class="text-sm font-medium text-gray-800">{{ \Carbon\Carbon::parse($pengajuan->tanggal_pengiriman)->translatedFormat('d M Y') }}</span>
                </div>
                <div class="flex justify-between items-start">
                    <span class="text-sm text-gray-500">Tujuan Penyewaan</span>
                    <span class="text-sm font-medium text-gray-800">{{ $pengajuan->tujuan_penyewaan }}</span>
                </div>
                <div class="flex justify-between items-start">
                    <span class="text-sm text-gray-500">Kategori Toko</span>
                    <span class="text-sm font-medium text-gray-800">{{ $pengajuan->kategori_toko }}</span>
                </div>
            </div>
        </div>
    </div>

    {{-- Harga & Rasio --}}
    <div class="rounded-xl bg-white shadow-sm border border-gray-100 p-6">
        <p class="text-xs font-semibold uppercase tracking-widest text-gray-400 mb-4">Harga & Rasio</p>
        <div class="space-y-4">
            <div class="flex justify-between items-start">
                <span class="text-sm text-gray-500">Value Muatan</span>
                <span class="text-sm font-medium text-gray-800">Rp {{ number_format($pengajuan->value_muatan, 0, ',', '.') }}</span>
            </div>
            <div class="flex justify-between items-start">
                <span class="text-sm text-gray-500">Harga Sewa</span>
                <span class="text-sm font-semibold text-gray-900">Rp {{ number_format($pengajuan->harga_sewa, 0, ',', '.') }}</span>
            </div>
            <div class="flex justify-between items-center">
                <span class="text-sm text-gray-500">Rasio Sewa</span>
                <span class="text-sm font-semibold
                    @if($pengajuan->rasio_sewa > 2.5) text-red-600 @else text-avian-green @endif">
                    {{ number_format($pengajuan->rasio_sewa, 2, ',', '.') }}%
                </span>
            </div>
            <div class="pt-4 border-t border-gray-200">
                <div class="flex items-center justify-between mb-3">
                    <span class="text-sm font-medium text-gray-700">Kategori Approval</span>
                    @if($pengajuan->kategori_approval === 'over_threshold')
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-amber-50 px-3 py-1 border border-amber-200">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 text-amber-600" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M13 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V9z"/>
                                <polyline points="13 2 13 9 20 9"/>
                            </svg>
                            <span class="text-xs font-semibold text-amber-700">Perlu Persetujuan Berlapis</span>
                        </span>
                    @else
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-green-50 px-3 py-1 border border-green-200">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 text-avian-green" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41L9 16.17z"/>
                            </svg>
                            <span class="text-xs font-semibold text-avian-green">Persetujuan Standar</span>
                        </span>
                    @endif
                </div>
                @if($pengajuan->kategori_approval === 'over_threshold')
                <div class="bg-amber-50 rounded-lg px-3 py-2.5 border border-amber-100">
                    <p class="text-xs text-amber-800 font-medium mb-1.5">Alasan:</p>
                    <ul class="text-xs text-amber-700 space-y-1">
                        @if($pengajuan->tujuan_penyewaan === 'PAC')
                        <li class="flex items-start gap-2">
                            <span class="text-amber-400 mt-0.5">•</span>
                            <span><strong>Tujuan PAC</strong> — mutasi antar cabang memerlukan persetujuan WH</span>
                        </li>
                        @endif
                        @if($pengajuan->rasio_sewa > 2.5)
                        <li class="flex items-start gap-2">
                            <span class="text-amber-400 mt-0.5">•</span>
                            <span><strong>Rasio Sewa Tinggi</strong> — {{ number_format($pengajuan->rasio_sewa, 2, ',', '.') }}% (melebihi 2,5%)</span>
                        </li>
                        @endif
                    </ul>
                </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Catatan --}}
    @if($pengajuan->catatan_pengajuan)
    <div class="rounded-xl bg-white shadow-sm border border-gray-100 p-6">
        <p class="text-xs font-semibold uppercase tracking-widest text-gray-400 mb-3">Catatan</p>
        <p class="text-sm text-gray-700 whitespace-pre-wrap leading-relaxed">{{ $pengajuan->catatan_pengajuan }}</p>
    </div>
    @endif

    {{-- Dokumen SJ / TO-ACB --}}
    <div class="rounded-xl bg-white shadow-sm border border-gray-100 p-6">
        <p class="text-xs font-semibold uppercase tracking-widest text-gray-400 mb-4">Dokumen Terlampir</p>
        @if(isset($pengajuan->suratJalan) && $pengajuan->suratJalan->count() > 0)
        <div class="overflow-hidden rounded-lg border border-gray-200">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-200 bg-gray-50">
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-600">No. Dokumen</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-600">Jenis</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-600">Nilai</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($pengajuan->suratJalan as $sj)
                    <tr class="border-b border-gray-100 last:border-0">
                        <td class="px-4 py-3 font-mono text-xs text-gray-700">{{ $sj->no_sj }}</td>
                        <td class="px-4 py-3 text-gray-600">SJ</td>
                        <td class="px-4 py-3 text-right text-gray-800">Rp {{ number_format($sj->nilai ?? 0, 0, ',', '.') }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @else
        <div class="flex flex-col items-center justify-center py-8 text-center">
            <div class="flex h-10 w-10 items-center justify-center rounded-full bg-gray-100 mb-3">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-3-3v6M5 8h14M5 8a2 2 0 00-2 2v8a2 2 0 002 2h14a2 2 0 002-2v-8a2 2 0 00-2-2M5 8V6a2 2 0 012-2h10a2 2 0 012 2v2" />
                </svg>
            </div>
            <p class="text-sm text-gray-500">Belum ada dokumen terlampir</p>
            <p class="text-xs text-gray-400 mt-1">Dokumen SJ/TO-ACB akan tersedia setelah integrasi Quantum aktif</p>
        </div>
        @endif
    </div>

    {{-- Biaya Tambahan --}}
    @if($pengajuan->biayaTambahan && $pengajuan->biayaTambahan->count() > 0)
    <div class="rounded-xl bg-white shadow-sm border border-gray-100 p-6">
        <p class="text-xs font-semibold uppercase tracking-widest text-gray-400 mb-4">Biaya Tambahan</p>
        <div class="overflow-hidden rounded-lg border border-gray-200">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-200 bg-gray-50">
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-600">Jenis Biaya</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-600">Nominal</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($pengajuan->biayaTambahan as $biaya)
                    <tr class="border-b border-gray-100 last:border-0">
                        <td class="px-4 py-3 text-gray-800">{{ $biaya->jenisBiaya->nama_biaya ?? '-' }}</td>
                        <td class="px-4 py-3 text-right text-gray-800">Rp {{ number_format($biaya->jumlah, 0, ',', '.') }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif

    {{-- Timeline Approval Log --}}
    <div class="rounded-xl bg-white shadow-sm border border-gray-100 p-6">
        <p class="text-xs font-semibold uppercase tracking-widest text-gray-400 mb-6">Riwayat Persetujuan</p>

        @if(isset($approvalLogs) && $approvalLogs->count() > 0)
        <div class="relative">
            {{-- Vertical line --}}
            <div class="absolute left-4 top-2 bottom-2 w-px bg-gray-200"></div>
            <div class="space-y-6">
                @foreach($approvalLogs as $log)
                @php
                    $logStatus = strtolower($log->status ?? '');
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
                @endphp
                <div class="relative flex gap-4 pl-10">
                    <div class="absolute left-2.5 top-1 h-3 w-3 rounded-full border-2 {{ $dotColor }} -translate-x-1/2"></div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center justify-between">
                            <p class="text-sm font-medium text-gray-800">{{ $log->approver->name ?? 'Unknown' }}</p>
                            <span class="text-xs text-gray-400">{{ $log->decided_at ? \Carbon\Carbon::parse($log->decided_at)->translatedFormat('d M Y, H:i') : '-' }}</span>
                        </div>
                        <p class="text-xs text-gray-500 mt-0.5">{{ $logLabel }}</p>
                        @if($log->alasan_penolakan)
                        <p class="mt-2 text-xs text-red-600 bg-red-50 rounded-lg px-3 py-2">{{ $log->alasan_penolakan }}</p>
                        @endif
                    </div>
                </div>
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

    {{-- Action --}}
    <div class="flex items-center gap-3 pb-2">
        <a href="{{ route('dashboard.kg') }}"
            class="inline-flex items-center gap-2 rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
            </svg>
            Kembali ke Dashboard
        </a>
    </div>

</div>
@endsection
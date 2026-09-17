@extends('layouts.app')

@section('title', 'Detail Kendaraan')

@section('content')
<div class="space-y-5">

    {{-- Hero Bar --}}
    <div class="rounded-xl bg-white shadow-sm border border-gray-100 overflow-hidden">
        <div class="h-1 w-full bg-avian-green"></div>
        <div class="px-6 py-5">
            <a href="{{ route('kendaraan.idx') }}" class="inline-flex items-center gap-1.5 text-xs text-gray-400 hover:text-gray-600 transition mb-4">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                </svg>
                Kembali ke Daftar
            </a>
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-gray-100">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8 17h8M3 11l2-6h14l2 6M3 11h18M5 17v2m14-2v2" />
                        </svg>
                    </div>
                    <div>
                        <h1 class="text-base font-semibold text-gray-900">{{ $kendaraan->jenis_kendaraan }}</h1>
                        <p class="text-xs text-gray-400 mt-0.5">{{ $kendaraan->perusahaan?->nama_perusahaan }} &middot; Diperbarui {{ $kendaraan->updated_at?->translatedFormat('d M Y') }}</p>
                    </div>
                </div>
                <span class="inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-xs font-medium
                    {{ $kendaraan->flag ? 'bg-green-50 border border-green-200 text-avian-green' : 'bg-red-50 border border-red-200 text-red-600' }}">
                    <span class="h-1.5 w-1.5 rounded-full {{ $kendaraan->flag ? 'bg-avian-green' : 'bg-red-400' }}"></span>
                    {{ $kendaraan->flag ? 'Aktif' : 'Tidak Aktif' }}
                </span>
            </div>
        </div>
    </div>

    {{-- 2 Kolom: Info + Dokumen --}}
    <div class="grid grid-cols-2 gap-4 items-stretch">

        {{-- Kolom Kiri: Kendaraan + Perusahaan --}}
        <div class="space-y-4">

            {{-- Kendaraan --}}
            <div class="rounded-xl bg-white shadow-sm border border-gray-100 p-6">
                <p class="text-xs font-semibold uppercase tracking-widest text-gray-400 mb-4">Informasi Kendaraan</p>
                <div class="space-y-3">
                    <div class="flex justify-between items-center py-2 border-b border-gray-50">
                        <span class="text-sm text-gray-500">Nama Kendaraan</span>
                        <span class="text-sm font-medium text-gray-800">{{ $kendaraan->jenis_kendaraan }}</span>
                    </div>
                    <div class="flex justify-between items-center py-2 border-b border-gray-50">
                        <span class="text-sm text-gray-500">Plat Nomor</span>
                        <span class="text-sm font-mono font-medium text-gray-800">{{ $kendaraan->plat_nomor_truk }}</span>
                    </div>
                    <div class="flex justify-between items-center py-2 border-b border-gray-50">
                        <span class="text-sm text-gray-500">Kapasitas Muatan</span>
                        <span class="text-sm font-medium text-gray-800">{{ \App\Helpers\FormatHelper::ton($kendaraan->muatan_maksimal) }}</span>
                    </div>
                    <div class="flex justify-between items-center py-2 border-b border-gray-50">
                        <span class="text-sm text-gray-500">Skill / Area</span>
                        <span class="text-sm font-medium text-gray-800 text-right max-w-[60%]">{{ $kendaraan->id_skill }}</span>
                    </div>
                    <div class="flex justify-between items-center py-2">
                        <span class="text-sm text-gray-500">Cabang</span>
                        <span class="text-sm font-medium text-gray-800">{{ $kendaraan->id_cabang }}</span>
                    </div>
                </div>
            </div>

            {{-- Perusahaan --}}
            @if($kendaraan->perusahaan)
            <div class="rounded-xl bg-white shadow-sm border border-gray-100 p-6">
                <p class="text-xs font-semibold uppercase tracking-widest text-gray-400 mb-4">Informasi Perusahaan</p>
                <div class="space-y-3">
                    <div class="flex justify-between items-center py-2 border-b border-gray-50">
                        <span class="text-sm text-gray-500">Nama Perusahaan</span>
                        <span class="text-sm font-medium text-gray-800">{{ $kendaraan->perusahaan->nama_perusahaan }}</span>
                    </div>
                    <div class="flex justify-between items-center py-2 border-b border-gray-50">
                        <span class="text-sm text-gray-500">Badan Usaha</span>
                        <span class="text-sm font-medium text-gray-800">{{ $kendaraan->perusahaan->badan_usaha }}</span>
                    </div>
                    <div class="flex justify-between items-center py-2 border-b border-gray-50">
                        <span class="text-sm text-gray-500">Telepon</span>
                        @if($kendaraan->perusahaan->no_telepon)
                            <a href="tel:{{ $kendaraan->perusahaan->no_telepon }}" class="text-sm font-medium text-avian-green hover:underline">
                                {{ $kendaraan->perusahaan->no_telepon }}
                            </a>
                        @else
                            <span class="text-sm text-gray-400">-</span>
                        @endif
                    </div>
                    <div class="flex justify-between items-center py-2">
                        <span class="text-sm text-gray-500">Alamat</span>
                        <span class="text-sm font-medium text-gray-800 text-right max-w-[60%]">{{ $kendaraan->perusahaan->alamat_kantor ?? '-' }}</span>
                    </div>
                </div>
            </div>
            @endif
        </div>

        {{-- Kolom Kanan: Dokumen Identitas Perusahaan.
        Catatan 9 Sept 2026: dulu di sini foto KTP/SIM pengemudi per-kendaraan
        (ktp_supir/sim_supir), sekarang pakai slot foto yg sama persis tapi
        sumbernya identitas_owner per-vendor (sesi_perusahaan_ekspedisi) —
        datanya udah ga per-jenis dokumen (ga ada label KTP/SIM lagi di data),
        jadi ditampilin sbg "Dokumen Identitas #1/#2/dst" sejumlah yg ada. --}}
        <div class="flex flex-col rounded-xl bg-white shadow-sm border border-gray-100 p-6">
            <p class="text-xs font-semibold uppercase tracking-widest text-gray-400 mb-2">Dokumen Identitas Perusahaan</p>
            @php
                $dokumenIdentitas = $kendaraan->perusahaan?->identitas_owner ?? [];
            @endphp
            <div class="space-y-4 flex-1">
                @forelse($dokumenIdentitas as $i => $item)
                    <div>
                        <p class="text-sm text-gray-500 mb-3">Dokumen Identitas #{{ $i + 1 }}</p>
                        <div class="rounded-lg border border-gray-200 overflow-hidden bg-gray-50 h-55">
                            <img src="{{ \App\Helpers\FormatHelper::identitasOwnerSrc($item) }}" alt="Dokumen Identitas #{{ $i + 1 }}" class="w-full h-full object-cover">
                        </div>
                    </div>
                @empty
                    <div class="rounded-lg border-2 border-dashed border-gray-200 h-55 flex flex-col items-center justify-center bg-gray-50">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-gray-300 mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0" />
                        </svg>
                        <p class="text-xs text-gray-400">Belum ada dokumen identitas</p>
                    </div>
                @endforelse
            </div>
        </div>
    </div>
</div>

{{-- Floating Action Button --}}
<div class="fixed bottom-6 right-6">
    <a href="{{ route('pengajuan.kg', ['id' => $kendaraan->id_kendaraan, 'nama' => $kendaraan->perusahaan->nama_perusahaan ?? $kendaraan->jenis_kendaraan, 'kendaraan' => $kendaraan->jenis_kendaraan, 'muatan' => \App\Helpers\FormatHelper::ton($kendaraan->muatan_maksimal), 'muatan_raw' => $kendaraan->muatan_maksimal, 'harga' => $hargaSewaTerakhir ? 'Rp ' . number_format($hargaSewaTerakhir, 0, ',', '.') : 'Rp 0', 'skill' => $kendaraan->id_skill]) }}"
        class="group relative flex h-14 w-14 items-center justify-center rounded-full bg-avian-green text-white shadow-lg hover:shadow-xl transition-all hover:scale-105"
        title="Buat Pengajuan Sewa">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
        </svg>
        <span class="absolute right-full mr-3 whitespace-nowrap rounded bg-gray-900 px-3 py-1.5 text-xs text-white opacity-0 transition group-hover:opacity-100 pointer-events-none">
            Buat Pengajuan
        </span>
    </a>
</div>
@endsection
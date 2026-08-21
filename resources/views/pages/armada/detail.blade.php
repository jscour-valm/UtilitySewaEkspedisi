@extends('layouts.app')

@section('title', 'Detail Armada')

@section('content')
<div class="space-y-5">

    {{-- Hero Bar --}}
    <div class="rounded-xl bg-white shadow-sm border border-gray-100 overflow-hidden">
        <div class="h-1 w-full bg-avian-green"></div>
        <div class="px-6 py-5">
            <a href="{{ route('armada.idx') }}" class="inline-flex items-center gap-1.5 text-xs text-gray-400 hover:text-gray-600 transition mb-4">
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
                        <h1 class="text-base font-semibold text-gray-900">{{ $armada->nama_kendaraan }}</h1>
                        <p class="text-xs text-gray-400 mt-0.5">{{ $armada->perusahaan?->nama_perusahaan }} &middot; Diperbarui {{ $armada->updated_at?->translatedFormat('d M Y') }}</p>
                    </div>
                </div>
                <span class="inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-xs font-medium
                    {{ $armada->flag ? 'bg-green-50 border border-green-200 text-avian-green' : 'bg-red-50 border border-red-200 text-red-600' }}">
                    <span class="h-1.5 w-1.5 rounded-full {{ $armada->flag ? 'bg-avian-green' : 'bg-red-400' }}"></span>
                    {{ $armada->flag ? 'Aktif' : 'Tidak Aktif' }}
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
                        <span class="text-sm font-medium text-gray-800">{{ $armada->nama_kendaraan }}</span>
                    </div>
                    <div class="flex justify-between items-center py-2 border-b border-gray-50">
                        <span class="text-sm text-gray-500">Plat Nomor</span>
                        <span class="text-sm font-mono font-medium text-gray-800">{{ $armada->plat_nomor }}</span>
                    </div>
                    <div class="flex justify-between items-center py-2 border-b border-gray-50">
                        <span class="text-sm text-gray-500">Kapasitas Muatan</span>
                        <span class="text-sm font-medium text-gray-800">{{ \App\Helpers\FormatHelper::ton($armada->muatan_maksimal) }}</span>
                    </div>
                    <div class="flex justify-between items-center py-2 border-b border-gray-50">
                        <span class="text-sm text-gray-500">Skill / Area</span>
                        <span class="text-sm font-medium text-gray-800 text-right max-w-[60%]">{{ $armada->id_skill }}</span>
                    </div>
                    <div class="flex justify-between items-center py-2">
                        <span class="text-sm text-gray-500">Cabang</span>
                        <span class="text-sm font-medium text-gray-800">{{ $armada->id_cabang }}</span>
                    </div>
                </div>
            </div>

            {{-- Perusahaan --}}
            @if($armada->perusahaan)
            <div class="rounded-xl bg-white shadow-sm border border-gray-100 p-6">
                <p class="text-xs font-semibold uppercase tracking-widest text-gray-400 mb-4">Informasi Perusahaan</p>
                <div class="space-y-3">
                    <div class="flex justify-between items-center py-2 border-b border-gray-50">
                        <span class="text-sm text-gray-500">Nama Perusahaan</span>
                        <span class="text-sm font-medium text-gray-800">{{ $armada->perusahaan->nama_perusahaan }}</span>
                    </div>
                    <div class="flex justify-between items-center py-2 border-b border-gray-50">
                        <span class="text-sm text-gray-500">Badan Usaha</span>
                        <span class="text-sm font-medium text-gray-800">{{ $armada->perusahaan->badan_usaha }}</span>
                    </div>
                    <div class="flex justify-between items-center py-2 border-b border-gray-50">
                        <span class="text-sm text-gray-500">Telepon</span>
                        @if($armada->perusahaan->no_telepon)
                            <a href="tel:{{ $armada->perusahaan->no_telepon }}" class="text-sm font-medium text-avian-green hover:underline">
                                {{ $armada->perusahaan->no_telepon }}
                            </a>
                        @else
                            <span class="text-sm text-gray-400">-</span>
                        @endif
                    </div>
                    <div class="flex justify-between items-center py-2">
                        <span class="text-sm text-gray-500">Alamat</span>
                        <span class="text-sm font-medium text-gray-800 text-right max-w-[60%]">{{ $armada->perusahaan->alamat_kantor ?? '-' }}</span>
                    </div>
                </div>
            </div>
            @endif
        </div>

        {{-- Kolom Kanan: Dokumen Pengemudi --}}
        <div class="flex flex-col rounded-xl bg-white shadow-sm border border-gray-100 p-6">
            <p class="text-xs font-semibold uppercase tracking-widest text-gray-400 mb-2">Dokumen Pengemudi</p>
            <div class="space-y-4 flex-1">

                {{-- KTP --}}
                <div>
                    <p class="text-sm text-gray-500 mb-3">Foto KTP Pengemudi</p>
                    @if($armada->ktp_supir)
                        <div class="rounded-lg border border-gray-200 overflow-hidden bg-gray-50 h-55">
                            <img src="{{ asset($armada->ktp_supir) }}" alt="KTP Pengemudi" class="w-full h-full object-cover">
                        </div>
                    @else
                        <div class="rounded-lg border-2 border-dashed border-gray-200 h-55 flex flex-col items-center justify-center bg-gray-50">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-gray-300 mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0" />
                            </svg>
                            <p class="text-xs text-gray-400">Belum ada foto KTP</p>
                        </div>
                    @endif
                </div>

                {{-- SIM --}}
                <div class="flex flex-col">
                    <p class="text-sm text-gray-500 mb-3">Foto SIM Pengemudi</p>
                    @if($armada->sim_supir)
                        <div class="rounded-lg border border-gray-200 overflow-hidden bg-gray-50 h-55">
                            <img src="{{ asset($armada->sim_supir) }}" alt="SIM Pengemudi" class="w-full h-full object-cover">
                        </div>
                    @else
                        <div class="rounded-lg border-2 border-dashed border-gray-200 h-55 flex flex-col items-center justify-center bg-gray-50">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-gray-300 mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0" />
                            </svg>
                            <p class="text-xs text-gray-400">Belum ada foto SIM</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Floating Action Button --}}
<div class="fixed bottom-6 right-6">
    <a href="{{ route('pengajuan.kg', ['id' => $armada->id_armada, 'nama' => $armada->perusahaan->nama_perusahaan ?? $armada->nama_kendaraan, 'kendaraan' => $armada->nama_kendaraan, 'muatan' => \App\Helpers\FormatHelper::ton($armada->muatan_maksimal), 'muatan_raw' => $armada->muatan_maksimal, 'harga' => 'Rp 0', 'skill' => $armada->id_skill]) }}"
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
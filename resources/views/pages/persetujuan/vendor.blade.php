@extends('layouts.app')

@section('title', 'Pengajuan Vendor Baru')

@section('content')
@php
    $status = $vendor->statusPersetujuan();
    $warnaStatus = match ($status) {
        'approved' => 'bg-avian-green-light text-avian-green',
        'rejected' => 'bg-red-50 text-red-600',
        default => 'bg-amber-50 text-amber-700',
    };
    $namaLengkap = trim(($vendor->badan_usaha && $vendor->badan_usaha !== '-' ? $vendor->badan_usaha . ' ' : '') . $vendor->nama_perusahaan);
    $labelAksi = $peran === 'WM' ? 'Validasi' : 'Setujui';
    $sectionLabel = 'text-[11px] font-semibold tracking-[0.09em] text-gray-500';
@endphp

<div class="space-y-4 pb-6">
    {{-- ==================== HEADER ==================== --}}
    <div class="rounded-xl border border-gray-100 bg-white p-5 shadow-sm">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="min-w-0">
                <p class="{{ $sectionLabel }}">PENGAJUAN VENDOR BARU</p>
                <h1 class="mt-1 text-[22px] font-bold tracking-tight text-gray-900">{{ $namaLengkap }}</h1>
                <p class="mt-1 text-[13px] text-gray-500">
                    Diajukan oleh <strong class="text-gray-700">{{ $vendor->submittedBy?->name ?? '-' }}</strong>
                    &middot; Cab. {{ $vendor->id_cabang_pengaju }}{{ $namaCabang ? ' (' . $namaCabang . ')' : '' }}
                    &middot; {{ $vendor->submitted_at?->translatedFormat('d M Y H:i') ?? '-' }}
                </p>
            </div>
            <span class="rounded-full px-3 py-1 text-sm font-semibold {{ $warnaStatus }}">{{ $vendor->labelStatusPersetujuan() }}</span>
        </div>

        {{-- Alur: Diajukan KG → Validasi WM → Approval WH --}}
        @php
            $langkah = [
                ['label' => 'Diajukan KG', 'selesai' => true],
                ['label' => 'Validasi WM', 'selesai' => in_array($status, ['menunggu_approval', 'approved'], true)],
                ['label' => 'Approval WH', 'selesai' => $status === 'approved'],
            ];
        @endphp
        <div class="mt-4 flex flex-wrap items-center gap-2 text-xs">
            @foreach($langkah as $i => $l)
                @if($i > 0)<span class="text-gray-300">→</span>@endif
                <span class="rounded-full px-2.5 py-1 font-medium {{ $l['selesai'] ? 'bg-avian-green-light text-avian-green' : 'bg-gray-100 text-gray-500' }}">
                    {{ $l['selesai'] ? '✓ ' : '' }}{{ $l['label'] }}
                </span>
            @endforeach
        </div>

        @if($status === 'rejected' && $vendor->alasan_penolakan)
            <div class="mt-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                <p class="font-semibold">Alasan penolakan</p>
                <p class="mt-0.5">{{ $vendor->alasan_penolakan }}</p>
            </div>
        @endif

        @if($bisaPutuskan)
            <div class="mt-5 flex flex-wrap items-center justify-between gap-3 border-t border-gray-100 pt-4">
                <p class="text-sm text-gray-600">
                    {{ $peran === 'WM' ? 'Menunggu validasi Anda.' : 'Menunggu approval Anda.' }}
                    Pengajuan sewa yang memakai vendor ini baru bisa divalidasi setelah vendor disetujui WH.
                </p>
                <div class="flex gap-2.5">
                    <button type="button"
                        onclick="putuskanPersetujuan(@js(route('persetujuan.vendor.putuskan', $vendor->id_perusahaan)), false, { objek: 'vendor ini' })"
                        class="rounded-lg border border-red-200 bg-white px-5 py-2.5 text-sm font-semibold text-red-600 transition hover:bg-red-50">
                        Tolak
                    </button>
                    <button type="button"
                        onclick="putuskanPersetujuan(@js(route('persetujuan.vendor.putuskan', $vendor->id_perusahaan)), true, { label: @js($labelAksi), objek: 'vendor ini' })"
                        class="rounded-lg bg-avian-green px-5 py-2.5 text-sm font-semibold text-white transition hover:brightness-90">
                        {{ $labelAksi }}
                    </button>
                </div>
            </div>
        @endif
    </div>

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-[1.3fr_1fr]">
        {{-- ==================== DATA VENDOR ==================== --}}
        <div class="rounded-xl border border-gray-100 bg-white p-5 shadow-sm">
            @if($bisaAjukanUlang)
                <div x-data="formVendor(@js(route('persetujuan.vendor.ajukan-ulang', $vendor->id_perusahaan)), @js($vendor->only(['nama_perusahaan', 'badan_usaha', 'no_telepon', 'alamat_kantor'])))">
                    <p class="{{ $sectionLabel }} mb-1">PERBAIKI & AJUKAN ULANG</p>
                    <p class="mb-4 text-xs text-gray-500">Perbaiki data sesuai alasan penolakan, lalu ajukan lagi ke WM.</p>
                    <x-form-vendor :foto-lama="$docs->all()" />
                    <div class="mt-4 flex justify-end">
                        <button type="button" @click="simpan()" :disabled="menyimpan"
                            class="rounded-lg bg-avian-green px-4 py-2 text-sm font-medium text-white hover:bg-avian-green-dark disabled:opacity-50">
                            <span x-text="menyimpan ? 'Mengajukan…' : 'Ajukan Ulang'"></span>
                        </button>
                    </div>
                </div>
            @else
                <p class="{{ $sectionLabel }} mb-1">INFORMASI PERUSAHAAN</p>
                @foreach([
                    'Badan Usaha' => $vendor->badan_usaha,
                    'No. Telepon' => $vendor->no_telepon,
                    'Alamat Kantor' => $vendor->alamat_kantor,
                ] as $label => $nilai)
                    <div class="flex justify-between gap-4 border-b border-gray-50 py-2 last:border-0">
                        <span class="text-sm text-gray-500">{{ $label }}</span>
                        <span class="text-right text-sm font-semibold text-gray-900">{{ $nilai ?: '—' }}</span>
                    </div>
                @endforeach

                <p class="{{ $sectionLabel }} mt-4">DOKUMEN IDENTITAS</p>
                @if($docs->isNotEmpty())
                    <div class="mt-2 grid grid-cols-3 gap-2">
                        @foreach($docs as $i => $src)
                            <div @click="openLightbox(@js($docs), {{ $i }})"
                                class="h-24 cursor-zoom-in overflow-hidden rounded-lg border border-gray-200 bg-gray-50 transition hover:opacity-80">
                                <img src="{{ $src }}" alt="Dokumen identitas #{{ $i + 1 }}" class="h-full w-full object-cover">
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="mt-2 text-sm text-gray-400">Belum ada dokumen identitas.</p>
                @endif

                @if($vendor->flag && $vendor->sudahDisetujui())
                    <a href="{{ route('perusahaan.show', $vendor->id_perusahaan) }}"
                        class="mt-4 inline-block text-sm font-medium text-avian-green hover:underline">Lihat di halaman Perusahaan →</a>
                @endif
            @endif
        </div>

        {{-- ==================== RIWAYAT ==================== --}}
        <div class="rounded-xl border border-gray-100 bg-white p-5 shadow-sm">
            <p class="{{ $sectionLabel }} mb-3">RIWAYAT</p>
            <ol class="space-y-3 text-sm">
                <li class="flex gap-3">
                    <span class="mt-1.5 h-2 w-2 shrink-0 rounded-full bg-gray-400"></span>
                    <div>
                        <p class="font-medium text-gray-800">{{ $vendor->approvalLogs->isNotEmpty() && $vendor->submitted_at > $vendor->approvalLogs->last()->decided_at ? 'Diajukan ulang' : 'Diajukan' }} oleh KG</p>
                        <p class="text-xs text-gray-500">{{ $vendor->submittedBy?->name ?? '-' }} &middot; {{ $vendor->submitted_at?->translatedFormat('d M Y H:i') }}</p>
                    </div>
                </li>
                @foreach($vendor->approvalLogs as $log)
                    @php $setuju = $log->status === 'approved'; @endphp
                    <li class="flex gap-3">
                        <span class="mt-1.5 h-2 w-2 shrink-0 rounded-full {{ $setuju ? 'bg-avian-green' : 'bg-red-500' }}"></span>
                        <div>
                            <p class="font-medium text-gray-800">
                                {{ $setuju ? ($log->role_approver === 'WM' ? 'Divalidasi' : 'Disetujui') : 'Ditolak' }} {{ $log->role_approver }}
                            </p>
                            <p class="text-xs text-gray-500">{{ $log->approver?->name ?? '-' }} &middot; {{ $log->decided_at?->translatedFormat('d M Y H:i') }}</p>
                            @if($log->alasan_penolakan)
                                <p class="mt-0.5 text-xs italic text-red-600">"{{ $log->alasan_penolakan }}"</p>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ol>
        </div>
    </div>

    {{-- ==================== PENGAJUAN SEWA YANG MEMAKAI VENDOR INI ==================== --}}
    <div class="rounded-xl border border-gray-100 bg-white p-5 shadow-sm">
        <p class="{{ $sectionLabel }} mb-3">PENGAJUAN SEWA YANG MEMAKAI VENDOR INI</p>
        @if($pengajuan->isEmpty())
            <p class="text-sm text-gray-400">Belum ada pengajuan sewa yang memakai vendor ini.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full min-w-[36rem] text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                            <th class="py-2 pr-3">No.</th>
                            <th class="py-2 pr-3">Jenis</th>
                            <th class="py-2 pr-3">Tgl Kirim</th>
                            <th class="py-2 pr-3">Diajukan oleh</th>
                            <th class="py-2 pr-3">Status</th>
                            <th class="py-2"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($pengajuan as $p)
                            <tr>
                                <td class="py-2 pr-3 font-medium text-gray-800">#{{ $p->id_pengajuan_sewa }}</td>
                                <td class="py-2 pr-3 text-gray-600">{{ $p->jenis_pengajuan === 'sewa_truk' ? 'Sewa Truk' : 'Kiriman Rutin' }}</td>
                                <td class="py-2 pr-3 text-gray-600">{{ $p->tanggal_pengiriman?->format('d M Y') }}</td>
                                <td class="py-2 pr-3 text-gray-600">{{ $p->submittedBy?->name ?? '-' }}</td>
                                @php
                                    [$labelP, $warnaP] = match (strtolower($p->status_pengajuan)) {
                                        'approved' => ['Disetujui', 'bg-avian-green-light text-avian-green'],
                                        'rejected' => ['Ditolak', 'bg-red-50 text-red-600'],
                                        'cancelled' => ['Dibatalkan', 'bg-gray-100 text-gray-600'],
                                        default => ['Pending', 'bg-amber-50 text-amber-700'],
                                    };
                                @endphp
                                <td class="py-2 pr-3"><span class="rounded-full px-2 py-0.5 text-xs font-medium {{ $warnaP }}">{{ $labelP }}</span></td>
                                <td class="py-2 text-right">
                                    <a href="{{ route('pengajuan.buka', $p->id_pengajuan_sewa) }}" class="text-xs font-medium text-avian-green hover:underline">Buka →</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
@endsection

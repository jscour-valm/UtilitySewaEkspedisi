@extends('layouts.app')

@section('title', 'Batas Rasio Sewa')

@section('content')
@php
    $persen = fn ($nilai) => rtrim(rtrim(number_format((float) $nilai, 2, ',', '.'), '0'), ',') . '%';
    $batas = $rasioAktif ? (float) $rasioAktif->persentase_maksimal : \App\Models\RasioSewa::BATAS_DEFAULT;
@endphp

<div class="flex flex-col gap-4 pb-2">
    <x-ui.alert-success />
    <x-ui.alert-error />

    <div class="rounded-xl bg-white p-6 shadow-sm">
        <h1 class="mb-4 text-2xl font-bold text-gray-900">Batas Rasio Sewa</h1>

        <div class="flex flex-wrap items-end gap-x-10 gap-y-4">
            <div>
                <p class="text-sm text-gray-500">Berlaku sekarang</p>
                <p class="text-3xl font-bold text-gray-900">{{ $persen($batas) }}</p>
                <p class="text-xs text-gray-400">sejak {{ $rasioAktif?->effective_date?->translatedFormat('d M Y') ?? '—' }}</p>
            </div>

            <form method="POST" action="{{ route('pengaturan.rasio.simpan') }}" class="flex flex-wrap items-end gap-3">
                @csrf
                <div class="w-40">
                    <x-ui.form-label required>Batas baru (%)</x-ui.form-label>
                    <input type="number" name="persentase_maksimal" step="0.01" min="0.01" max="100" required
                        value="{{ old('persentase_maksimal') }}"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-avian-green focus:outline-none">
                </div>
                <div class="w-44">
                    <x-ui.form-label required>Mulai berlaku</x-ui.form-label>
                    <input type="date" name="mulai_berlaku" required min="{{ now()->toDateString() }}"
                        value="{{ old('mulai_berlaku', now()->toDateString()) }}"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-avian-green focus:outline-none">
                </div>
                <button type="submit" class="rounded-lg bg-avian-green px-4 py-2 text-sm font-medium text-white hover:bg-avian-green-dark">Simpan Batas Rasio</button>
            </form>
        </div>
        <p class="mt-3 text-xs text-gray-400">Pengajuan yang sudah diajukan tidak berubah.</p>
    </div>

    <div class="rounded-xl bg-white p-6 shadow-sm">
        <h2 class="mb-3 text-sm font-semibold text-gray-700">Riwayat Batas Rasio</h2>
        <div class="overflow-x-auto">
                <table class="w-full min-w-[22rem] text-sm">
                    <thead>
                        <tr class="kepala-tabel text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                            <th class="px-3 py-2">Batas</th>
                            <th class="px-3 py-2">Mulai</th>
                            <th class="px-3 py-2">Sampai</th>
                            <th class="px-3 py-2">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($riwayatRasio as $r)
                            @php
                                $aktif = $rasioAktif && $r->id_rasio_sewa === $rasioAktif->id_rasio_sewa;
                                $status = ! $r->flag ? 'Dibatalkan' : ($aktif ? 'Berlaku' : ($r->effective_date && $r->effective_date->isFuture() ? 'Terjadwal' : 'Selesai'));
                            @endphp
                            <tr>
                                <td class="px-3 py-2 font-semibold text-gray-800">{{ $persen($r->persentase_maksimal) }}</td>
                                <td class="px-3 py-2 text-gray-600">{{ $r->effective_date?->translatedFormat('d M Y') ?? '—' }}</td>
                                <td class="px-3 py-2 text-gray-600">{{ $r->end_date?->translatedFormat('d M Y') ?? '—' }}</td>
                                <td class="px-3 py-2">
                                    <span class="rounded-full px-2 py-0.5 text-xs font-medium
                                        {{ match ($status) { 'Berlaku' => 'bg-avian-green-light text-avian-green', 'Terjadwal' => 'bg-blue-50 text-blue-600', default => 'bg-gray-100 text-gray-500' } }}">{{ $status }}</span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
        </div>
    </div>
</div>
@endsection

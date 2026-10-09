@extends('layouts.app')

@section('title', 'Pengaturan')

@section('content')
@php
    $sectionLabel = 'text-[11px] font-semibold tracking-[0.09em] text-gray-500';
    $labelKondisi = function (string $kunci) {
        [$jenis, $tujuan, $rasio] = explode('|', $kunci);

        return [
            $jenis === 'sewa_truk' ? 'Sewa Truk' : 'Kiriman Rutin',
            $tujuan,
            match ($rasio) { 'atas' => 'di atas batas', 'bawah' => 'di bawah / sama dengan batas', default => 'tanpa rasio' },
        ];
    };
    $labelAlur = ['WM' => 'WM saja (final di WM)', 'WM,WC' => 'WM → WC', 'WM,WH' => 'WM → WH', 'WM,WC,WH' => 'WM → WC → WH'];
    $batas = $rasioAktif ? (float) $rasioAktif->persentase_maksimal : 2.5;
    $grupEmail = collect(\App\Models\AturanEmail::KEJADIAN)->groupBy('grup', true);
@endphp

<div class="space-y-4 pb-6">
    <x-alert-success />
    @if($errors->any())
        <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ $errors->first() }}</div>
    @endif
    @unless($tabelSiap)
        <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
            Tabel pengaturan belum ada di database (migration belum dijalankan). Alur & penerima email memakai aturan bawaan dan belum bisa diubah.
        </div>
    @endunless

    {{-- ==================== BATAS RASIO ==================== --}}
    <div class="rounded-xl border border-gray-100 bg-white p-5 shadow-sm">
        <p class="{{ $sectionLabel }}">BATAS RASIO SEWA</p>
        <p class="mt-1 text-sm text-gray-500">
            Rasio = (harga sewa + biaya tambahan) ÷ value muatan. Di atas batas, alur memakai pengaturan "di atas batas" di bawah.
        </p>
        <div class="mt-4 grid grid-cols-1 gap-5 lg:grid-cols-[1fr_1.4fr]">
            <div>
                <p class="text-sm text-gray-500">Berlaku sekarang</p>
                <p class="text-3xl font-bold text-gray-900">{{ rtrim(rtrim(number_format($batas, 2, ',', '.'), '0'), ',') }}%</p>
                <p class="text-xs text-gray-400">sejak {{ $rasioAktif?->effective_date?->translatedFormat('d M Y') ?? '—' }}</p>

                <form method="POST" action="{{ route('pengaturan.rasio') }}" class="mt-4 space-y-3">
                    @csrf
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <x-form-label required>Batas baru (%)</x-form-label>
                            <input type="number" name="persentase_maksimal" step="0.01" min="0.01" max="100" required
                                value="{{ old('persentase_maksimal') }}"
                                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-avian-green focus:outline-none">
                        </div>
                        <div>
                            <x-form-label required>Mulai berlaku</x-form-label>
                            <input type="date" name="mulai_berlaku" required min="{{ now()->toDateString() }}"
                                value="{{ old('mulai_berlaku', now()->toDateString()) }}"
                                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-avian-green focus:outline-none">
                        </div>
                    </div>
                    <button type="submit" class="rounded-lg bg-avian-green px-4 py-2 text-sm font-medium text-white hover:bg-avian-green-dark">Simpan Batas Rasio</button>
                    <p class="text-xs text-gray-400">Pengajuan yang sudah diajukan tidak berubah.</p>
                </form>
            </div>
            <div class="overflow-x-auto">
                <p class="mb-2 text-sm font-medium text-gray-700">Riwayat</p>
                <table class="w-full min-w-[22rem] text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                            <th class="py-2 pr-3">Batas</th>
                            <th class="py-2 pr-3">Mulai</th>
                            <th class="py-2 pr-3">Sampai</th>
                            <th class="py-2">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($riwayatRasio as $r)
                            @php
                                $aktif = $rasioAktif && $r->id_rasio_sewa === $rasioAktif->id_rasio_sewa;
                                $status = ! $r->flag ? 'Dibatalkan' : ($aktif ? 'Berlaku' : ($r->effective_date && $r->effective_date->isFuture() ? 'Terjadwal' : 'Selesai'));
                            @endphp
                            <tr>
                                <td class="py-2 pr-3 font-semibold text-gray-800">{{ rtrim(rtrim(number_format((float) $r->persentase_maksimal, 2, ',', '.'), '0'), ',') }}%</td>
                                <td class="py-2 pr-3 text-gray-600">{{ $r->effective_date?->translatedFormat('d M Y') ?? '—' }}</td>
                                <td class="py-2 pr-3 text-gray-600">{{ $r->end_date?->translatedFormat('d M Y') ?? '—' }}</td>
                                <td class="py-2">
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

    {{-- ==================== ALUR APPROVAL ==================== --}}
    <form method="POST" action="{{ route('pengaturan.alur') }}" class="rounded-xl border border-gray-100 bg-white p-5 shadow-sm">
        @csrf
        <p class="{{ $sectionLabel }}">ALUR APPROVAL PENGAJUAN SEWA</p>
        <p class="mt-1 text-sm text-gray-500">
            WM selalu memvalidasi pertama. Pilih tahap sesudahnya per kondisi. Alur dikunci saat pengajuan diajukan —
            pengajuan yang sudah berjalan tidak ikut berubah. Vendor baru & usulan harga selalu WM → WH.
        </p>
        <div class="mt-4 overflow-x-auto">
            <table class="w-full min-w-[40rem] text-sm">
                <thead>
                    <tr class="border-b border-gray-200 text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                        <th class="py-2 pr-3">Jenis</th>
                        <th class="py-2 pr-3">Tujuan</th>
                        <th class="py-2 pr-3">Rasio (batas {{ rtrim(rtrim(number_format($batas, 2, ',', '.'), '0'), ',') }}%)</th>
                        <th class="py-2">Alur</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($alur as $kunci => $nilai)
                        @php [$j, $t, $r] = $labelKondisi($kunci); @endphp
                        <tr>
                            <td class="py-2.5 pr-3 font-medium text-gray-800">{{ $j }}</td>
                            <td class="py-2.5 pr-3 text-gray-600">{{ $t }}</td>
                            <td class="py-2.5 pr-3 text-gray-600">{{ $r }}</td>
                            <td class="py-2.5">
                                <select name="alur[{{ $kunci }}]" @disabled(! $tabelSiap)
                                    class="w-full max-w-[14rem] rounded-lg border border-gray-300 px-3 py-1.5 text-sm focus:border-avian-green focus:outline-none disabled:bg-gray-50">
                                    @foreach($labelAlur as $v => $l)
                                        <option value="{{ $v }}" @selected($nilai === $v)>{{ $l }}</option>
                                    @endforeach
                                </select>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="mt-4 flex justify-end">
            <button type="submit" @disabled(! $tabelSiap)
                class="rounded-lg bg-avian-green px-4 py-2 text-sm font-medium text-white hover:bg-avian-green-dark disabled:opacity-50">Simpan Alur</button>
        </div>
    </form>

    {{-- ==================== PENERIMA EMAIL ==================== --}}
    <form method="POST" action="{{ route('pengaturan.email') }}" class="rounded-xl border border-gray-100 bg-white p-5 shadow-sm">
        @csrf
        <p class="{{ $sectionLabel }}">PENERIMA EMAIL NOTIFIKASI</p>
        <p class="mt-1 text-sm text-gray-500">
            Pilih <strong>To</strong> / <strong>CC</strong> / kosong per role. KG = pengaju saja; WM & KA = cabang terkait; WC & DCI = semua user role itu; WH = approver WH.
            Pengajuan dibatalkan otomatis dikirim ke peran yang sedang giliran (CC pengaju, yang sudah approve, DCI).
        </p>
        <div class="mt-4 overflow-x-auto">
            <table class="w-full min-w-[46rem] text-sm">
                <thead>
                    <tr class="border-b border-gray-200 text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                        <th class="py-2 pr-3">Kejadian</th>
                        @foreach(\App\Models\AturanEmail::ROLE as $role)
                            <th class="py-2 pr-2 text-center">{{ $role }}</th>
                        @endforeach
                    </tr>
                </thead>
                @foreach($grupEmail as $grup => $kejadianGrup)
                    <tbody class="divide-y divide-gray-100">
                        <tr><td colspan="{{ count(\App\Models\AturanEmail::ROLE) + 1 }}" class="bg-gray-50 px-2 py-1.5 text-xs font-semibold text-gray-600">{{ $grup }}</td></tr>
                        @foreach($kejadianGrup as $kejadian => $k)
                            <tr>
                                <td class="py-2 pr-3 text-gray-700">{{ $k['label'] }}</td>
                                @foreach(\App\Models\AturanEmail::ROLE as $role)
                                    @php
                                        $nilai = old("email.$kejadian.$role", in_array($role, $email[$kejadian]['to'], true) ? 'to' : (in_array($role, $email[$kejadian]['cc'], true) ? 'cc' : ''));
                                    @endphp
                                    <td class="py-2 pr-2 text-center">
                                        <select name="email[{{ $kejadian }}][{{ $role }}]" @disabled(! $tabelSiap)
                                            class="rounded-md border px-1.5 py-1 text-xs focus:border-avian-green focus:outline-none disabled:bg-gray-50
                                            {{ $nilai === 'to' ? 'border-avian-green bg-avian-green-light font-semibold text-avian-green' : ($nilai === 'cc' ? 'border-blue-200 bg-blue-50 text-blue-700' : 'border-gray-200 text-gray-400') }}">
                                            <option value="" @selected($nilai === '')>—</option>
                                            <option value="to" @selected($nilai === 'to')>To</option>
                                            <option value="cc" @selected($nilai === 'cc')>CC</option>
                                        </select>
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                @endforeach
            </table>
        </div>
        <div class="mt-4 flex justify-end">
            <button type="submit" @disabled(! $tabelSiap)
                class="rounded-lg bg-avian-green px-4 py-2 text-sm font-medium text-white hover:bg-avian-green-dark disabled:opacity-50">Simpan Penerima Email</button>
        </div>
    </form>
</div>
@endsection

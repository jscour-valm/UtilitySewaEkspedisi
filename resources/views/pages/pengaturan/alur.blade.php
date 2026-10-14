@extends('layouts.app')

@section('title', 'Alur Approval')

@section('content')
@php
    use App\Models\AturanAlur;

    // Baris tabel: [kunci, nama, tujuan, rasio, alur]. Proses master memakai kunci "<proses>|-|-".
    $grup = ['Pengajuan sewa' => [], 'Vendor baru & usulan harga' => []];
    foreach ($alur as $kunci => $nilai) {
        [$jenis, $tujuan, $rasio] = explode('|', $kunci);
        $grup['Pengajuan sewa'][] = [
            $kunci,
            \App\Models\PengajuanSewa::JENIS[$jenis] ?? $jenis,
            $tujuan,
            match ($rasio) { 'atas' => 'di atas batas', 'bawah' => 'di bawah / sama dengan batas', default => 'tanpa rasio' },
            $nilai,
        ];
    }
    foreach ($alurMaster as $proses => $nilai) {
        $grup['Vendor baru & usulan harga'][] = [AturanAlur::kunci($proses, '-', '-'), AturanAlur::LABEL_MASTER[$proses], '—', '—', $nilai];
    }
    $kelasPilih = 'pilih-peran w-24 rounded-lg border px-2.5 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-avian-green/30 disabled:opacity-60';
@endphp

<div class="flex flex-col gap-4 pb-2">
    <x-ui.alert-success />
    <x-ui.alert-error />
    @include('pages.pengaturan.partials.tabel-belum-ada')

    <form method="POST" action="{{ route('pengaturan.alur.simpan') }}" data-tandai-ubah class="rounded-xl bg-white p-6 shadow-sm">
        @csrf
        <h1 class="mb-1 text-2xl font-bold text-gray-900">Alur Approval</h1>
        <p class="mb-4 text-sm text-gray-500">
            Alur dikunci saat diajukan — yang sudah berjalan tidak ikut berubah.
        </p>

        <div class="overflow-x-auto">
            <table class="w-full min-w-[52rem] text-sm">
                <thead>
                    <tr class="kepala-tabel text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                        <th class="px-3 py-2">Jenis</th>
                        <th class="px-3 py-2">Tujuan</th>
                        <th class="px-3 py-2">Rasio (batas {{ rtrim(rtrim(number_format($batas, 2, ',', '.'), '0'), ',') }}%)</th>
                        <th class="px-3 py-2">Alur (tahap 1 → tahap 2 → tahap 3)</th>
                    </tr>
                </thead>
                @foreach($grup as $namaGrup => $baris)
                    {{-- Satu kelompok: judul rata kiri, baris di bawahnya menjorok --}}
                    <tbody class="divide-y divide-gray-100">
                        <tr><td colspan="4" class="px-3 pb-2 pt-5 text-sm font-semibold text-gray-900">{{ $namaGrup }}</td></tr>
                        @foreach($baris as [$kunci, $nama, $tujuan, $rasio, $nilai])
                            @php $tahap = old("alur.$kunci", explode(',', $nilai)); @endphp
                            <tr>
                                <td class="py-2.5 pl-9 pr-3 font-medium text-gray-800">{{ $nama }}</td>
                                <td class="px-3 py-2.5 text-gray-600">{{ $tujuan }}</td>
                                <td class="px-3 py-2.5 text-gray-600">{{ $rasio }}</td>
                                <td class="px-3 py-2.5">
                                    <div class="flex items-center gap-2">
                                        @for($i = 0; $i < AturanAlur::TAHAP_MAKS; $i++)
                                            @php $terpilih = $tahap[$i] ?? ''; @endphp
                                            @if($i > 0)<span class="text-gray-400">→</span>@endif
                                            <select name="alur[{{ $kunci }}][{{ $i }}]" @disabled(! $tabelSiap) @required($i === 0)
                                                aria-label="Tahap {{ $i + 1 }} {{ $nama }}"
                                                data-peran="{{ $terpilih }}" onchange="this.dataset.peran = this.value"
                                                class="{{ $kelasPilih }}">
                                                @if($i > 0)<option value="" @selected($terpilih === '')>—</option>@endif
                                                @foreach(AturanAlur::PERAN as $peran)
                                                    <option value="{{ $peran }}" @selected($terpilih === $peran)>{{ $peran }}</option>
                                                @endforeach
                                            </select>
                                        @endfor
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                @endforeach
            </table>
        </div>
        <p class="mt-3 text-xs text-gray-400">Tahap kosong dilewati. Satu peran hanya boleh dipakai sekali dalam satu alur.</p>
        <div class="mt-4 flex justify-end">
            <button type="submit" @disabled(! $tabelSiap)
                class="rounded-lg bg-avian-green px-4 py-2 text-sm font-medium text-white hover:bg-avian-green-dark disabled:opacity-50">Simpan Alur</button>
        </div>
    </form>
</div>
@endsection

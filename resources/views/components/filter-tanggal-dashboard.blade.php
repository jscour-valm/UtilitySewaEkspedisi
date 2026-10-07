{{--
    Filter tanggal diajukan di atas dashboard (semua role). Rentang maks 7 hari.
    Antrian pending peran approver tetap tampil walau di luar rentang.
--}}
@php
    $rentang = \App\Helpers\RentangTanggalDashboard::dariRequest();
    $role = auth()->user()->userUtility?->role;
    $adaAntrian = in_array($role, ['WM', 'WC', 'WH', 'DCI'], true);
@endphp

<form method="GET" action="{{ url()->current() }}"
    x-data="filterTanggalDashboard('{{ $rentang->dari->toDateString() }}', '{{ $rentang->sampai->toDateString() }}', {{ \App\Helpers\RentangTanggalDashboard::MAKS_HARI }})"
    class="rounded-xl bg-white shadow-sm px-6 py-4 flex flex-wrap items-end gap-4">
    @foreach (request()->except(['dari', 'sampai']) as $kunci => $nilai)
        @if (is_string($nilai))
            <input type="hidden" name="{{ $kunci }}" value="{{ $nilai }}">
        @endif
    @endforeach

    <div>
        <label class="mb-1 block text-xs font-medium text-gray-600">Diajukan dari</label>
        <input type="date" name="dari" x-model="dari" @change="ubahDari()" :max="hariIni"
            class="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-avian-green focus:outline-none">
    </div>
    <div>
        <label class="mb-1 block text-xs font-medium text-gray-600">Sampai</label>
        <input type="date" name="sampai" x-model="sampai" @change="ubahSampai()" :max="hariIni"
            class="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-avian-green focus:outline-none">
    </div>
    <button type="submit"
        class="rounded-lg bg-avian-green px-4 py-2 text-sm font-medium text-white hover:bg-avian-green-dark">
        Terapkan
    </button>
    <a href="{{ url()->current() }}" class="py-2 text-sm text-gray-500 hover:text-avian-green hover:underline">7 hari terakhir</a>

    <p class="basis-full text-xs text-gray-500">
        Menampilkan pengajuan yang diajukan {{ $rentang->dari->translatedFormat('d M Y') }} – {{ $rentang->sampai->translatedFormat('d M Y') }} (maks {{ \App\Helpers\RentangTanggalDashboard::MAKS_HARI }} hari).
        @if ($adaAntrian)
            Antrian pending yang menunggu Anda tetap tampil semua.
        @endif
        @if ($rentang->dipotong)
            <span class="font-medium text-amber-600">Rentang dipotong jadi {{ \App\Helpers\RentangTanggalDashboard::MAKS_HARI }} hari.</span>
        @endif
    </p>
</form>

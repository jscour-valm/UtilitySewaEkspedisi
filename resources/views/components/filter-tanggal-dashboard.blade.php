{{--
    Filter periode (tanggal diajukan) di atas dashboard semua role: pill ringkas + popover kalender.
    Klik satu tanggal = mulai periode, otomatis 7 hari ke depan (maks hari ini); klik kedua di
    dalam rentang itu memendekkan periode. Antrian pending peran approver tetap tampil walau di luar periode.
--}}
@php
    $rentang = \App\Helpers\RentangTanggalDashboard::dariRequest();
    $maksHari = \App\Helpers\RentangTanggalDashboard::MAKS_HARI;
    $role = auth()->user()->userUtility?->role;
    $adaAntrian = in_array($role, ['WM', 'WC', 'WH', 'DCI'], true);
@endphp

<form method="GET" action="{{ url()->current() }}"
    x-data="filterTanggalDashboard('{{ $rentang->dari->toDateString() }}', '{{ $rentang->sampai->toDateString() }}', {{ $maksHari }})"
    @keydown.escape.window="buka = false"
    class="flex flex-wrap items-center justify-end gap-x-3 gap-y-1">
    @foreach (request()->except(['dari', 'sampai']) as $kunci => $nilai)
        @if (is_string($nilai))
            <input type="hidden" name="{{ $kunci }}" value="{{ $nilai }}">
        @endif
    @endforeach
    <input type="hidden" name="dari" :value="dari" value="{{ $rentang->dari->toDateString() }}">
    <input type="hidden" name="sampai" :value="sampai" value="{{ $rentang->sampai->toDateString() }}">

    @if ($adaAntrian || $rentang->dipotong)
        <p class="text-xs text-gray-500">
            @if ($rentang->dipotong)
                <span class="font-medium text-amber-600">Periode dipotong jadi {{ $maksHari }} hari.</span>
            @endif
            @if ($adaAntrian)
                Antrian yang menunggu Anda selalu tampil.
            @endif
        </p>
    @endif

    <div class="relative" @click.outside="buka = false">
        <button type="button" @click="toggle()"
            class="inline-flex items-center gap-2 rounded-full border border-gray-200 bg-white py-1.5 pl-3 pr-2.5 text-sm shadow-sm transition hover:border-avian-green"
            :class="buka && 'border-avian-green ring-2 ring-avian-green/20'">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-avian-green" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
            </svg>
            <span class="text-gray-500" x-text="namaPreset(dari, sampai) ?? 'Periode'">Periode</span>
            <span class="font-semibold text-gray-800" x-text="label(dari, sampai)"></span>
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-gray-400 transition" :class="buka && 'rotate-180'" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
            </svg>
        </button>

        <div x-show="buka" x-cloak x-transition.origin.top.right
            class="absolute right-0 z-30 mt-2 w-[calc(100vw-2rem)] max-w-md overflow-hidden rounded-xl border border-gray-200 bg-white shadow-xl">
            <div class="flex flex-col sm:flex-row">
                {{-- Pilihan cepat --}}
                <div class="flex gap-1 overflow-x-auto border-b border-gray-100 p-2 sm:w-36 sm:flex-col sm:border-b-0 sm:border-r">
                    <template x-for="p in preset" :key="p.nama">
                        <button type="button" @click="pilihPreset(p)"
                            class="whitespace-nowrap rounded-lg px-3 py-1.5 text-left text-sm transition"
                            :class="p.dari === pilihDari && p.sampai === pilihSampai ? 'bg-avian-green-light font-semibold text-avian-green' : 'text-gray-600 hover:bg-gray-50'"
                            x-text="p.nama"></button>
                    </template>
                </div>

                {{-- Kalender --}}
                <div class="flex-1 p-3">
                    <div class="mb-2 flex items-center justify-between">
                        <button type="button" @click="geserBulan(-1)" class="rounded-lg p-1.5 text-gray-500 hover:bg-gray-100" aria-label="Bulan sebelumnya">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" /></svg>
                        </button>
                        <span class="text-sm font-semibold text-gray-800" x-text="judulBulan"></span>
                        <button type="button" @click="geserBulan(1)" :disabled="!bisaMaju" class="rounded-lg p-1.5 text-gray-500 hover:bg-gray-100 disabled:opacity-30 disabled:hover:bg-transparent" aria-label="Bulan berikutnya">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" /></svg>
                        </button>
                    </div>
                    <div class="grid grid-cols-7 text-center text-[11px] font-medium uppercase text-gray-400">
                        <template x-for="h in ['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min']" :key="h"><span class="py-1" x-text="h"></span></template>
                    </div>
                    <div class="grid grid-cols-7 gap-y-1">
                        <template x-for="sel in hariBulan" :key="sel.tgl">
                            <div class="flex justify-center" :class="kelasRentang(sel.tgl)">
                                <button type="button" @click="klikTanggal(sel.tgl)" :disabled="sel.tgl > hariIni"
                                    class="h-8 w-8 rounded-full text-sm transition disabled:cursor-not-allowed disabled:text-gray-300"
                                    :class="kelasTanggal(sel)"
                                    x-text="sel.hari"></button>
                            </div>
                        </template>
                    </div>
                </div>
            </div>

            <div class="flex flex-wrap items-center justify-between gap-2 border-t border-gray-100 bg-gray-50 px-3 py-2.5">
                <p class="text-xs text-gray-500">
                    <span class="font-semibold text-gray-700" x-text="label(pilihDari, pilihSampai)"></span>
                    · <span x-text="jumlahHari(pilihDari, pilihSampai) + ' hari'"></span>
                    <span class="block text-[11px] text-gray-400">Klik tanggal mulai, otomatis {{ $maksHari }} hari</span>
                </p>
                <div class="flex gap-2">
                    <button type="button" @click="buka = false" class="rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-sm text-gray-600 hover:bg-gray-100">Batal</button>
                    <button type="button" @click="terapkan()" class="rounded-lg bg-avian-green px-3 py-1.5 text-sm font-medium text-white hover:bg-avian-green-dark">Terapkan</button>
                </div>
            </div>
        </div>
    </div>
</form>

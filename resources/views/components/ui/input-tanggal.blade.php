{{--
    Input satu tanggal dengan popover kalender (tampilan sama dengan filter periode dashboard).
    Nilainya string 'YYYY-MM-DD' yang diikat ke state Alpine induk.
    Dipakai di: step 2 wizard pengajuan, filter Daftar Pengajuan.
    Props: model (nama state Alpine induk, mis. "pengajuan.tanggal_pengiriman"), placeholder,
           rata (sisi popover menempel ke input: kiri | kanan).
--}}
@props(['model', 'placeholder' => 'Pilih tanggal', 'rata' => 'kiri'])

<div class="relative" x-data="inputTanggal" x-modelable="nilai" x-model="{{ $model }}"
    @click.outside="buka = false" @keydown.escape.window="buka = false">
    <button type="button" @click="toggle()"
        class="flex w-full items-center gap-2 rounded-lg border border-gray-300 bg-white px-3 py-2 text-left text-sm transition focus:border-avian-green focus:outline-none"
        :class="buka && 'border-avian-green ring-2 ring-avian-green/20'">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 shrink-0 text-avian-green" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
        </svg>
        <span class="flex-1 truncate" :class="nilai ? 'text-gray-800' : 'text-gray-400'" x-text="nilai ? label : @js($placeholder)"></span>
        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 shrink-0 text-gray-400 transition" :class="buka && 'rotate-180'" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
        </svg>
    </button>

    <div x-show="buka" x-cloak x-transition.origin.top
        class="absolute {{ $rata === 'kanan' ? 'right-0' : 'left-0' }} z-30 mt-2 w-72 overflow-hidden rounded-xl border border-gray-200 bg-white shadow-xl">
        <div class="p-3">
            <div class="mb-2 flex items-center justify-between">
                <button type="button" @click="geserBulan(-1)" class="rounded-lg p-1.5 text-gray-500 hover:bg-gray-100" aria-label="Bulan sebelumnya">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" /></svg>
                </button>
                <span class="text-sm font-semibold text-gray-800" x-text="judulBulan"></span>
                <button type="button" @click="geserBulan(1)" class="rounded-lg p-1.5 text-gray-500 hover:bg-gray-100" aria-label="Bulan berikutnya">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" /></svg>
                </button>
            </div>
            <div class="grid grid-cols-7 text-center text-[11px] font-medium uppercase text-gray-400">
                <template x-for="h in ['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min']" :key="h"><span class="py-1" x-text="h"></span></template>
            </div>
            <div class="grid grid-cols-7 gap-y-1">
                <template x-for="sel in hariBulan" :key="sel.tgl">
                    <div class="flex justify-center">
                        <button type="button" @click="pilih(sel.tgl)"
                            class="h-8 w-8 rounded-full text-sm transition"
                            :class="kelasTanggal(sel)"
                            x-text="sel.hari"></button>
                    </div>
                </template>
            </div>
        </div>
        <div class="flex items-center justify-between border-t border-gray-100 bg-gray-50 px-3 py-2">
            <button type="button" @click="pilih('')" class="text-xs text-gray-500 hover:text-gray-700">Hapus</button>
            <button type="button" @click="pilih(hariIni)" class="text-xs font-medium text-avian-green hover:underline">Hari ini</button>
        </div>
    </div>
</div>

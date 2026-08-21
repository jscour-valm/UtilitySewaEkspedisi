@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
<div
    class="flex flex-col gap-4 pb-2"
    x-data="{
        filter: 'semua',
        pengajuan: [
            { kagud: 'Budi S.',  cabang: 'Cab. Surabaya', perusahaan: 'PT Bambang',   tgl: '12 Jul 2025', harga: 'Rp 2.500.000', rasio: 2.1, status: 'pending'   },
            { kagud: 'Sari W.',  cabang: 'Cab. Surabaya', perusahaan: 'PT Maju',      tgl: '14 Jul 2025', harga: 'Rp 4.100.000', rasio: 3.2, status: 'pending'   },
            { kagud: 'Budi S.',  cabang: 'Cab. Surabaya', perusahaan: 'PT ABC',       tgl: '5 Jul 2025',  harga: 'Rp 3.200.000', rasio: 1.8, status: 'disetujui' },
            { kagud: 'Sari W.',  cabang: 'Cab. Surabaya', perusahaan: 'PT Nusantara', tgl: '1 Jul 2025',  harga: 'Rp 1.800.000', rasio: 1.5, status: 'ditolak'   },
            { kagud: 'Benny A.',  cabang: 'Cab. Surabaya', perusahaan: 'PT Sinar',     tgl: '20 Jul 2025', harga: 'Rp 2.900.000', rasio: 2.5, status: 'pending'   }
        ],
        get filtered() {
            if (this.filter === 'semua') return this.pengajuan
            return this.pengajuan.filter(p => p.status === this.filter)
        },
        get countPending()   { return this.pengajuan.filter(p => p.status === 'pending').length },
        get countDisetujui() { return this.pengajuan.filter(p => p.status === 'disetujui').length },
        get countDitolak()   { return this.pengajuan.filter(p => p.status === 'ditolak').length },
    }">

    {{-- Summary Cards — sekaligus filter --}}
    <div class="grid grid-cols-4 gap-4">

        {{-- Semua --}}
        <button
            type="button"
            @click="filter = 'semua'"
            :class="filter === 'semua'
                ? 'ring-2 ring-gray-400 bg-gray-100'
                : 'bg-white hover:bg-gray-50'"
            class="rounded-xl p-5 shadow-sm text-left transition">
            <p class="text-xs font-medium uppercase tracking-wide text-gray-400">Total Pengajuan</p>
            <p class="mt-2 text-3xl font-bold text-gray-700" x-text="pengajuan.length"></p>
            <p class="mt-1 text-xs text-gray-400">Semua status</p>
        </button>

        {{-- Pending --}}
        <button
            type="button"
            @click="filter = 'pending'"
            :class="filter === 'pending'
                ? 'ring-2 ring-yellow-400 bg-yellow-50'
                : 'bg-white hover:bg-yellow-50'"
            class="rounded-xl p-5 shadow-sm text-left transition">
            <p class="text-xs font-medium uppercase tracking-wide text-yellow-500">Pending</p>
            <p class="mt-2 text-3xl font-bold text-yellow-500" x-text="countPending"></p>
            <p class="mt-1 text-xs text-yellow-400">Menunggu review</p>
        </button>

        {{-- Disetujui --}}
        <button
            type="button"
            @click="filter = 'disetujui'"
            :class="filter === 'disetujui'
                ? 'ring-2 ring-avian-green bg-avian-green-light'
                : 'bg-white hover:bg-avian-green-light'"
            class="rounded-xl p-5 shadow-sm text-left transition">
            <p class="text-xs font-medium uppercase tracking-wide text-avian-green">Disetujui</p>
            <p class="mt-2 text-3xl font-bold text-avian-green" x-text="countDisetujui"></p>
            <p class="mt-1 text-xs text-avian-green/60">Bulan ini</p>
        </button>

        {{-- Ditolak --}}
        <button
            type="button"
            @click="filter = 'ditolak'"
            :class="filter === 'ditolak'
                ? 'ring-2 ring-red-400 bg-red-50'
                : 'bg-white hover:bg-red-50'"
            class="rounded-xl p-5 shadow-sm text-left transition">
            <p class="text-xs font-medium uppercase tracking-wide text-red-500">Ditolak</p>
            <p class="mt-2 text-3xl font-bold text-red-500" x-text="countDitolak"></p>
            <p class="mt-1 text-xs text-red-400">Bulan ini</p>
        </button>

    </div>

    {{-- Section: Pengajuan Sewa --}}
    <div class="rounded-xl bg-white shadow-sm">

        <div class="flex items-center justify-between border-b border-gray-100 px-6 py-4">
            <h2 class="text-sm font-semibold text-gray-700">
                Pengajuan Sewa
                <span
                    class="ml-2 text-xs font-normal text-gray-400"
                    x-text="filter === 'semua' ? 'Semua status' : filter.charAt(0).toUpperCase() + filter.slice(1)">
                </span>
            </h2>
        </div>

        <div class="p-6">

            {{-- Search --}}
            <input
                type="text"
                placeholder="Cari pengajuan..."
                class="mb-5 w-full rounded-lg border border-gray-300 px-4 py-2 text-sm
                       focus:border-avian-green focus:outline-none">

            {{-- Table --}}
            <div class="overflow-hidden rounded-xl border border-gray-200">
                <table class="w-full table-auto text-sm">

                    <thead>
                        <tr class="border-b text-xs font-medium uppercase tracking-wide text-gray-500">
                            <th class="px-4 py-3 text-left">
                                <button type="button" class="flex items-center gap-1 hover:text-gray-900">
                                    <span>KaGud</span>
                                    <i data-lucide="chevrons-up-down" class="h-4 w-4 text-gray-400"></i>
                                </button>
                            </th>
                            <th class="px-4 py-3 text-left">
                                <button type="button" class="flex items-center gap-1 hover:text-gray-900">
                                    <span>Perusahaan</span>
                                    <i data-lucide="chevrons-up-down" class="h-4 w-4 text-gray-400"></i>
                                </button>
                            </th>
                            <th class="px-4 py-3 text-left">
                                <button type="button" class="flex items-center gap-1 hover:text-gray-900">
                                    <span>Tgl. Pengiriman</span>
                                    <i data-lucide="chevrons-up-down" class="h-4 w-4 text-gray-400"></i>
                                </button>
                            </th>
                            <th class="px-4 py-3 text-left">
                                <button type="button" class="flex items-center gap-1 hover:text-gray-900">
                                    <span>Harga Sewa</span>
                                    <i data-lucide="chevrons-up-down" class="h-4 w-4 text-gray-400"></i>
                                </button>
                            </th>
                            <th class="px-4 py-3 text-left">
                                <button type="button" class="flex items-center gap-1 hover:text-gray-900">
                                    <span>Rasio</span>
                                    <i data-lucide="chevrons-up-down" class="h-4 w-4 text-gray-400"></i>
                                </button>
                            </th>
                            <th class="px-4 py-3 text-left">
                                <button type="button" class="flex items-center gap-1 hover:text-gray-900">
                                    <span>Status</span>
                                    <i data-lucide="chevrons-up-down" class="h-4 w-4 text-gray-400"></i>
                                </button>
                            </th>
                            <th class="px-4 py-3 text-right">Action</th>
                        </tr>
                    </thead>

                    <tbody class="divide-y text-sm">

                        <tr x-show="filtered.length === 0">
                            <td colspan="7" class="py-16 text-center text-sm text-gray-400">
                                Tidak ada pengajuan.
                            </td>
                        </tr>

                        <template x-for="(p, i) in filtered" :key="i">
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3 font-medium text-gray-800">
                                    <span x-text="p.kagud"></span>
                                    <span class="block text-xs font-normal text-gray-400" x-text="p.cabang"></span>
                                </td>
                                <td class="px-4 py-3 text-gray-600" x-text="p.perusahaan"></td>
                                <td class="px-4 py-3 text-gray-600 whitespace-nowrap" x-text="p.tgl"></td>
                                <td class="px-4 py-3 text-gray-600 whitespace-nowrap" x-text="p.harga"></td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <span
                                        :class="p.rasio > 2.5 ? 'text-red-600 font-semibold' : 'text-gray-800 font-medium'"
                                        x-text="p.rasio.toFixed(1).replace('.', ',') + '%'">
                                    </span>
                                    <span class="ml-1 text-xs text-gray-400">/ 2,5%</span>
                                </td>
                                <td class="px-4 py-3">
                                    <span
                                        :class="{
                                            'bg-yellow-100 text-yellow-700': p.status === 'pending',
                                            'bg-avian-green-light text-avian-green': p.status === 'disetujui',
                                            'bg-red-100 text-red-600': p.status === 'ditolak',
                                        }"
                                        class="rounded-full px-2 py-0.5 text-xs font-medium capitalize"
                                        x-text="p.status === 'pending' ? 'Pending' : p.status === 'disetujui' ? 'Disetujui' : 'Ditolak'">
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <button class="rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-600 hover:bg-gray-50">
                                        Detail
                                    </button>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>

            {{-- Pagination --}}
            <div class="mt-5 flex items-center justify-between">
                <span class="text-sm text-gray-500" x-text="'Menampilkan ' + filtered.length + ' dari ' + pengajuan.length + ' pengajuan'"></span>
                <div class="flex items-center gap-1">
                    <button disabled class="rounded-lg px-3 py-1.5 text-sm text-gray-400">Sebelumnya</button>
                    <button class="rounded-lg bg-avian-green px-3 py-1.5 text-sm font-medium text-white">1</button>
                    <button disabled class="rounded-lg px-3 py-1.5 text-sm text-gray-400">Berikutnya</button>
                </div>
            </div>
        </div>
    </div>

</div>
@endsection
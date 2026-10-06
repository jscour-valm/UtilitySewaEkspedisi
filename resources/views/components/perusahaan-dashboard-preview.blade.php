{{--
    Proyeksi "dashboard" dari tabel Perusahaan (tab Semua `/perusahaan`) — dipakai KG
    dashboard, gantiin `<x-tabel-kendaraan mode="dashboard">` yang lama
--}}
<div x-data="{
    rows: [],
    search: '',
    loading: false,
    async fetchPreview() {
        this.loading = true
        try {
            const params = new URLSearchParams()
            if (this.search) params.set('search', this.search)
            const res = await fetch(`{{ route('perusahaan.preview') }}?${params}`)
            const json = await res.json()
            this.rows = json.data ?? []
        } catch (e) {
            console.error('Gagal fetch preview perusahaan:', e)
        } finally {
            this.loading = false
        }
    },
}" x-init="fetchPreview()">
    <input
        type="text"
        @input.debounce.300ms="fetchPreview()"
        x-model="search"
        placeholder="Cari nama perusahaan, badan usaha, cabang, atau area..."
        class="mb-4 w-full rounded-lg border border-gray-300 px-4 py-2 text-sm
        focus:border-avian-green focus:outline-none">

    <div class="overflow-hidden rounded-xl border border-gray-200">
        <table class="w-full text-sm">
            <colgroup>
                <col class="w-[26%]">
                <col class="w-[14%]">
                <col class="w-[20%]">
                <col class="w-[18%]">
                <col class="w-[10%]">
                <col class="w-[12%]">
            </colgroup>
            <thead>
                <tr class="border-b border-gray-300 bg-gray-50 text-xs font-medium uppercase tracking-wide text-gray-500">
                    <th class="px-4 py-3 text-left">Perusahaan</th>
                    <th class="px-4 py-3 text-left">Badan Usaha</th>
                    <th class="px-4 py-3 text-left">Area Kirim</th>
                    <th class="px-4 py-3 text-left">Tarif</th>
                    <th class="px-4 py-3 text-left">Kendaraan</th>
                    <th class="px-4 py-3 text-right">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <template x-for="r in rows" :key="r.id_perusahaan">
                    <tr class="hover:bg-gray-50 transition">
                        <td class="px-4 py-3 font-medium text-gray-800">
                            <span x-text="r.nama_perusahaan || '—'"></span>
                            <span x-show="r.is_mine" class="ml-2 rounded-full bg-avian-green-light px-2 py-0.5 text-[10px] font-medium text-avian-green">Cabang Anda</span>
                        </td>
                        <td class="px-4 py-3 text-gray-600" x-text="r.badan_usaha || '—'"></td>
                        <x-cakupan-popover mode="alpine" jsVar="r" :only-area="true" />
                        <td class="px-4 py-3">
                            <div class="flex flex-wrap gap-1">
                                <span x-show="r.has_sewa" class="rounded-full bg-avian-green-light px-2 py-0.5 text-[11px] font-medium text-avian-green">Sewa Truk</span>
                                <span x-show="r.has_kiriman" class="rounded-full bg-blue-50 px-2 py-0.5 text-[11px] font-medium text-blue-600">Kiriman Rutin</span>
                                <span x-show="!r.has_sewa && !r.has_kiriman" class="rounded-full bg-gray-100 px-2 py-0.5 text-[11px] font-medium text-gray-500">Belum ada tarif</span>
                            </div>
                        </td>
                        <td class="px-4 py-3 text-gray-600" x-text="r.kendaraan_count ?? 0"></td>
                        <td class="px-4 py-3 text-right">
                            <a :href="'{{ url('/perusahaan') }}/' + r.id_perusahaan"
                                class="rounded-lg border border-avian-green px-3 py-1.5 text-xs font-medium text-avian-green transition hover:bg-avian-green-light whitespace-nowrap">
                                Detail
                            </a>
                        </td>
                    </tr>
                </template>
                <tr x-show="!loading && rows.length === 0">
                    <td colspan="6" class="py-12 text-center text-sm text-gray-400">
                        Tidak ada perusahaan yang cocok.
                    </td>
                </tr>
                <tr x-show="loading">
                    <td colspan="6" class="py-12 text-center text-sm text-gray-400">Memuat…</td>
                </tr>
            </tbody>
        </table>
    </div>
    <p class="mt-2 text-[11px] text-gray-400">Menampilkan maksimal 5 data paling cocok. <a href="{{ route('perusahaan.index') }}" class="text-avian-green hover:underline">Lihat semua perusahaan →</a></p>
</div>

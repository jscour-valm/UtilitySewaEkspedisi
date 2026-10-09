<div>
    <input
        type="text"
        @input.debounce.300ms="searchPerusahaan = $event.target.value; fetchPerusahaanList()"
        :value="searchPerusahaan"
        placeholder="Cari nama perusahaan, badan usaha, cabang, atau area..."
        class="mb-4 w-full rounded-lg border border-gray-300 px-4 py-2 text-sm
        focus:border-avian-green focus:outline-none">

    <div class="overflow-hidden rounded-xl border border-gray-200">
    <table class="w-full text-sm">
        <colgroup>
            <col class="w-[26%]">
            <col class="w-[13%]">
            <col class="w-[19%]">
            <col class="w-[17%]">
            <col class="w-[9%]">
            <col class="w-[16%]">
        </colgroup>

        <thead>
            <tr class="border-b border-gray-300 bg-gray-50 text-xs font-medium uppercase tracking-wide text-gray-500">
                <th class="px-4 py-3 text-left">Nama Perusahaan</th>
                <th class="px-4 py-3 text-left">Badan Usaha</th>
                <th class="px-4 py-3 text-left">Area Kirim</th>
                <th class="px-4 py-3 text-left">Tarif</th>
                <th class="px-4 py-3 text-left">Kendaraan</th>
                <th class="px-4 py-3 text-right">Aksi</th>
            </tr>
        </thead>

        <tbody class="divide-y divide-gray-200">
            <template x-for="p in perusahaanList" :key="p.id_perusahaan">
            <tr
                class="cursor-pointer transition hover:bg-gray-50"
                :class="(kendaraanBaru.perusahaan_id === p.id_perusahaan || perusahaanTerpilih?.id_perusahaan === p.id_perusahaan) ? 'bg-avian-green-light' : 'hover:bg-gray-50'"
                @click="pilihPerusahaan(p)">
                <td class="relative group px-4 py-3 font-medium text-gray-800">
                    <span x-text="p.nama_perusahaan || '—'"></span>
                    <span x-show="p.status_approval && p.status_approval !== 'approved'" x-cloak
                        class="mt-1 block w-fit whitespace-nowrap rounded-full bg-amber-50 px-2 py-0.5 text-[11px] font-medium text-amber-700"
                        x-text="p.status_approval === 'menunggu_approval' ? 'Vendor baru · menunggu WH' : 'Vendor baru · menunggu WM'"></span>
                    <template x-if="pengajuan.jenis_pengajuan === 'pengiriman_rutin' && p.tarif_breakdown?.length > 0">
                        <div class="hidden absolute left-0 top-full z-20 max-h-80 w-72 overflow-y-auto
                            rounded-lg border border-gray-200 bg-white p-3 text-xs font-normal
                            opacity-0 shadow-lg transition-all duration-150 transition-discrete
                            group-hover:block group-hover:opacity-100 group-hover:starting:opacity-0">
                            <p class="mb-2 font-medium text-gray-500">Tarif terdaftar:</p>
                            <template x-for="(t, i) in p.tarif_breakdown" :key="i">
                                <div class="mb-1 flex items-center justify-between last:mb-0">
                                    <span class="text-gray-600" x-text="t.nama_barang"></span>
                                    <span class="font-medium text-gray-800" x-text="'Rp ' + Number(t.harga).toLocaleString('id-ID')"></span>
                                </div>
                            </template>
                        </div>
                    </template>
                </td>
                <td class="px-4 py-3 text-gray-600" x-text="p.badan_usaha || '—'"></td>
                <x-cakupan-popover mode="alpine" jsVar="p" :only-area="true" />
                <td class="px-4 py-3">
                    <div class="flex flex-wrap gap-1">
                        <span x-show="p.has_sewa" class="rounded-full bg-avian-green-light px-2 py-0.5 text-[11px] font-medium text-avian-green">Sewa Truk</span>
                        <span x-show="p.has_kiriman" class="rounded-full bg-blue-50 px-2 py-0.5 text-[11px] font-medium text-blue-600">Kiriman Rutin</span>
                        <span x-show="!p.has_sewa && !p.has_kiriman" class="rounded-full bg-gray-100 px-2 py-0.5 text-[11px] font-medium text-gray-500">Belum ada tarif</span>
                    </div>
                </td>
                <td class="px-4 py-3 text-gray-600" x-text="p.kendaraan_count ?? 0"></td>
                <td class="px-4 py-3 text-right">
                    <button
                        type="button"
                        @click.stop="pilihPerusahaan(p)"
                        :class="(kendaraanBaru.perusahaan_id === p.id_perusahaan || perusahaanTerpilih?.id_perusahaan === p.id_perusahaan)
                            ? 'bg-avian-green text-white'
                            : 'border border-avian-green text-avian-green hover:bg-avian-green-light'"
                        class="rounded-lg px-3 py-1.5 text-xs font-medium transition whitespace-nowrap">
                        <span x-show="(kendaraanBaru.perusahaan_id !== p.id_perusahaan && perusahaanTerpilih?.id_perusahaan !== p.id_perusahaan)">Pilih</span>
                        <span x-show="(kendaraanBaru.perusahaan_id === p.id_perusahaan || perusahaanTerpilih?.id_perusahaan === p.id_perusahaan)">✓ Dipilih</span>
                    </button>
                </td>
            </tr>
            </template>
            <tr x-show="loadingPerusahaan && perusahaanList.length === 0">
                <td colspan="6" class="py-12 text-center text-sm text-gray-400">Memuat…</td>
            </tr>
            <tr x-show="!loadingPerusahaan && perusahaanList.length === 0">
                <td colspan="6" class="py-12 text-center text-sm text-gray-400">
                    Tidak ada perusahaan yang cocok. <br>
                    <span class="text-xs">Coba kata kunci lain, atau tambah perusahaan baru.</span>
                </td>
            </tr>
        </tbody>
    </table>
    </div>
    <p class="mt-2 text-[11px] text-gray-400">Menampilkan maksimal 5 data paling cocok. Persempit pencarian kalau perusahaan yang dicari belum kelihatan.</p>
</div>

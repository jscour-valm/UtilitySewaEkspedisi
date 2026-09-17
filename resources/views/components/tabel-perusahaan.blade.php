<div>
    {{-- Search Box --}}
    <input
        type="text"
        @input="searchPerusahaan = $event.target.value"
        :value="searchPerusahaan"
        placeholder="Cari nama perusahaan, badan usaha, telepon..."
        class="mb-4 w-full rounded-lg border border-gray-300 px-4 py-2 text-sm
        focus:border-avian-green focus:outline-none">

    {{-- Tabel Perusahaan untuk pilih --}}
    <div class="overflow-hidden rounded-xl border border-gray-200">
    <table class="w-full text-sm">
        <colgroup>
            <col class="w-[24%]">
            <col class="w-[13%]">
            <col class="w-[17%]">
            <col class="w-[30%]">
            <col class="w-[16%]">
        </colgroup>

        <thead>
            <tr class="border-b border-gray-300 bg-gray-50 text-xs font-medium uppercase tracking-wide text-gray-500">
                <th class="px-4 py-3 text-left">Nama Perusahaan</th>
                <th class="px-4 py-3 text-left">Badan Usaha</th>
                <th class="px-4 py-3 text-left">No. Telepon</th>
                <th class="px-4 py-3 text-left">Alamat Kantor</th>
                <th class="px-4 py-3 text-right">Aksi</th>
            </tr>
        </thead>

        <tbody class="divide-y divide-gray-200">
            <template x-for="p in perusahaanList.filter(p => {
                const searchableText = (p.nama_perusahaan + ' ' + p.badan_usaha + ' ' + p.no_telepon + ' ' + (p.alamat_kantor || '')).toLowerCase()
                return searchableText.includes(searchPerusahaan.toLowerCase())
            })" :key="p.id_perusahaan">
            <tr
                class="cursor-pointer transition hover:bg-gray-50"
                :class="(kendaraanBaru.perusahaan_id === p.id_perusahaan || perusahaanTerpilih?.id_perusahaan === p.id_perusahaan) ? 'bg-avian-green-light' : 'hover:bg-gray-50'"
                @click="kendaraanBaru.perusahaan_id = (kendaraanBaru.perusahaan_id == p.id_perusahaan) ? null : p.id_perusahaan; if (kendaraanBaru.perusahaan_id === null) { perusahaanTerpilih = null; kendaraanTerpilih = null; } else { if (perusahaanTerpilih?.id_perusahaan != p.id_perusahaan) kendaraanTerpilih = null; perusahaanTerpilih = p; }">
                <td class="relative group px-4 py-3 font-medium text-gray-800">
                    <span x-text="p.nama_perusahaan || '—'"></span>
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
                <td class="px-4 py-3 text-gray-600" x-text="p.no_telepon || '—'"></td>
                <td class="px-4 py-3 text-gray-600 truncate" :title="p.alamat_kantor" x-text="p.alamat_kantor || '—'"></td>
                <td class="px-4 py-3 text-right">
                    <button
                        type="button"
                        @click.stop="kendaraanBaru.perusahaan_id = (kendaraanBaru.perusahaan_id == p.id_perusahaan) ? null : p.id_perusahaan; if (kendaraanBaru.perusahaan_id === null) { perusahaanTerpilih = null; kendaraanTerpilih = null; } else { if (perusahaanTerpilih?.id_perusahaan != p.id_perusahaan) kendaraanTerpilih = null; perusahaanTerpilih = p; }"
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
            <tr x-show="perusahaanList.filter(p => {
                const searchableText = (p.nama_perusahaan + ' ' + p.badan_usaha + ' ' + p.no_telepon + ' ' + (p.alamat_kantor || '')).toLowerCase()
                return searchableText.includes(searchPerusahaan.toLowerCase())
            }).length === 0">
                <td colspan="5" class="py-12 text-center text-sm text-gray-400">
                    Tidak ada perusahaan yang tersedia. <br>
                    <span class="text-xs">Perusahaan akan muncul setelah ditambahkan.</span>
                </td>
            </tr>
        </tbody>
    </table>
    </div>
</div>

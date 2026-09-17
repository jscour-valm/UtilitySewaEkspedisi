{{--
    Tabel pemilihan dokumen (Surat Jalan / Transfer Antar Cabang) untuk step
    "Pilih Dokumen" pada form Pengajuan Sewa.

    Beda dari <x-tabel-kendaraan> / <x-tabel-pengajuan>: komponen ini TIDAK query
    DB langsung (tidak ada @php DB::table di sini) — datanya 100% berasal dari
    state Alpine parent (`dokumenList`, `dokumenPaged`, dst, di-fetch runtime
    lewat fetchDokumenList() di app.js). Komponen ini murni ekstraksi markup
    supaya resources/views/pages/pengajuan/step3.blade.php lebih ringkas,
    komponen ini harus dirender di dalam scope x-data="pengajuanSewa".

    Kolom SJ dan TO-ACB beda total (field aslinya juga beda dari view Quantum
    masing-masing), jadi tabelnya di-duplikat 2 varian dan dipilih pakai
    x-if berdasarkan pengajuan.tujuan_penyewaan — bukan 1 tabel dgn kolom
    generic, supaya tiap tipe nampilin field yang benar-benar relevan.
--}}

<div>
    <label class="mb-3 block text-xs font-medium text-gray-600">
        Pilih dokumen
        <span x-text="pengajuan.tujuan_penyewaan === 'PAC' ? 'TO-ACB' : pengajuan.tujuan_penyewaan === 'Toko' ? 'SJ' : 'SJ/TO-ACB'"></span>
        yang akan di muat (bisa lebih dari 1)
    </label>

    <input
        type="text"
        x-model="dokumenSearch"
        @input="dokumenPage = 1"
        placeholder="Cari nomor dokumen, nama customer, alamat, kota, atau nomor TS..."
        class="mb-3 w-full rounded-lg border border-gray-300 px-4 py-2 text-sm
        focus:border-avian-green focus:outline-none">

    <div class="overflow-hidden rounded-xl border border-gray-200">
        {{-- Varian SJ (tujuan_penyewaan = Toko) --}}
        <template x-if="pengajuan.tujuan_penyewaan !== 'PAC'">
            <table class="w-full text-sm">
                <colgroup>
                    <col class="w-10">
                    <col class="w-[16%]">
                    <col class="w-[18%]">
                    <col class="w-[24%]">
                    <col class="w-[14%]">
                    <col class="w-[14%]">
                </colgroup>
                <thead>
                    <tr class="border-b border-gray-300 bg-gray-50">
                        <th class="px-4 py-3 text-left">
                            <input
                                type="checkbox"
                                @change="e => dokumenDipilih = e.target.checked ? dokumenList.map(d => d.id) : []"
                                :checked="dokumenList.length > 0 && dokumenDipilih.length === dokumenList.length"
                                class="rounded border-gray-300">
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500">No. SJ</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500">Customer</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500">Alamat</th>
                        <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wide text-gray-500">Berat (kg)</th>
                        <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wide text-gray-500">Value (Rp)</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <template x-if="dokumenList.length === 0">
                        <tr>
                            <td colspan="6" class="py-12 text-center text-sm text-gray-400">
                                Tidak ada dokumen tersedia untuk tujuan ini.
                            </td>
                        </tr>
                    </template>
                    <template x-for="doc in dokumenPaged" :key="doc.id">
                        <tr class="hover:bg-gray-50 transition"
                            :class="[
                                dokumenDipilih.includes(doc.id) ? 'bg-avian-green-light' : '',
                                doc.skill_priority ? 'border-l-2 border-l-avian-green' : ''
                            ]">
                            <td class="px-4 py-3">
                                <input
                                    type="checkbox"
                                    @change="e => {
                                        const id = doc.id
                                        if (e.target.checked) {
                                            if (!dokumenDipilih.includes(id)) dokumenDipilih.push(id)
                                        } else {
                                            dokumenDipilih = dokumenDipilih.filter(x => x !== id)
                                        }
                                    }"
                                    :checked="dokumenDipilih.includes(doc.id)"
                                    class="rounded border-gray-300">
                            </td>
                            <td class="px-4 py-3 font-medium text-gray-800 whitespace-nowrap">
                                <span x-text="doc.nomor_dokumen"></span>
                                <span x-show="doc.skill_priority" class="ml-1.5 rounded-full bg-avian-green-light px-1.5 py-0.5 text-[10px] font-medium text-avian-green align-middle">skill cocok</span>
                            </td>
                            <td class="px-4 py-3 text-gray-600 whitespace-nowrap" x-text="doc.nama_customer || '—'"></td>
                            <td class="px-4 py-3 text-gray-600 truncate" :title="doc.alamat" x-text="doc.alamat || '—'"></td>
                            <td class="px-4 py-3 text-right text-gray-600 whitespace-nowrap" x-text="Number(doc.berat).toLocaleString('id-ID', {maximumFractionDigits: 2}) + ' kg'"></td>
                            <td class="px-4 py-3 text-right font-medium text-gray-800 whitespace-nowrap"
                                x-text="'Rp ' + Number(doc.value).toLocaleString('id-ID', {maximumFractionDigits: 2})">
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </template>

        {{-- Varian TO-ACB (tujuan_penyewaan = PAC) --}}
        <template x-if="pengajuan.tujuan_penyewaan === 'PAC'">
            <table class="w-full text-sm">
                <colgroup>
                    <col class="w-10">
                    <col class="w-[18%]">
                    <col class="w-[22%]">
                    <col class="w-[17%]">
                    <col class="w-[17%]">
                    <col class="w-[17%]">
                </colgroup>
                <thead>
                    <tr class="border-b border-gray-300 bg-gray-50">
                        <th class="px-4 py-3 text-left">
                            <input
                                type="checkbox"
                                @change="e => dokumenDipilih = e.target.checked ? dokumenList.map(d => d.id) : []"
                                :checked="dokumenList.length > 0 && dokumenDipilih.length === dokumenList.length"
                                class="rounded border-gray-300">
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500">No. TO</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500">No. TS</th>
                        <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wide text-gray-500">Gross Weight (kg)</th>
                        <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wide text-gray-500">Net Weight (kg)</th>
                        <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wide text-gray-500">Value (Rp)</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <template x-if="dokumenList.length === 0">
                        <tr>
                            <td colspan="6" class="py-12 text-center text-sm text-gray-400">
                                Tidak ada dokumen tersedia untuk tujuan ini.
                            </td>
                        </tr>
                    </template>
                    <template x-for="doc in dokumenPaged" :key="doc.id">
                        <tr class="hover:bg-gray-50 transition"
                            :class="dokumenDipilih.includes(doc.id) ? 'bg-avian-green-light' : ''">
                            <td class="px-4 py-3">
                                <input
                                    type="checkbox"
                                    @change="e => {
                                        const id = doc.id
                                        if (e.target.checked) {
                                            if (!dokumenDipilih.includes(id)) dokumenDipilih.push(id)
                                        } else {
                                            dokumenDipilih = dokumenDipilih.filter(x => x !== id)
                                        }
                                    }"
                                    :checked="dokumenDipilih.includes(doc.id)"
                                    class="rounded border-gray-300">
                            </td>
                            <td class="px-4 py-3 font-medium text-gray-800 whitespace-nowrap" x-text="doc.nomor_dokumen"></td>
                            <td class="px-4 py-3 text-gray-600 text-xs whitespace-nowrap" x-text="doc.last_shipment_no || '—'"></td>
                            <td class="px-4 py-3 text-right text-gray-600 whitespace-nowrap" x-text="Number(doc.berat).toLocaleString('id-ID', {maximumFractionDigits: 2}) + ' kg'"></td>
                            <td class="px-4 py-3 text-right text-gray-600 whitespace-nowrap" x-text="Number(doc.berat_bersih).toLocaleString('id-ID', {maximumFractionDigits: 2}) + ' kg'"></td>
                            <td class="px-4 py-3 text-right font-medium text-gray-800 whitespace-nowrap"
                                x-text="'Rp ' + Number(doc.value).toLocaleString('id-ID', {maximumFractionDigits: 2})">
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </template>
    </div>

    {{-- Pagination --}}
    <div x-show="dokumenTotalPages > 1" class="mt-3 flex items-center justify-between">
        <span class="text-xs text-gray-500"
            x-text="'Menampilkan ' + ((dokumenPage - 1) * dokumenPerPage + 1) + '–' + Math.min(dokumenPage * dokumenPerPage, dokumenList.length) + ' dari ' + dokumenList.length + ' dokumen'">
        </span>
        <div class="flex flex-wrap items-center justify-end gap-1">
            <button
                type="button"
                @click="dokumenPage--"
                :disabled="dokumenPage === 1"
                :class="dokumenPage === 1 ? 'text-gray-300 cursor-not-allowed' : 'text-gray-600 hover:bg-gray-100'"
                class="rounded-lg px-3 py-1.5 text-sm">
                Sebelumnya
            </button>
            <template x-for="(p, idx) in dokumenPageWindow" :key="idx">
                <button
                    type="button"
                    @click="p !== '…' && (dokumenPage = p)"
                    :disabled="p === '…'"
                    :class="p === '…'
                        ? 'text-gray-300 cursor-default'
                        : (dokumenPage === p ? 'bg-avian-green text-white' : 'text-gray-600 hover:bg-gray-100')"
                    class="rounded-lg px-3 py-1.5 text-sm font-medium"
                    x-text="p">
                </button>
            </template>
            <button
                type="button"
                @click="dokumenPage++"
                :disabled="dokumenPage === dokumenTotalPages"
                :class="dokumenPage === dokumenTotalPages ? 'text-gray-300 cursor-not-allowed' : 'text-gray-600 hover:bg-gray-100'"
                class="rounded-lg px-3 py-1.5 text-sm">
                Berikutnya
            </button>
        </div>
    </div>
</div>

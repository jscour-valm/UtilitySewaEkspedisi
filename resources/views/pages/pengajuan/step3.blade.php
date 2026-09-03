<div class="space-y-6">
    <h3 class="text-sm font-semibold text-gray-700">
        Pilih Dokumen
        (<span x-text="pengajuan.tujuan_penyewaan === 'PAC' ? 'TO-ACB' : pengajuan.tujuan_penyewaan === 'Toko' ? 'SJ' : 'SJ/TO-ACB'"></span>)
    </h3>

    {{-- Info Ringkas --}}
    <div class="rounded-lg bg-blue-50 border border-blue-200 px-4 py-3">
        <p class="text-xs text-blue-700">
            <span class="font-medium">Tujuan Penyewaan:</span>
            <span x-text="pengajuan.tujuan_penyewaan || '—'"></span>
            <span class="text-blue-700 mx-2">·</span>
            <span class="font-medium">Harga Sewa:</span>
            <span x-text="'Rp ' + Number(pengajuan.harga_sewa || 0).toLocaleString('id-ID')"></span>
        </p>
    </div>

    {{-- Dokumen Selection Table --}}
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
            placeholder="Cari nomor dokumen, tipe, skill, atau kategori..."
            class="mb-3 w-full rounded-lg border border-gray-300 px-4 py-2 text-sm
            focus:border-avian-green focus:outline-none">

        <div class="overflow-x-auto rounded-lg border border-gray-200">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b bg-gray-50">
                        <th class="w-10 px-4 py-3 text-left">
                            <input
                                type="checkbox"
                                @change="e => dokumenDipilih = e.target.checked ? dokumenList.map(d => d.id) : []"
                                :checked="dokumenList.length > 0 && dokumenDipilih.length === dokumenList.length"
                                class="rounded border-gray-300">
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500">No. Dokumen</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500">Tipe</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500">Skill / Area</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500">Kategori</th>
                        <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wide text-gray-500">Berat (kg)</th>
                        <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wide text-gray-500">Value (Rp)</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <template x-if="dokumenList.length === 0">
                        <tr>
                            <td colspan="7" class="py-12 text-center text-sm text-gray-400">
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
                            <td class="px-4 py-3">
                                <span
                                    :class="doc.tipe === 'SJ' ? 'bg-blue-100 text-blue-700' : 'bg-purple-100 text-purple-700'"
                                    class="inline-block px-2 py-0.5 rounded text-xs font-medium whitespace-nowrap"
                                    x-text="doc.tipe">
                                </span>
                            </td>
                            <td class="px-4 py-3 text-gray-600 text-xs whitespace-nowrap" x-text="doc.skill_nama || doc.id_skill || '—'"></td>
                            <td class="px-4 py-3 text-gray-600 text-xs whitespace-nowrap" x-text="doc.kategoriToko || '—'"></td>
                            <td class="px-4 py-3 text-right text-gray-600 whitespace-nowrap" x-text="Number(doc.berat).toLocaleString('id-ID')"></td>
                            <td class="px-4 py-3 text-right font-medium text-gray-800 whitespace-nowrap"
                                x-text="'Rp ' + Number(doc.value).toLocaleString('id-ID')">
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        <div x-show="dokumenTotalPages > 1" class="mt-3 flex items-center justify-between">
            <span class="text-xs text-gray-500"
                x-text="'Menampilkan ' + ((dokumenPage - 1) * dokumenPerPage + 1) + '–' + Math.min(dokumenPage * dokumenPerPage, dokumenList.length) + ' dari ' + dokumenList.length + ' dokumen'">
            </span>
            <div class="flex items-center gap-1">
                <button
                    type="button"
                    @click="dokumenPage--"
                    :disabled="dokumenPage === 1"
                    :class="dokumenPage === 1 ? 'text-gray-300 cursor-not-allowed' : 'text-gray-600 hover:bg-gray-100'"
                    class="rounded-lg px-3 py-1.5 text-sm">
                    Sebelumnya
                </button>
                <template x-for="p in dokumenTotalPages" :key="p">
                    <button
                        type="button"
                        @click="dokumenPage = p"
                        :class="dokumenPage === p
                            ? 'bg-avian-green text-white'
                            : 'text-gray-600 hover:bg-gray-100'"
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

    {{-- Progress Bar Kapasitas Muatan --}}
    <div x-show="armadaTerpilih && dokumenDipilih.length > 0" class="rounded-lg border border-gray-200 bg-gray-50 px-4 py-4">
        <p class="mb-3 text-xs font-medium uppercase tracking-wide text-gray-500">Perbandingan Muatan</p>
        <div class="space-y-3">
            {{-- Progress Bar --}}
            <div>
                <div class="h-2 w-full rounded-full bg-gray-200 overflow-hidden">
                    <div
                        class="h-full rounded-full transition-all duration-300"
                        :class="beratMelebihi ? 'bg-red-500' : 'bg-avian-green'"
                        :style="{ width: persentaseMuatan + '%' }">
                    </div>
                </div>
            </div>
            {{-- Info Text --}}
            <div class="flex items-center justify-between text-xs">
                <div class="space-y-1">
                    <p class="text-gray-600">
                        <span class="font-medium text-gray-700" x-text="totalBeratDipilih.toLocaleString('id-ID')"></span>
                        <span class="text-gray-500"> / </span>
                        <span class="font-medium text-gray-700" x-text="muatanMaksimalKg ? muatanMaksimalKg.toLocaleString('id-ID') : '—'"></span>
                        <span class="text-gray-500"> kg</span>
                    </p>
                    <p :class="beratMelebihi ? 'text-red-600 font-medium' : 'text-gray-600'"
                        x-text="beratMelebihi
                            ? 'Melebihi kapasitas'
                            : 'Kapasitas ' + Math.round(persentaseMuatan) + '%'">
                    </p>
                </div>
                <div class="text-right">
                    <p class="text-xl font-bold" :class="beratMelebihi ? 'text-red-600' : 'text-avian-green'" x-text="Math.round(persentaseMuatan) + '%'"></p>
                </div>
            </div>
        </div>
    </div>

    {{-- Selected Dokumen Summary --}}
    <div x-show="dokumenDipilih.length > 0" class="rounded-lg border border-avian-green/30 bg-avian-green-light px-4 py-4">
        <p class="mb-3 text-xs font-medium uppercase text-avian-green">Ringkasan Dokumen Terpilih</p>
        <div class="space-y-2 text-sm">
            <template x-for="docId in dokumenDipilih">
                <template x-if="dokumenList.find(d => d.id === docId)">
                    <div class="flex items-center justify-between">
                        <span class="text-gray-700"
                            x-text="dokumenList.find(d => d.id === docId).nomor_dokumen + ' - ' + dokumenList.find(d => d.id === docId).tipe">
                        </span>
                        <span class="font-medium text-gray-800"
                            x-text="'Rp ' + Number(dokumenList.find(d => d.id === docId).value).toLocaleString('id-ID')">
                        </span>
                    </div>
                </template>
            </template>
            <div class="border-t border-avian-green/20 pt-2 mt-2 flex items-center justify-between font-bold text-avian-green">
                <span>Total Value Muatan</span>
                <span x-text="'Rp ' + dokumenDipilih.reduce((sum, docId) => {
                    const doc = dokumenList.find(d => d.id === docId);
                    return sum + (doc ? Number(doc.value) : 0);
                }, 0).toLocaleString('id-ID')">
                </span>
            </div>
            <div class="border-t border-avian-green/20 pt-2 mt-2 flex items-center justify-between text-avian-green/70 text-xs">
                <span>Total Berat</span>
                <span x-text="dokumenDipilih.reduce((sum, docId) => {
                    const doc = dokumenList.find(d => d.id === docId);
                    return sum + (doc ? Number(doc.berat) : 0);
                }, 0).toLocaleString('id-ID') + ' kg'">
                </span>
            </div>
        </div>
    </div>

    {{-- Preview Rasio Kalkulasi --}}
    <div class="rounded-lg border border-gray-200 bg-gray-50 px-4 py-4">
        <p class="mb-3 text-xs font-medium uppercase tracking-wide text-gray-400">Preview Rasio Sewa</p>
        <div class="space-y-3 text-sm">
            <div class="flex items-center justify-between">
                <span class="text-gray-600">Harga Sewa</span>
                <span class="font-medium text-gray-800"
                    x-text="'Rp ' + Number(pengajuan.harga_sewa || 0).toLocaleString('id-ID')"></span>
            </div>
            <div x-show="biayaTambahan.length === 0" class="flex items-center justify-between">
                <span class="text-gray-600">Biaya Tambahan</span>
                <span class="font-medium text-gray-800"
                    x-text="'Rp ' + totalBiayaTambahan.toLocaleString('id-ID')"></span>
            </div>
            <div x-show="biayaTambahan.length > 0" class="space-y-1">
                <div class="flex items-center justify-between">
                    <span class="text-gray-600">Biaya Tambahan</span>
                    <span class="font-medium text-gray-800"
                        x-text="'Rp ' + totalBiayaTambahan.toLocaleString('id-ID')"></span>
                </div>
                <template x-for="b in biayaTambahan" :key="b.id_jenis_biaya">
                    <div class="flex items-center justify-between pl-4 text-xs text-gray-500">
                        <span x-text="jenisBiayaList.find(j => j.id_jenis_biaya == b.id_jenis_biaya)?.nama_biaya || '—'"></span>
                        <span x-text="'Rp ' + Number(b.nominal || 0).toLocaleString('id-ID')"></span>
                    </div>
                </template>
            </div>
            <div class="flex items-center justify-between">
                <span class="text-gray-600 font-medium">Total Biaya</span>
                <span class="font-semibold text-gray-900"
                    x-text="'Rp ' + totalDenganBiayaTambahan.toLocaleString('id-ID')"></span>
            </div>
            <div class="flex items-center justify-between">
                <span class="text-gray-600">Value Muatan (Total)</span>
                <span class="font-medium text-gray-800"
                    x-text="dokumenDipilih.length > 0
                        ? 'Rp ' + dokumenDipilih.reduce((sum, docId) => {
                            const doc = dokumenList.find(d => d.id === docId);
                            return sum + (doc ? Number(doc.value) : 0);
                        }, 0).toLocaleString('id-ID')
                        : (editId && pengajuan.value_muatan
                            ? 'Rp ' + Number(pengajuan.value_muatan).toLocaleString('id-ID') + ' (dari DB)'
                            : '—')">
                </span>
            </div>
            <div class="flex items-center justify-between">
                <span class="text-gray-600">Jumlah Toko</span>
                <span class="font-medium text-gray-800"
                    x-text="jumlahTokoDipilih === null ? '-' : jumlahTokoDipilih + ' toko'">
                </span>
            </div>
            <div class="border-t border-gray-200 pt-3 flex items-center justify-between">
                <span class="text-gray-700 font-medium">Rasio Sewa (Total Biaya / Value Muatan)</span>
                <span class="text-lg font-bold text-avian-green"
                    x-text="(dokumenDipilih.length > 0 && totalDenganBiayaTambahan
                        ? ((totalDenganBiayaTambahan / dokumenDipilih.reduce((sum, docId) => {
                            const doc = dokumenList.find(d => d.id === docId);
                            return sum + (doc ? Number(doc.value) : 0);
                        }, 0)) * 100).toFixed(2) + '%'
                        : (editId && pengajuan.value_muatan && totalDenganBiayaTambahan
                            ? ((totalDenganBiayaTambahan / Number(pengajuan.value_muatan)) * 100).toFixed(2) + '% (dari DB)'
                            : '—'))">
                </span>
            </div>
        </div>
        <div class="mt-3 pt-3 space-y-2">
            <div class="flex items-center justify-between">
                <span class="text-xs text-gray-600">Skill / Area:</span>
                <span class="text-xs font-medium text-gray-800" x-text="skillGabungan.join(', ') || '—'"></span>
            </div>
            <div class="flex items-center justify-between">
                <span class="text-xs text-gray-600">Kategori Toko:</span>
                <span class="text-xs font-medium text-gray-800" x-text="pengajuan.kategoriToko || '—'"></span>
            </div>
        </div>
        <div class="mt-3 pt-3 border-t border-gray-200">
            <p class="text-xs text-gray-600">
                <span class="font-medium">Kategori Rasio Sewa:</span>
                <template x-if="editId && !dokumenDipilih.length && pengajuan.value_muatan">
                    <span x-show="totalDenganBiayaTambahan && ((totalDenganBiayaTambahan / Number(pengajuan.value_muatan)) * 100) <= 2.5"
                        class="text-blue-600 font-medium">Normal ✓ (dari DB)</span>
                    <span x-show="totalDenganBiayaTambahan && ((totalDenganBiayaTambahan / Number(pengajuan.value_muatan)) * 100) > 2.5"
                        class="text-orange-600 font-medium">Over Threshold ⚠️ (dari DB)</span>
                </template>
                <template x-if="!editId || dokumenDipilih.length">
                    <span x-show="!dokumenDipilih.length || !totalDenganBiayaTambahan" class="text-gray-400">— (pilih dokumen dulu)</span>
                    <span x-show="dokumenDipilih.length > 0 && totalDenganBiayaTambahan && ((totalDenganBiayaTambahan / dokumenDipilih.reduce((sum, docId) => {
                        const doc = dokumenList.find(d => d.id === docId);
                        return sum + (doc ? Number(doc.value) : 0);
                    }, 0)) * 100) <= 2.5"
                        class="text-blue-600 font-medium">Normal ✓</span>
                    <span x-show="dokumenDipilih.length > 0 && totalDenganBiayaTambahan && ((totalDenganBiayaTambahan / dokumenDipilih.reduce((sum, docId) => {
                        const doc = dokumenList.find(d => d.id === docId);
                        return sum + (doc ? Number(doc.value) : 0);
                    }, 0)) * 100) > 2.5"
                        class="text-orange-600 font-medium">Over Threshold ⚠️</span>
                </template>
            </p>
        </div>
    </div>

    {{-- Navigasi --}}
    {{-- Alert berat melebihi kapasitas --}}
    <div x-show="beratMelebihi"
        class="rounded-lg bg-red-50 border border-red-200 px-4 py-3 flex items-start gap-3">
        <i data-lucide="alert-triangle" class="w-4 h-4 text-red-500 shrink-0 mt-0.5"></i>
        <div class="text-xs text-red-700">
            <p class="font-semibold mb-1">Berat muatan melebihi kapasitas armada!</p>
            <p>
                Total berat dokumen terpilih:
                <span class="font-medium" x-text="totalBeratDipilih.toLocaleString('id-ID') + ' kg'"></span>
            </p>
            <p>
                Kapasitas armada:
                <span class="font-medium" x-text="muatanMaksimalKg ? muatanMaksimalKg.toLocaleString('id-ID') + ' kg' : '—'"></span>
            </p>
            <p class="mt-1">Kurangi dokumen yang dipilih atau ganti armada dengan kapasitas lebih besar.</p>
        </div>
    </div>

    {{-- Navigasi --}}
    <div class="flex items-center justify-between">
        <button
            type="button"
            @click="goToStep(2)"
            class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50">
            ← Kembali
        </button>
        <button
            type="button"
            @click="goToStep(4)"
            :disabled="(dokumenDipilih.length === 0 && !(editId && pengajuan.value_muatan)) || beratMelebihi"
            :title="tooltipStep3"
            :class="((dokumenDipilih.length > 0 || (editId && pengajuan.value_muatan)) && !beratMelebihi)
                ? 'bg-avian-green text-white hover:bg-avian-green-dark'
                : 'bg-gray-100 text-gray-400 cursor-not-allowed'"
            class="rounded-lg px-4 py-2 text-sm font-medium transition">
            Lanjut →
        </button>
    </div>
</div>
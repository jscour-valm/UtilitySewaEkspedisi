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
    <x-tabel-dokumen-pengajuan />

    {{-- Progress Bar Kapasitas Muatan --}}
    <div x-show="kendaraanTerpilih && dokumenDipilih.length > 0" class="rounded-lg border border-gray-200 bg-gray-50 px-4 py-4">
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
        <p class="mb-3 text-xs font-medium uppercase text-avian-green">
            Ringkasan Dokumen Terpilih (<span x-text="dokumenDipilihResolved.length"></span>)
        </p>
        <div class="space-y-2 text-sm">
            <template x-for="doc in (ringkasanDokumenExpanded ? dokumenDipilihResolved : dokumenDipilihResolved.slice(0, 5))" :key="doc.id">
                <div class="flex items-center justify-between">
                    <span class="text-gray-700" x-text="doc.nomor_dokumen"></span>
                    <span class="font-medium text-gray-800" x-text="'Rp ' + Number(doc.value).toLocaleString('id-ID')"></span>
                </div>
            </template>
            <button type="button" x-show="dokumenDipilihResolved.length > 5"
                @click="ringkasanDokumenExpanded = !ringkasanDokumenExpanded"
                class="text-xs font-medium text-avian-green hover:underline"
                x-text="ringkasanDokumenExpanded ? 'Tampilkan lebih sedikit' : 'Lihat semua (' + dokumenDipilihResolved.length + ')'">
            </button>
            <div class="border-t border-avian-green/20 pt-2 mt-2 flex items-center justify-between font-bold text-avian-green">
                <span>Total Value Muatan</span>
                <span x-text="'Rp ' + valueMuatanDipilih.toLocaleString('id-ID')"></span>
            </div>
            <div class="border-t border-avian-green/20 pt-2 mt-2 flex items-center justify-between text-avian-green/70 text-xs">
                <span>Total Berat</span>
                <span x-text="totalBeratDipilih.toLocaleString('id-ID') + ' kg'"></span>
            </div>
        </div>
    </div>

    {{-- Alert berat melebihi kapasitas --}}
    <div x-show="beratMelebihi"
        class="rounded-lg bg-red-50 border border-red-200 px-4 py-3 flex items-start gap-3">
        <i data-lucide="alert-triangle" class="w-4 h-4 text-red-500 shrink-0 mt-0.5"></i>
        <div class="text-xs text-red-700">
            <p class="font-semibold mb-1">Berat muatan melebihi kapasitas kendaraan!</p>
            <p>
                Total berat dokumen terpilih:
                <span class="font-medium" x-text="totalBeratDipilih.toLocaleString('id-ID') + ' kg'"></span>
            </p>
            <p>
                Kapasitas kendaraan:
                <span class="font-medium" x-text="muatanMaksimalKg ? muatanMaksimalKg.toLocaleString('id-ID') + ' kg' : '—'"></span>
            </p>
            <p class="mt-1">Kurangi dokumen yang dipilih atau ganti kendaraan dengan kapasitas lebih besar.</p>
        </div>
    </div>

    <div class="mt-3 border-t border-gray-200 pt-3">
        <div class="flex items-center justify-between flex-wrap gap-2">
            <div>
                <p class="text-xs text-gray-400">Estimasi Rasio Sewa</p>
                <p class="mt-0.5 text-lg font-bold text-gray-800"
                    x-text="(rasioSewaEstimasi !== null ? rasioSewaEstimasi.toFixed(2) + '%' : '—') + ' / 2,5%'">
                </p>
            </div>
            <template x-if="pengajuan.tujuan_penyewaan === 'PAC'">
                <span class="inline-block px-3 py-1 rounded-lg bg-orange-100 text-orange-700 text-xs font-semibold">
                    ⚠️ OVER THRESHOLD — Tujuan PAC
                </span>
            </template>
            <template x-if="pengajuan.tujuan_penyewaan !== 'PAC'">
                <span
                    x-show="rasioSewaEstimasi !== null && rasioSewaEstimasi <= 2.5"
                    class="inline-block px-3 py-1 rounded-lg bg-blue-100 text-blue-700 text-xs font-semibold">
                    ✓ NORMAL
                </span>
            </template>
            <template x-if="pengajuan.tujuan_penyewaan !== 'PAC'">
                <span
                    x-show="rasioSewaEstimasi !== null && rasioSewaEstimasi > 2.5"
                    class="inline-block px-3 py-1 rounded-lg bg-orange-100 text-orange-700 text-xs font-semibold">
                    ⚠️ OVER THRESHOLD — Rasio > 2.5%
                </span>
            </template>
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
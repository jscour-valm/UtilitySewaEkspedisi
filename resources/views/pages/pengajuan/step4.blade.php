<div class="space-y-6">

    <h3 class="text-sm font-semibold text-gray-700">Review & Submit</h3>

    {{-- Alert Info --}}
    <div class="rounded-lg bg-blue-50 border border-blue-200 px-4 py-3">
        <p class="text-xs text-blue-700">
            <span class="font-medium">Periksa kembali data Anda sebelum submit.</span>
            Setelah submit, pengajuan akan masuk ke proses approval.
        </p>
    </div>

    {{-- Summary Cards --}}
    <div class="grid grid-cols-2 gap-4">

        {{-- Armada --}}
        <div class="rounded-lg border border-gray-200 p-4 bg-gray-50">
            <p class="mb-3 text-xs font-medium text-gray-500 uppercase">Ekspedisi Dipilih</p>
            <p class="mb-1 text-sm font-semibold text-gray-800" x-text="armadaTerpilih?.nama"></p>
            <p class="text-xs text-gray-600" x-text="armadaTerpilih?.kendaraan"></p>
            <p class="mt-2 text-xs text-gray-500">
                <span class="font-medium">Muatan Max:</span>
                <span x-text="armadaTerpilih?.muatan"></span>
            </p>
        </div>

        {{-- Tanggal & Harga --}}
        <div class="rounded-lg border border-gray-200 p-4 bg-gray-50">
            <p class="mb-3 text-xs font-medium text-gray-500 uppercase">Tanggal & Harga</p>
            <p class="text-xs text-gray-600 mb-1">
                <span class="font-medium">Pengiriman:</span>
                <span x-text="formatTanggalID(pengajuan.tanggal_pengiriman)"></span>
            </p>
            <p class="text-xs text-gray-600 mb-1">
                <span class="font-medium">Harga Sewa:</span>
                <span x-text="'Rp ' + Number(pengajuan.harga_sewa || 0).toLocaleString('id-ID')"></span>
            </p>
            <p class="text-xs text-gray-600">
                <span class="font-medium">Value Muatan:</span>
                <span x-text="'Rp ' + dokumenDipilih.reduce((sum, docId) => {
                    const doc = dokumenList.find(d => d.id === docId);
                    return sum + (doc ? Number(doc.value) : 0);
                }, 0).toLocaleString('id-ID')"></span>
            </p>
        </div>

        {{-- Tujuan & Skill --}}
        <div class="rounded-lg border border-gray-200 p-4 bg-gray-50">
            <p class="mb-3 text-xs font-medium text-gray-500 uppercase">Tujuan & Area Pengantaran</p>
            <p class="text-xs text-gray-600 mb-1">
                <span class="font-medium">Tujuan Penyewaan:</span>
                <span x-text="pengajuan.tujuan_penyewaan"></span>
            </p>
            <p class="text-xs text-gray-600 mb-1">
                <span class="font-medium">Skill / Area:</span>
                <span x-text="skillGabungan.join(', ') || '—'"></span>
            </p>
            <p class="text-xs text-gray-600 mb-1">
                <span class="font-medium">Kategori Toko:</span>
                <span x-text="pengajuan.kategoriToko"></span>
            </p>
            <p class="text-xs text-gray-600">
                <span class="font-medium">Jumlah Toko:</span>
                <span x-text="jumlahTokoDipilih === null ? '-' : jumlahTokoDipilih + ' toko'"></span>
            </p>
        </div>

        {{-- Total Biaya --}}
        <div class="rounded-lg border border-gray-200 p-4 bg-gray-50">
            <p class="mb-3 text-xs font-medium text-gray-500 uppercase">Rincian Biaya</p>
            <p class="text-sm font-bold text-avian-green"
                x-text="'Rp ' + (Number(pengajuan.harga_sewa || 0) + biayaTambahan.reduce((s, b) => s + (Number(b.nominal) || 0), 0)).toLocaleString('id-ID')">
            </p>
            <div x-show="biayaTambahan.length > 0" class="mt-2 space-y-1">
                <template x-for="b in biayaTambahan" :key="b.id_jenis_biaya">
                    <p class="text-xs text-gray-500 flex justify-between">
                        <span x-text="jenisBiayaList.find(j => j.id_jenis_biaya == b.id_jenis_biaya)?.nama_biaya || '—'"></span>
                        <span x-text="'Rp ' + Number(b.nominal || 0).toLocaleString('id-ID')"></span>
                    </p>
                </template>
            </div>
            <p x-show="biayaTambahan.length === 0" class="mt-1 text-xs text-gray-400">Tidak ada biaya tambahan</p>
        </div>

        {{-- Dokumen Terpilih --}}
        <div class="rounded-lg border border-gray-200 p-4 bg-gray-50 col-span-2">
            <p class="mb-3 text-xs font-medium text-gray-500 uppercase">
                Dokumen Terpilih
                (<span x-text="dokumenDipilih.length"></span> dokumen)
            </p>
            <p x-show="dokumenDipilih.length === 0" class="text-xs text-gray-400">Tidak ada dokumen</p>
            <div x-show="dokumenDipilih.length > 0" class="divide-y divide-gray-100">
                <template x-for="docId in dokumenDipilih" :key="docId">
                    <template x-if="dokumenList.find(d => d.id === docId)">
                        <div class="flex items-center justify-between py-1.5">
                            <div class="flex items-center gap-2">
                                <span
                                    :class="dokumenList.find(d => d.id === docId).tipe === 'SJ' ? 'bg-blue-100 text-blue-700' : 'bg-purple-100 text-purple-700'"
                                    class="inline-block px-1.5 py-0.5 rounded text-xs font-medium"
                                    x-text="dokumenList.find(d => d.id === docId).tipe">
                                </span>
                                <span class="text-xs font-medium text-gray-700"
                                    x-text="dokumenList.find(d => d.id === docId).nomor_dokumen">
                                </span>
                            </div>
                            <span class="text-xs text-gray-600"
                                x-text="'Rp ' + Number(dokumenList.find(d => d.id === docId).value).toLocaleString('id-ID')">
                            </span>
                        </div>
                    </template>
                </template>
                <div class="flex justify-between pt-2 mt-1">
                    <span class="text-xs font-semibold text-gray-700">Total Value Muatan</span>
                    <span class="text-xs font-semibold text-avian-green"
                        x-text="'Rp ' + dokumenDipilih.reduce((sum, docId) => {
                            const doc = dokumenList.find(d => d.id === docId);
                            return sum + (doc ? Number(doc.value) : 0);
                        }, 0).toLocaleString('id-ID')">
                    </span>
                </div>
            </div>
        </div>

    </div>

    {{-- Progress Bar Kapasitas Muatan --}}
    <div class="rounded-lg border border-gray-200 bg-gray-50 px-4 py-4">
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

    {{-- Rasio Sewa & Kategori --}}
    <div class="rounded-lg border-2 border-avian-green/30 bg-avian-green-light px-4 py-4">
        <p class="mb-4 text-xs font-medium uppercase tracking-wide text-avian-green">Perhitungan Rasio Sewa</p>
        <div class="grid grid-cols-3 gap-4">
            <div>
                <p class="text-xs text-avian-green/70 mb-1">Harga Sewa</p>
                <p class="text-sm font-semibold text-avian-green"
                    x-text="'Rp ' + Number(pengajuan.harga_sewa || 0).toLocaleString('id-ID')">
                </p>
            </div>
            <div>
                <p class="text-xs text-avian-green/70 mb-1">Value Muatan</p>
                <p class="text-sm font-semibold text-avian-green"
                    x-text="'Rp ' + dokumenDipilih.reduce((sum, docId) => {
                        const doc = dokumenList.find(d => d.id === docId);
                        return sum + (doc ? Number(doc.value) : 0);
                    }, 0).toLocaleString('id-ID')">
                </p>
            </div>
            <div>
                <p class="text-xs text-avian-green/70 mb-1">Rasio</p>
                <p class="text-lg font-bold text-avian-green"
                    x-text="dokumenDipilih.reduce((sum, docId) => {
                        const doc = dokumenList.find(d => d.id === docId);
                        return sum + (doc ? Number(doc.value) : 0);
                    }, 0) > 0
                        ? ((Number(pengajuan.harga_sewa || 0) / dokumenDipilih.reduce((sum, docId) => {
                            const doc = dokumenList.find(d => d.id === docId);
                            return sum + (doc ? Number(doc.value) : 0);
                        }, 0)) * 100).toFixed(2) + '%'
                        : '—'">
                </p>
            </div>
        </div>
        <div class="mt-4 pt-4 border-t border-avian-green/20">
            <p class="text-xs font-medium text-avian-green/70 mb-2">KATEGORI RASIO SEWA</p>
            <div class="flex items-center gap-2 flex-wrap">
                {{-- PAC: selalu over threshold, tapi rasio tetap dihitung --}}
                <template x-if="pengajuan.tujuan_penyewaan === 'PAC'">
                    <span class="inline-block px-3 py-1 rounded-lg bg-orange-100 text-orange-700 text-xs font-semibold">
                        ⚠️ OVER THRESHOLD — Tujuan PAC
                    </span>
                </template>
                {{-- Toko: cek rasio --}}
                <template x-if="pengajuan.tujuan_penyewaan !== 'PAC'">
                    <span
                        x-show="dokumenDipilih.reduce((s,id) => { const d=dokumenList.find(x=>x.id===id); return s+(d?Number(d.value):0); },0) > 0
                            && ((Number(pengajuan.harga_sewa||0) / dokumenDipilih.reduce((s,id) => { const d=dokumenList.find(x=>x.id===id); return s+(d?Number(d.value):0); },0)) * 100) <= 2.5"
                        class="inline-block px-3 py-1 rounded-lg bg-blue-100 text-blue-700 text-xs font-semibold">
                        ✓ NORMAL
                    </span>
                </template>
                <template x-if="pengajuan.tujuan_penyewaan !== 'PAC'">
                    <span
                        x-show="dokumenDipilih.reduce((s,id) => { const d=dokumenList.find(x=>x.id===id); return s+(d?Number(d.value):0); },0) > 0
                            && ((Number(pengajuan.harga_sewa||0) / dokumenDipilih.reduce((s,id) => { const d=dokumenList.find(x=>x.id===id); return s+(d?Number(d.value):0); },0)) * 100) > 2.5"
                        class="inline-block px-3 py-1 rounded-lg bg-orange-100 text-orange-700 text-xs font-semibold">
                        ⚠️ OVER THRESHOLD — Rasio > 2.5%
                    </span>
                </template>
            </div>
            <p class="mt-2 text-xs text-avian-green/70">
                Threshold standar rasio sewa adalah <span class="font-semibold">2.5%</span>.
            </p>
        </div>
    </div>

    {{-- Catatan (jika ada) --}}
    <div x-show="pengajuan.catatan" class="rounded-lg border border-gray-200 p-4 bg-gray-50">
        <p class="mb-2 text-xs font-medium text-gray-600">Catatan</p>
        <p class="text-sm text-gray-700" x-text="pengajuan.catatan"></p>
    </div>

    {{-- Navigasi --}}
    <div class="flex items-center justify-between pt-4 border-t border-gray-200">
        <button
            type="button"
            @click="goToStep(3)"
            class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50">
            ← Kembali
        </button>
        <button
            type="button"
            @click="submitPengajuan()"
            :disabled="submitting"
            :class="submitting ? 'bg-gray-300 cursor-not-allowed' : 'bg-avian-green text-white hover:bg-avian-green-dark'"
            class="rounded-lg px-6 py-2 text-sm font-medium transition">
            <span x-show="!submitting">✓ Submit Pengajuan</span>
            <span x-show="submitting">Mengirim...</span>
        </button>
    </div>
</div>
<div class="space-y-6">
    <h3 class="text-sm font-semibold text-gray-700">Data Pengajuan</h3>

    {{-- Info Terpilih (Kendaraan atau Vendor) --}}
    <template x-if="pengajuan.jenis_pengajuan === 'sewa_truk'">
        <div class="rounded-lg bg-avian-green-light border border-avian-green/20 px-4 py-3 flex items-center gap-4">
            <i data-lucide="truck" class="w-4 h-4 text-avian-green shrink-0"></i>
            <div class="text-sm">
                <span class="font-semibold text-avian-green" x-text="kendaraanTerpilih?.nama"></span>
                <span class="text-avian-green/70 mx-1">·</span>
                <span class="text-avian-green/70" x-text="kendaraanTerpilih?.kendaraan"></span>
                <span class="text-avian-green/70 mx-1">·</span>
                <span class="text-avian-green/70" x-text="kendaraanTerpilih?.harga"></span>
                <span class="text-avian-green/70 mx-1">·</span>
                <span class="text-avian-green/70" x-text="kendaraanTerpilih?.update_at"></span>
            </div>
            <button type="button" @click="goToStep(1)"
                x-show="!editId"
                class="ml-auto text-xs text-avian-green underline hover:no-underline">
                Ganti
            </button>
        </div>
    </template>

    <template x-if="pengajuan.jenis_pengajuan === 'pengiriman_rutin'">
        <div class="rounded-lg bg-avian-green-light border border-avian-green/20 px-4 py-3 flex items-center gap-4">
            <i data-lucide="package" class="w-4 h-4 text-avian-green shrink-0"></i>
            <div class="text-sm">
                <span class="font-semibold text-avian-green" x-text="perusahaanTerpilih?.nama_perusahaan"></span>
            </div>
            <button type="button" @click="goToStep(1)"
                x-show="!editId"
                class="ml-auto text-xs text-avian-green underline hover:no-underline">
                Ganti
            </button>
        </div>
    </template>

    {{-- Data Vendor sekarang diisi/dikoreksi di mini-stepper step1 sub-step 2
         (<x-form-vendor-edit /> di step1.blade.php) — dipindah ke situ biar user
         koreksi dari awal, bukan didobelin muncul lagi di sini. --}}

    {{-- Shared Form Grid --}}
    <div class="grid grid-cols-2 gap-5">
        {{-- Tanggal Pengiriman (SHARED) --}}
        <div>
            <label class="mb-1 block text-xs font-medium text-gray-600">
                Tanggal Pengiriman <span class="text-red-500">*</span>
            </label>
            <input
                type="date"
                x-model="pengajuan.tanggal_pengiriman"
                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-avian-green focus:outline-none">
        </div>

        {{-- Harga Sewa (SEWA TRUK ONLY) --}}
        <div x-show="pengajuan.jenis_pengajuan === 'sewa_truk'">
            <label class="mb-1 block text-xs font-medium text-gray-600">
                Harga Sewa (Rp) <span class="text-red-500">*</span>
            </label>
            <input
                type="text"
                inputmode="numeric"
                :value="formatRibuan(pengajuan.harga_sewa)"
                @input="pengajuan.harga_sewa = parseRibuan($event.target.value)"
                placeholder="Contoh: 2.500.000"
                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-avian-green focus:outline-none">
        </div>

        {{-- Tujuan Penyewaan (SHARED) --}}
        <div>
            <label class="mb-1 block text-xs font-medium text-gray-600">
                Tujuan Penyewaan <span class="text-red-500">*</span>
            </label>
            <div class="relative">
                <select
                    x-model="pengajuan.tujuan_penyewaan"
                    class="w-full appearance-none rounded-lg border border-gray-300 px-3 py-2 pr-9 text-sm focus:border-avian-green focus:outline-none">
                    <option value="">-- Pilih --</option>
                    <option value="Toko">Toko</option>
                    <option value="PAC">PAC</option>
                </select>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                    class="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400">
                    <path d="m6 9 6 6 6-6" />
                </svg>
            </div>
        </div>

        {{-- Kategori Toko (SHARED) --}}
        <div>
            <label class="mb-1 block text-xs font-medium text-gray-600">
                Kategori Toko <span class="text-red-500">*</span>
            </label>
            <div class="relative">
                <select
                    x-model="pengajuan.kategoriToko"
                    x-init="$nextTick(() => { $el.value = pengajuan.kategoriToko })"
                    class="w-full appearance-none rounded-lg border border-gray-300 px-3 py-2 pr-9 text-sm focus:border-avian-green focus:outline-none">
                    <option value="">-- Pilih Kategori --</option>
                    <template x-for="k in kategoriTokoList" :key="k">
                        <option :value="k" x-text="k"></option>
                    </template>
                </select>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                    class="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400">
                    <path d="m6 9 6 6 6-6" />
                </svg>
            </div>
        </div>

        {{-- Skill / Area (SHARED - visible for both jenis) --}}
        <div class="col-span-2">
            <label class="mb-1 block text-xs font-medium text-gray-600">
                Skill / Area Pengantaran <span class="text-red-500">*</span>
            </label>

            <div class="grid grid-cols-2 gap-4">
                {{-- Kiri: Checklist --}}
                <div class="rounded-lg border border-gray-300 bg-white px-3 py-2"
                    :class="skillLocked ? 'opacity-50 pointer-events-none' : ''">
                    <template x-if="skillList.length === 0">
                        <p class="text-xs text-gray-400 py-1">Memuat daftar area...</p>
                    </template>
                    <template x-for="s in skillList" :key="s.id_skill">
                        <label class="flex items-center gap-2 cursor-pointer py-1 hover:bg-gray-50 px-1 rounded">
                            <input
                                type="checkbox"
                                :value="s.id_skill"
                                x-model="pengajuan.id_skill"
                                class="rounded border-gray-300 text-avian-green focus:ring-avian-green">
                            <span class="text-sm text-gray-700" x-text="s.nama_skill"></span>
                        </label>
                    </template>
                </div>

                {{-- Kanan --}}
                <div class="flex flex-col gap-3">
                    {{-- Summary dipilih --}}
                    <div class="rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 min-h-20">
                        <p class="mb-2 text-xs font-medium text-gray-500">Area Dipilih</p>
                        <div class="flex flex-wrap gap-1.5">
                            <template x-if="pengajuan.id_skill.length === 0 && skillBaru.filter(s => s.trim() !== '').length === 0">
                                <p class="text-xs text-gray-400">Belum ada area dipilih.</p>
                            </template>
                            <template x-for="s in pengajuan.id_skill" :key="s">
                                <span class="inline-flex items-center gap-1 rounded-full bg-avian-green-light px-2.5 py-0.5 text-xs font-medium text-avian-green">
                                    <span x-text="skillList.find(sk => String(sk.id_skill) === String(s))?.nama_skill ?? s"></span>
                                    <button
                                        x-show="!skillLocked"
                                        type="button"
                                        @click="pengajuan.id_skill = pengajuan.id_skill.filter(x => x !== s)"
                                        class="hover:text-avian-green-dark leading-none">×</button>
                                </span>
                            </template>
                            <template x-for="(s, i) in skillBaru.filter(s => s.trim() !== '')" :key="'baru-'+i">
                                <span class="inline-flex items-center gap-1 rounded-full bg-blue-50 px-2.5 py-0.5 text-xs font-medium text-blue-600">
                                    <span x-text="s"></span>
                                    <span class="text-blue-400 text-[10px]">baru</span>
                                </span>
                            </template>
                        </div>
                    </div>

                    {{-- Tambah Area Baru --}}
                    <div :class="skillLocked ? 'opacity-50 pointer-events-none' : ''">
                        <div class="mb-2 flex items-center justify-between">
                            <span class="text-xs font-medium text-gray-600">Tambah Area Baru</span>
                            <button
                                type="button"
                                @click="skillBaru.push('')"
                                class="rounded-lg border border-avian-green px-3 py-1 text-xs font-medium text-avian-green hover:bg-avian-green-light transition">
                                + Tambah
                            </button>
                        </div>
                        <div class="space-y-2">
                            <template x-for="(s, i) in skillBaru" :key="i">
                                <div class="flex items-center gap-2">
                                    <input
                                        type="text"
                                        x-model="skillBaru[i]"
                                        placeholder="Contoh: ACKOT CDE"
                                        class="flex-1 rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-avian-green focus:outline-none">
                                    <button
                                        type="button"
                                        @click="skillBaru.splice(i, 1)"
                                        class="rounded-lg border border-red-200 px-2 py-2 text-red-400 hover:bg-red-50 transition">
                                        <i data-lucide="trash-2" class="w-4 h-4"></i>
                                    </button>
                                </div>
                            </template>
                            <p x-show="skillBaru.length === 0" class="text-xs text-gray-400">
                                Belum ada area baru ditambahkan.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Daftar Barang (KIRIMAN RUTIN ONLY) — full width, di luar kolom sempit skill --}}
        <template x-if="pengajuan.jenis_pengajuan === 'pengiriman_rutin'">
            <div class="col-span-2 space-y-4">
                <div class="rounded-lg border border-gray-200 bg-gray-50 p-4">
                    <div class="mb-3 flex items-center justify-between">
                        <h4 class="text-sm font-semibold text-gray-800">Daftar Barang</h4>
                        <button type="button" @click="tambahDetailItem()"
                            class="rounded text-xs font-medium text-avian-green hover:bg-avian-green-light px-2 py-1">
                            + Tambah Item
                        </button>
                    </div>

                    <div x-show="detailKirimanRutin.length === 0" class="text-sm text-gray-400 text-center py-4">
                        Belum ada item. Klik "+ Tambah Item" untuk mulai.
                    </div>

                    <div x-show="detailKirimanRutin.length > 0" class="space-y-3">
                        <template x-for="(detail, idx) in detailKirimanRutin" :key="idx">
                            <div class="bg-white rounded border border-gray-200 p-3">
                                <div class="grid grid-cols-1 gap-2 sm:grid-cols-[minmax(0,1fr)_5rem_8rem_8rem_1.5rem] sm:gap-3 sm:items-center">
                                    {{-- Jenis Barang Dropdown (selalu semua master, apapun vendornya) --}}
                                    <div>
                                        <select x-model="detail.id_jenis_barang"
                                            x-init="$nextTick(() => { $el.value = detail.id_jenis_barang })"
                                            @change="pilihJenisBarangDetail(detail)"
                                            class="w-full rounded border border-gray-300 px-2 py-1.5 text-xs">
                                            <option value="">-- Pilih Barang --</option>
                                            <template x-for="b in jenisBarangList" :key="b.id_jenis_barang">
                                                <option :value="b.id_jenis_barang" x-text="b.nama_barang"></option>
                                            </template>
                                        </select>
                                    </div>

                                    {{-- Quantity Input --}}
                                    <div>
                                        <span class="sm:hidden text-[10px] text-gray-400">Qty</span>
                                        <input type="number" x-model="detail.quantity" placeholder="Qty"
                                            @input="detail.subtotal = Number(detail.quantity) * Number(detail.harga_satuan || 0)"
                                            @wheel="$event.target.blur()"
                                            class="w-full rounded border border-gray-300 px-2 py-1.5 text-xs" min="0.01" step="0.01">
                                    </div>

                                    {{-- Harga Satuan: read-only kalau tarif sudah ada, editable kalau tarif baru --}}
                                    <div>
                                        <span class="sm:hidden text-[10px] text-gray-400">Harga Satuan</span>
                                        <template x-if="!detail.tarif_baru">
                                            <span class="block sm:text-right text-xs text-gray-500 py-1" x-text="'Rp ' + Number(detail.harga_satuan || 0).toLocaleString('id-ID')"></span>
                                        </template>
                                        <template x-if="detail.tarif_baru">
                                            <input type="text" inputmode="numeric"
                                                :value="formatRibuan(detail.harga_satuan)"
                                                @input="detail.harga_satuan = parseRibuan($event.target.value); detail.subtotal = Number(detail.quantity || 0) * Number(detail.harga_satuan || 0)"
                                                placeholder="Contoh: 20.000"
                                                class="w-full rounded border border-amber-300 px-2 py-1.5 text-xs sm:text-right">
                                        </template>
                                    </div>

                                    {{-- Subtotal (Read-only, Auto-computed) --}}
                                    <div class="sm:text-right">
                                        <span class="sm:hidden text-[10px] text-gray-400">Subtotal </span>
                                        <span class="text-xs font-semibold text-gray-800" x-text="'Rp ' + Number(detail.subtotal || 0).toLocaleString('id-ID')"></span>
                                    </div>

                                    {{-- Delete Button --}}
                                    <button type="button" @click="hapusDetailItem(idx)"
                                        class="justify-self-start sm:justify-self-center text-red-500 hover:text-red-700 text-xs font-medium">
                                        ✕
                                    </button>
                                </div>

                                {{-- Notifikasi tarif — baris penuh terpisah --}}
                                <p x-show="detail.tarif_baru" class="mt-2 text-[11px] font-medium text-amber-600">
                                    Harga khusus pengajuan ini. Belum jadi tarif resmi vendor — daftarkan lewat Kelola Tarif kalau mau dipakai pengajuan berikutnya.
                                </p>
                                <template x-if="cekSelisihTarif(detail) !== null">
                                    <p class="mt-2 text-[11px] font-medium text-blue-600">
                                        Tarif resmi sekarang: Rp <span x-text="cekSelisihTarif(detail).toLocaleString('id-ID')"></span>
                                        (beda dari Rp <span x-text="Number(detail.harga_satuan).toLocaleString('id-ID')"></span> saat pengajuan ini dibuat)
                                    </p>
                                </template>
                            </div>
                        </template>
                    </div>
                </div>

                {{-- Total Harga Agregasi --}}
                <div class="rounded-lg border border-gray-200 bg-gray-50 px-4 py-4">
                    <div class="flex justify-between items-center">
                        <p class="text-sm font-medium text-gray-700">Total Harga (Agregasi Otomatis)</p>
                        <p class="text-lg font-bold text-avian-green"
                            x-text="'Rp ' + Number(pengajuan.harga_sewa || 0).toLocaleString('id-ID')">
                        </p>
                    </div>
                </div>
            </div>
        </template>
    </div>

    {{-- Biaya Tambahan (SHARED) --}}
    <div>
        <div class="mb-3 flex items-center justify-between">
            <label class="text-xs font-medium text-gray-600">Biaya Tambahan (opsional)</label>
            <button
                type="button"
                @click="biayaTambahan.push({ id_jenis_biaya: '', nominal: '' })"
                class="rounded-lg border border-avian-green px-3 py-1 text-xs font-medium text-avian-green hover:bg-avian-green-light transition">
                + Tambah
            </button>
        </div>

        <div class="space-y-2">
            <template x-for="(b, i) in biayaTambahan" :key="i">
                <div class="flex items-center gap-3">
                    <div class="relative flex-1">
                        <select
                            x-model="b.id_jenis_biaya"
                            x-init="$nextTick(() => { $el.value = b.id_jenis_biaya })"
                            class="w-full appearance-none rounded-lg border border-gray-300 px-3 py-2 pr-9 text-sm focus:border-avian-green focus:outline-none">
                            <option value="">-- Jenis Biaya --</option>
                            <template x-for="j in jenisBiayaList" :key="j.id_jenis_biaya">
                                <option :value="j.id_jenis_biaya" x-text="j.nama_biaya"></option>
                            </template>
                        </select>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                            class="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400">
                            <path d="m6 9 6 6 6-6" />
                        </svg>
                    </div>
                    <input
                        type="text"
                        inputmode="numeric"
                        :value="formatRibuan(b.nominal)"
                        @input="b.nominal = parseRibuan($event.target.value)"
                        placeholder="Nominal (Rp)"
                        class="flex-1 rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-avian-green focus:outline-none">
                    <button
                        type="button"
                        @click="biayaTambahan.splice(i, 1)"
                        class="rounded-lg border border-red-200 px-2 py-2 text-red-400 hover:bg-red-50 transition">
                        <i data-lucide="trash-2" class="w-4 h-4"></i>
                    </button>
                </div>
            </template>

            <p x-show="biayaTambahan.length === 0" class="text-xs text-gray-400">
                Belum ada biaya tambahan.
            </p>
        </div>
    </div>
    
    {{-- Catatan (SHARED, full width) --}}
    <div class="col-span-2">
        <label class="mb-1 block text-xs font-medium text-gray-600">Catatan (opsional)</label>
        <textarea
            x-model="pengajuan.catatan"
            rows="3"
            placeholder="Keterangan tambahan jika ada..."
            class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-avian-green focus:outline-none resize-none">
        </textarea>
    </div>

    {{-- Preview Kalkulasi Rasio (SHARED) --}}
    <div class="rounded-lg border border-gray-200 bg-gray-50 px-4 py-4">
        <p class="mb-3 text-xs font-medium uppercase tracking-wide text-gray-400">Preview Kalkulasi</p>
        <div class="grid grid-cols-3 gap-4 text-sm">
            <div>
                <p class="text-xs text-gray-400" x-text="pengajuan.jenis_pengajuan === 'pengiriman_rutin' ? 'Total Tarif' : 'Harga Sewa'"></p>
                <p class="font-semibold text-gray-800"
                    x-text="pengajuan.harga_sewa
                        ? 'Rp ' + Number(pengajuan.harga_sewa).toLocaleString('id-ID')
                        : '—'">
                </p>
            </div>
            <div>
                <p class="text-xs text-gray-400">Biaya Tambahan</p>
                <p class="font-semibold text-gray-800"
                    x-text="'Rp ' + totalBiayaTambahan.toLocaleString('id-ID')">
                </p>
            </div>
            <div>
                <p class="text-xs text-gray-400">Total Biaya</p>
                <p class="font-semibold text-gray-800"
                    x-text="'Rp ' + totalDenganBiayaTambahan.toLocaleString('id-ID')">
                </p>
            </div>
        </div>

        {{-- Ringkasan data pengajuan yang sudah diisi di step ini --}}
        <div class="mt-3 border-t border-gray-200 pt-3 space-y-1.5">
            <div class="flex items-center justify-between text-xs">
                <span class="text-gray-500">Tanggal Pengiriman</span>
                <span class="font-medium text-gray-700" x-text="formatTanggalID(pengajuan.tanggal_pengiriman)"></span>
            </div>
            <div class="flex items-center justify-between text-xs">
                <span class="text-gray-500">Tujuan Penyewaan</span>
                <span class="font-medium text-gray-700" x-text="pengajuan.tujuan_penyewaan || '—'"></span>
            </div>
            <div class="flex items-center justify-between text-xs">
                <span class="text-gray-500">Skill / Area</span>
                <span class="font-medium text-gray-700 text-right" x-text="skillGabunganLabel.join(', ') || '—'"></span>
            </div>
            <div class="flex items-center justify-between text-xs">
                <span class="text-gray-500">Kategori Toko</span>
                <span class="font-medium text-gray-700" x-text="pengajuan.kategoriToko || '—'"></span>
            </div>
        </div>
    </div>

    {{-- Navigasi (SHARED, disabled logic conditional per jenis) --}}
    <div class="flex items-center justify-between">
        <button
            type="button"
            @click="goToStep(1)"
            class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50">
            ← Kembali
        </button>
        <button
            type="button"
            @click="goToStep(3)"
            :disabled="pengajuan.jenis_pengajuan === 'sewa_truk'
                ? (!pengajuan.tanggal_pengiriman || !pengajuan.harga_sewa || !pengajuan.tujuan_penyewaan || !adaSkillTerpilih || !pengajuan.kategoriToko)
                : (!pengajuan.tanggal_pengiriman || !detailKirimanRutinValid || !pengajuan.tujuan_penyewaan || !adaSkillTerpilih || !pengajuan.kategoriToko)"
            :title="tooltipStep2"
            :class="(pengajuan.jenis_pengajuan === 'sewa_truk'
                ? (pengajuan.tanggal_pengiriman && pengajuan.harga_sewa && pengajuan.tujuan_penyewaan && adaSkillTerpilih && pengajuan.kategoriToko)
                : (pengajuan.tanggal_pengiriman && detailKirimanRutinValid && pengajuan.tujuan_penyewaan && adaSkillTerpilih && pengajuan.kategoriToko))
                ? 'bg-avian-green text-white hover:bg-avian-green-dark'
                : 'bg-gray-100 text-gray-400 cursor-not-allowed'"
            class="rounded-lg px-4 py-2 text-sm font-medium transition">
            Lanjut →
        </button>
    </div>

</div>

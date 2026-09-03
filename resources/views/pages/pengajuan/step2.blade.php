<div class="space-y-6">

    <h3 class="text-sm font-semibold text-gray-700">Data Pengajuan</h3>

    {{-- Info armada terpilih (ringkasan dari step 1) --}}
    <div class="rounded-lg bg-avian-green-light border border-avian-green/20 px-4 py-3 flex items-center gap-4">
        <i data-lucide="truck" class="w-4 h-4 text-avian-green shrink-0"></i>
        <div class="text-sm">
            <span class="font-semibold text-avian-green" x-text="armadaTerpilih?.nama"></span>
            <span class="text-avian-green/70 mx-1">·</span>
            <span class="text-avian-green/70" x-text="armadaTerpilih?.kendaraan"></span>
            <span class="text-avian-green/70 mx-1">·</span>
            <span class="text-avian-green/70" x-text="armadaTerpilih?.harga"></span>
            <span class="text-avian-green/70 mx-1">·</span>
            <span class="text-avian-green/70" x-text="armadaTerpilih?.update_at"></span>
        </div>
        <button type="button" @click="goToStep(1)"
            x-show="!editId"
            class="ml-auto text-xs text-avian-green underline hover:no-underline">
            Ganti
        </button>
    </div>

    {{-- Form grid --}}
    <div class="grid grid-cols-2 gap-5">

        {{-- Tanggal Pengiriman --}}
        <div>
            <label class="mb-1 block text-xs font-medium text-gray-600">
                Tanggal Pengiriman <span class="text-red-500">*</span>
            </label>
            <input
                type="date"
                x-model="pengajuan.tanggal_pengiriman"
                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-avian-green focus:outline-none">
        </div>

        {{-- Harga Sewa --}}
        <div>
            <label class="mb-1 block text-xs font-medium text-gray-600">
                Harga Sewa (Rp) <span class="text-red-500">*</span>
            </label>
            <input
                type="number"
                x-model="pengajuan.harga_sewa"
                placeholder="Contoh: 2500000"
                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-avian-green focus:outline-none">
        </div>

        {{-- Tujuan Penyewaan --}}
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

        {{-- Kategori Toko --}}
        <div>
            <label class="mb-1 block text-xs font-medium text-gray-600">
                Kategori Toko <span class="text-red-500">*</span>
            </label>
            <div class="relative">
                <select
                    x-model="pengajuan.kategoriToko"
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

        {{-- Skill / Area --}}
        <div class="col-span-2">
            <label class="mb-1 block text-xs font-medium text-gray-600">
                Skill / Area Pengantaran <span class="text-red-500">*</span>
            </label>

            <div class="grid grid-cols-2 gap-4">

                {{-- Kiri: Checklist (tampil semua, no scroll) --}}
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
                                    <span x-text="s"></span>
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

                    {{-- Tombol Lock --}}
                    <button
                        type="button"
                        @click="skillLocked = !skillLocked"
                        :disabled="pengajuan.id_skill.length === 0 && skillBaru.filter(s => s.trim() !== '').length === 0"
                        :class="skillLocked
                            ? 'bg-avian-green text-white hover:bg-avian-green-dark'
                            : 'border border-avian-green text-avian-green hover:bg-avian-green-light'"
                        class="w-full rounded-lg px-4 py-2 text-sm font-medium transition flex items-center justify-center gap-2 disabled:opacity-40 disabled:cursor-not-allowed">
                        <i :data-lucide="skillLocked ? 'lock' : 'lock-open'" class="w-4 h-4"></i>
                        <span x-text="skillLocked ? 'Terkunci — Klik untuk Ubah' : 'Kunci Pilihan Skill'"></span>
                    </button>

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

        {{-- Catatan (full width) --}}
        <div class="col-span-2">
            <label class="mb-1 block text-xs font-medium text-gray-600">Catatan (opsional)</label>
            <textarea
                x-model="pengajuan.catatan"
                rows="3"
                placeholder="Keterangan tambahan jika ada..."
                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-avian-green focus:outline-none resize-none">
            </textarea>
        </div>
    </div>

    {{-- Biaya Tambahan --}}
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
                        type="number"
                        x-model="b.nominal"
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

    {{-- Preview Kalkulasi Rasio --}}
    <div class="rounded-lg border border-gray-200 bg-gray-50 px-4 py-4">
        <p class="mb-3 text-xs font-medium uppercase tracking-wide text-gray-400">Preview Kalkulasi</p>
        <div class="grid grid-cols-3 gap-4 text-sm">
            <div>
                <p class="text-xs text-gray-400">Harga Sewa</p>
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
        <div class="mt-3 border-t border-gray-200 pt-3">
            <p class="text-xs text-gray-400">Estimasi Rasio Sewa</p>
            <p class="mt-0.5 text-lg font-bold text-gray-800">
                — <span class="text-xs font-normal text-gray-400">/ 2,5% (value muatan dari Step 3)</span>
            </p>
        </div>
    </div>

    {{-- Navigasi --}}
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
            :disabled="!pengajuan.tanggal_pengiriman || !pengajuan.harga_sewa || !pengajuan.tujuan_penyewaan || !adaSkillTerpilih || !pengajuan.kategoriToko"
            :title="tooltipStep2"
            :class="(pengajuan.tanggal_pengiriman && pengajuan.harga_sewa && pengajuan.tujuan_penyewaan && adaSkillTerpilih && pengajuan.kategoriToko)
                ? 'bg-avian-green text-white hover:bg-avian-green-dark'
                : 'bg-gray-100 text-gray-400 cursor-not-allowed'"
            class="rounded-lg px-4 py-2 text-sm font-medium transition">
            Lanjut →
        </button>
    </div>
</div>
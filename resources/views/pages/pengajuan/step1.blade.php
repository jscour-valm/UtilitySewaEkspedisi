{{-- Mode toggle --}}
<div class="mb-5 flex items-center justify-between">
    <h3 class="text-sm font-semibold text-gray-700">
        <span x-show="mode === 'pilih'">Pilih Armada</span>
        <span x-show="mode === 'tambah'">Tambahkan Data Armada Baru</span>
    </h3>
    <button
        type="button"
        @click="mode = mode === 'pilih' ? 'tambah' : 'pilih'"
        class="rounded-lg border border-avian-green px-3 py-1.5 text-xs font-medium text-avian-green hover:bg-avian-green-light transition">
        <span x-show="mode === 'pilih'">+ Tambah Armada Baru</span>
        <span x-show="mode === 'tambah'">← Kembali ke Daftar</span>
    </button>
</div>

{{-- Mode A: Pilih dari daftar --}}
<div x-show="mode === 'pilih'">
    <x-tabel-armada mode="pilih" />

    {{-- Armada terpilih info + tombol lanjut --}}
    <div class="mt-5 flex items-center justify-between">
        <div x-show="armadaTerpilih" class="text-sm text-gray-600">
            Dipilih: <span class="font-semibold text-gray-800" x-text="armadaTerpilih?.nama"></span>
            — <span x-text="armadaTerpilih?.kendaraan"></span>
            (<span x-text="armadaTerpilih?.harga"></span>)
        </div>
        <div x-show="!armadaTerpilih" class="text-sm text-gray-400">
            Belum ada armada yang dipilih.
        </div>
        <button
            type="button"
            @click="goToStep(2)"
            :disabled="!armadaTerpilih"
            :class="armadaTerpilih
                ? 'bg-avian-green text-white hover:bg-avian-green-dark'
                : 'bg-gray-100 text-gray-400 cursor-not-allowed'"
            class="rounded-lg px-4 py-2 text-sm font-medium transition">
            Lanjut →
        </button>
    </div>
</div>

{{-- Mode B: Tambah armada baru --}}
<div x-show="mode === 'tambah'" class="space-y-5">
    <p class="text-sm text-gray-500">Isi data armada baru. Setelah disimpan, armada akan otomatis terpilih.</p>

    {{-- Data Perusahaan --}}
    <div class="grid grid-cols-2 gap-4">
        <div>
            <x-form-label required>Nama Perusahaan</x-form-label>
            <input type="text" x-model="armadaBaru.nama_perusahaan"
                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-avian-green focus:outline-none">
        </div>
        <div>
            <x-form-label required>Badan Usaha</x-form-label>
            <select x-model="armadaBaru.badan_usaha"
                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-avian-green focus:outline-none">
                <option value="">-- Pilih --</option>
                <option value="PT">PT</option>
                <option value="CV">CV</option>
                <option value="UD">UD</option>
                <option value="Perorangan">Perorangan</option>
            </select>
        </div>
        <div>
            <x-form-label required>No. Telepon</x-form-label>
            <input type="text" x-model="armadaBaru.no_telepon"
                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-avian-green focus:outline-none">
        </div>
        <div>
            <x-form-label required>Alamat Kantor</x-form-label>
            <input type="text" x-model="armadaBaru.alamat_kantor"
                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-avian-green focus:outline-none">
        </div>
    </div>

    {{-- Data Kendaraan --}}
    <div class="grid grid-cols-3 gap-4">
        <div>
            <x-form-label required>Jenis Kendaraan</x-form-label>
            <input type="text" x-model="armadaBaru.nama_kendaraan"
                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-avian-green focus:outline-none">
        </div>
        <div>
            <x-form-label required>Plat Nomor</x-form-label>
            <input type="text" x-model="armadaBaru.plat_nomor"
                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-avian-green focus:outline-none">
        </div>
        <div>
            <x-form-label required>Muatan Maksimal (Ton)</x-form-label>
            <input type="number" x-model="armadaBaru.muatan_maksimal"
                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-avian-green focus:outline-none">
        </div>
    </div>

    {{-- Skill Selection (Multi-select) --}}
    <div>
        <x-form-label required>Area/Skill Layanan</x-form-label>

        <div class="grid grid-cols-2 gap-4">

            {{-- Kiri: Checklist --}}
            <div class="rounded-lg border border-gray-300 bg-white px-3 py-2">
                <template x-if="skillList.length === 0">
                    <p class="text-xs text-gray-400 py-1">Memuat daftar area...</p>
                </template>
                <template x-for="s in skillList" :key="s.id_skill">
                    <label class="flex items-center gap-2 cursor-pointer py-1 hover:bg-gray-50 px-1 rounded">
                        <input
                            type="checkbox"
                            :value="s.id_skill"
                            x-model="armadaBaru.id_skill"
                            class="rounded border-gray-300 text-avian-green focus:ring-avian-green">
                        <span class="text-sm text-gray-700" x-text="s.nama_skill"></span>
                    </label>
                </template>
            </div>

            {{-- Kanan: Summary + Add New --}}
            <div class="flex flex-col gap-3">
                <div class="rounded-lg border border-gray-200 bg-gray-50 px-3 py-2">
                    <p class="mb-2 text-xs font-medium text-gray-500">Area Dipilih</p>
                    <div class="flex flex-wrap gap-1.5">
                        <template x-if="armadaBaru.id_skill.length === 0 && armadaBaru.skillBaru.filter(s => s.trim() !== '').length === 0">
                            <p class="text-xs text-gray-400">Belum ada area dipilih.</p>
                        </template>
                        <template x-for="s in armadaBaru.id_skill" :key="s">
                            <span class="inline-flex items-center gap-1 rounded-full bg-avian-green-light px-2.5 py-0.5 text-xs font-medium text-avian-green">
                                <span x-text="s"></span>
                                <button
                                    type="button"
                                    @click="armadaBaru.id_skill = armadaBaru.id_skill.filter(x => x !== s)"
                                    class="hover:text-avian-green-dark leading-none">×</button>
                            </span>
                        </template>
                        <template x-for="(s, i) in armadaBaru.skillBaru.filter(s => s.trim() !== '')" :key="'baru-'+i">
                            <span class="inline-flex items-center gap-1 rounded-full bg-blue-50 px-2.5 py-0.5 text-xs font-medium text-blue-600">
                                <span x-text="s"></span>
                                <span class="text-blue-400 text-[10px]">baru</span>
                            </span>
                        </template>
                    </div>
                </div>

                {{-- Tambah Area Baru --}}
                <div>
                    <div class="mb-2 flex items-center justify-between">
                        <span class="text-xs font-medium text-gray-600">Tambah Area Baru</span>
                        <button
                            type="button"
                            @click="armadaBaru.skillBaru.push('')"
                            class="rounded-lg border border-avian-green px-3 py-1 text-xs font-medium text-avian-green hover:bg-avian-green-light transition">
                            + Tambah
                        </button>
                    </div>
                    <div class="space-y-2">
                        <template x-for="(s, i) in armadaBaru.skillBaru" :key="i">
                            <div class="flex items-center gap-2">
                                <input
                                    type="text"
                                    x-model="armadaBaru.skillBaru[i]"
                                    placeholder="Contoh: ACKOT CDE"
                                    class="flex-1 rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-avian-green focus:outline-none">
                                <button
                                    type="button"
                                    @click="armadaBaru.skillBaru.splice(i, 1)"
                                    class="rounded-lg border border-red-200 px-2 py-2 text-red-400 hover:bg-red-50 transition">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                    </svg>
                                </button>
                            </div>
                        </template>
                        <p x-show="armadaBaru.skillBaru.length === 0" class="text-xs text-gray-400">
                            Belum ada area baru ditambahkan.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Upload KTP + SIM --}}
    <div class="grid grid-cols-2 gap-4">
        <div>
            <x-form-label required>KTP Supir</x-form-label>
            <input type="file" accept="image/*" required
                @change="handleKtpUpload($event)"
                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-avian-green focus:outline-none">
            <img x-show="armadaBaru.ktp_preview" :src="armadaBaru.ktp_preview"
                class="mt-2 h-24 rounded-lg border border-gray-200 object-cover">
        </div>
        <div>
            <x-form-label required>SIM Supir</x-form-label>
            <input type="file" accept="image/*" required
                @change="handleSimUpload($event)"
                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-avian-green focus:outline-none">
            <img x-show="armadaBaru.sim_preview" :src="armadaBaru.sim_preview"
                class="mt-2 h-24 rounded-lg border border-gray-200 object-cover">
        </div>
    </div>

    {{-- Tombol simpan --}}
    <div class="flex justify-end gap-3">
        <button
            type="button"
            @click="mode = 'pilih'"
            class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50">
            Batal
        </button>
        <button
            type="button"
            @click="saveArmada"
            class="rounded-lg bg-avian-green px-4 py-2 text-sm font-medium text-white hover:bg-avian-green-dark">
            Simpan & Pilih Armada
        </button>
    </div>
</div>
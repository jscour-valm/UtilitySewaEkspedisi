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
            :title="tooltipStep1"
            :class="armadaTerpilih
                ? 'bg-avian-green text-white hover:bg-avian-green-dark'
                : 'bg-gray-100 text-gray-400 cursor-not-allowed'"
            class="rounded-lg px-4 py-2 text-sm font-medium transition">
            Lanjut →
        </button>
    </div>
</div>

{{-- Mode B: Tambah armada baru (2 sub-step) --}}
<div x-show="mode === 'tambah'" class="space-y-5">
    {{-- Mini-stepper untuk sub-step (3 bubble clickable) --}}
    <div class="mb-4 flex items-center gap-3 text-xs">
        {{-- Bubble 1: Perusahaan --}}
        <button type="button" @click="subStepArmada = 1" class="flex items-center gap-1.5 cursor-pointer hover:opacity-80 transition">
            <span :class="subStepArmada === 1
                ? 'bg-avian-green text-white'
                : (perusahaanTerpilih ? 'bg-avian-green-light text-avian-green' : 'bg-gray-200 text-gray-400')"
                  class="flex h-6 w-6 items-center justify-center rounded-full font-semibold">
                <span x-show="subStepArmada === 1">1</span>
                <span x-show="subStepArmada !== 1 && perusahaanTerpilih">✓</span>
                <span x-show="subStepArmada !== 1 && !perusahaanTerpilih">1</span>
            </span>
            <span :class="subStepArmada === 1
                ? 'font-semibold text-gray-800'
                : (perusahaanTerpilih ? 'font-semibold text-gray-800' : 'text-gray-400')">Perusahaan</span>
        </button>

        {{-- Garis penghubung 1-2 --}}
        <div class="h-px flex-1" :class="perusahaanTerpilih ? 'bg-avian-green' : 'bg-gray-200'"></div>

        {{-- Bubble 2: Kendaraan --}}
        <button type="button" @click="subStepArmada = 2" class="flex items-center gap-1.5 cursor-pointer hover:opacity-80 transition">
            <span :class="subStepArmada === 2
                ? 'bg-avian-green text-white'
                : (armadaTerpilih ? 'bg-avian-green-light text-avian-green' : 'bg-gray-200 text-gray-400')"
                  class="flex h-6 w-6 items-center justify-center rounded-full font-semibold">
                <span x-show="subStepArmada === 2">2</span>
                <span x-show="subStepArmada !== 2 && armadaTerpilih">✓</span>
                <span x-show="subStepArmada !== 2 && !armadaTerpilih">2</span>
            </span>
            <span :class="subStepArmada === 2
                ? 'font-semibold text-gray-800'
                : (armadaTerpilih ? 'font-semibold text-gray-800' : 'text-gray-400')">Kendaraan</span>
        </button>

        {{-- Garis penghubung 2-3 --}}
        <div class="h-px flex-1" :class="armadaTerpilih ? 'bg-avian-green' : 'bg-gray-200'"></div>

        {{-- Bubble 3: Ringkasan --}}
        <button type="button" @click="subStepArmada = 3" class="flex items-center gap-1.5 cursor-pointer hover:opacity-80 transition">
            <span :class="subStepArmada === 3
                ? 'bg-avian-green text-white'
                : 'bg-gray-200 text-gray-400'"
                  class="flex h-6 w-6 items-center justify-center rounded-full font-semibold">3</span>
            <span :class="subStepArmada === 3
                ? 'font-semibold text-gray-800'
                : 'text-gray-400'">Ringkasan</span>
        </button>
    </div>

    {{-- SUB-STEP 1: Pilih / Tambah Perusahaan --}}
    <div x-show="subStepArmada === 1" class="space-y-4 rounded-lg border border-gray-200 bg-gray-50 p-4">
        <h4 class="font-semibold text-gray-800">Langkah 1: Pilih atau Tambah Perusahaan Ekspedisi</h4>

        {{-- Tabel: Pilih perusahaan existing --}}
        <div x-show="armadaBaru.perusahaan_mode === 'pilih'">
            <div class="mb-3">
                <x-tabel-perusahaan />
            </div>

            {{-- Tombol Lanjut ke Kendaraan --}}
            <div x-show="armadaBaru.perusahaan_id" class="mt-3 flex justify-end">
                <button
                    type="button"
                    @click="if (perusahaanTerpilih?.id_perusahaan != armadaBaru.perusahaan_id) armadaTerpilih = null; perusahaanTerpilih = perusahaanList.find(p => p.id_perusahaan == armadaBaru.perusahaan_id); fetchArmadaByPerusahaan(armadaBaru.perusahaan_id); subStepArmada = 2; saveDraft()"
                    class="rounded-lg bg-avian-green px-4 py-2 text-sm font-medium text-white hover:bg-avian-green-dark">
                    Lanjut ke Kendaraan →
                </button>
            </div>

            {{-- Tombol Tambah Baru --}}
            <div class="mt-2 text-center">
                <button
                    type="button"
                    @click="armadaBaru.perusahaan_mode = 'baru'"
                    class="text-xs font-medium text-avian-green hover:underline">
                    + Atau Tambah Perusahaan Baru
                </button>
            </div>
        </div>

        {{-- Form: Tambah perusahaan baru --}}
        <div x-show="armadaBaru.perusahaan_mode === 'baru'" class="space-y-4 rounded-lg bg-gray-50 p-4">
            <div class="flex items-center justify-between">
                <h5 class="font-medium text-gray-700">Perusahaan Baru</h5>
                <button
                    type="button"
                    @click="armadaBaru.perusahaan_mode = 'pilih'; armadaBaru.nama_perusahaan = ''; armadaBaru.badan_usaha = ''; armadaBaru.no_telepon = ''; armadaBaru.alamat_kantor = ''; armadaBaru.identitas_owner_files = []; armadaBaru.identitas_owner_previews = []"
                    class="text-xs text-gray-500 hover:text-gray-700 underline">
                    ← Kembali ke Pilih
                </button>
            </div>

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
                        <option value="Perseorangan">Perseorangan</option>
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

            {{-- Identitas Owner Upload --}}
            <div>
                <x-form-label required>Upload Identitas Owner (KTP/NPWP/SIM) — Max 3 File</x-form-label>
                <input type="file" accept="image/*" multiple
                    @change="handleIdentitasOwnerUpload($event)"
                    class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-avian-green focus:outline-none">
                <p class="mt-2 text-xs text-gray-500">Format: JPG, PNG, WebP. Ukuran max 5MB per file.</p>

                {{-- Preview thumbnails --}}
                <div x-show="armadaBaru.identitas_owner_previews.length > 0" class="mt-3 flex gap-2 flex-wrap">
                    <template x-for="(preview, i) in armadaBaru.identitas_owner_previews" :key="i">
                        <div class="relative">
                            <img :src="preview" class="h-20 w-20 rounded-lg border border-gray-200 object-cover">
                            <button
                                type="button"
                                @click="armadaBaru.identitas_owner_files.splice(i, 1); armadaBaru.identitas_owner_previews.splice(i, 1)"
                                class="absolute -top-2 -right-2 rounded-full bg-red-500 text-white w-5 h-5 flex items-center justify-center text-xs font-bold hover:bg-red-600">
                                ×
                            </button>
                        </div>
                    </template>
                </div>
            </div>

            {{-- Buttons: Simpan / Batal --}}
            <div class="flex justify-end gap-3">
                <button
                    type="button"
                    @click="armadaBaru.perusahaan_mode = 'pilih'; armadaBaru.nama_perusahaan = ''; armadaBaru.badan_usaha = ''; armadaBaru.no_telepon = ''; armadaBaru.alamat_kantor = ''; armadaBaru.identitas_owner_files = []; armadaBaru.identitas_owner_previews = []"
                    class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50">
                    Batal
                </button>
                <button
                    type="button"
                    @click="savePerusahaanBaru()"
                    class="rounded-lg bg-avian-green px-4 py-2 text-sm font-medium text-white hover:bg-avian-green-dark">
                    Simpan Perusahaan
                </button>
            </div>
        </div>
    </div>

    {{-- SUB-STEP 2: Pilih/Buat Kendaraan (hanya tampil setelah perusahaan dipilih) --}}
    <div x-show="subStepArmada === 2" class="space-y-4 rounded-lg border border-gray-200 bg-gray-50 p-4">
        <div class="flex items-center justify-between">
            <h4 class="font-semibold text-gray-800">Langkah 2: Data Kendaraan</h4>
            <button
                type="button"
                @click="subStepArmada = 1"
                class="text-xs text-gray-500 hover:text-gray-700 underline">
                ← Ganti Perusahaan
            </button>
        </div>

        <p class="text-sm text-gray-600">Perusahaan: <strong x-text="perusahaanTerpilih?.nama_perusahaan"></strong></p>

        {{-- Daftar armada existing milik perusahaan ini --}}
        <div x-show="armadaByPerusahaan.length > 0">
            <p class="mb-2 text-sm font-medium text-gray-700">Kendaraan Terdaftar</p>
            <div class="overflow-hidden rounded-lg border border-gray-200 bg-gray-50">
                <template x-for="a in armadaByPerusahaan" :key="a.id">
                    <div class="flex items-center justify-between border-b border-gray-100 px-4 py-3 last:border-0 cursor-pointer hover:bg-gray-50"
                         :class="armadaTerpilih?.id === a.id ? 'bg-avian-green-light' : ''"
                         @click="armadaTerpilih = (armadaTerpilih?.id === a.id ? null : a); showFormKendaraanBaru = false">
                        <div class="text-sm">
                            <span class="font-medium text-gray-800" x-text="a.kendaraan || '(Tanpa nama)'"></span>
                            <span class="text-gray-400 mx-1">·</span>
                            <span class="text-gray-500" x-text="a.plat_nomor_truk || 'Tanpa plat'"></span>
                            <span class="text-gray-400 mx-1">·</span>
                            <span class="text-gray-500" x-text="a.muatan"></span>
                        </div>
                        <span x-show="armadaTerpilih?.id === a.id" class="text-xs font-medium text-avian-green">✓ Dipilih</span>
                    </div>
                </template>
            </div>
        </div>
        <p x-show="!loadingArmadaByPerusahaan && armadaByPerusahaan.length === 0" class="text-sm text-gray-400">
            Perusahaan ini belum punya kendaraan terdaftar.
        </p>

        {{-- Toggle form tambah kendaraan baru --}}
        <button type="button" @click="showFormKendaraanBaru = !showFormKendaraanBaru; if (showFormKendaraanBaru) armadaTerpilih = null"
            class="text-xs font-medium text-avian-green hover:underline"
            x-text="showFormKendaraanBaru ? '− Sembunyikan form tambah kendaraan' : '+ Tambah Kendaraan Baru'"></button>

        {{-- Form kendaraan baru --}}
        <div x-show="showFormKendaraanBaru" class="space-y-4 rounded-lg bg-gray-50 p-4">
            {{-- Jenis Kendaraan & Plat Nomor & Muatan Maksimal --}}
            <div class="grid grid-cols-3 gap-4">
                <div>
                    <x-form-label>Jenis Kendaraan</x-form-label>
                    <input type="text" x-model="armadaBaru.jenis_kendaraan" placeholder="Cth: Truk Box"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-avian-green focus:outline-none">
                </div>
                <div>
                    <x-form-label>Plat Nomor</x-form-label>
                    <input type="text" x-model="armadaBaru.plat_nomor_truk" placeholder="Cth: B 1234 XYZ"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-avian-green focus:outline-none">
                </div>
                <div>
                    <x-form-label required>Muatan Maksimal (Ton)</x-form-label>
                    <input type="number" x-model="armadaBaru.muatan_maksimal" step="0.01" min="0.01"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-avian-green focus:outline-none">
                </div>
            </div>

            {{-- Skill Selection (Multi-select) --}}
            <div>
                <x-form-label required>Area/Skill Layanan</x-form-label>

                <div class="grid grid-cols-2 gap-4">

                    {{-- Kiri: Checklist --}}
                    <div class="rounded-lg border border-gray-300 bg-gray-50 px-3 py-2">
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

            {{-- Tombol simpan kendaraan baru --}}
            <div class="flex justify-end">
                <button
                    type="button"
                    @click="saveArmada()"
                    class="rounded-lg bg-avian-green px-4 py-2 text-sm font-medium text-white hover:bg-avian-green-dark">
                    Simpan & Pilih Armada
                </button>
            </div>
        </div>


        {{-- Tombol navigasi bawah --}}
        <div class="flex justify-end gap-3">
            <button
                type="button"
                @click="subStepArmada = 3"
                :disabled="!armadaTerpilih"
                :class="armadaTerpilih
                    ? 'bg-avian-green text-white hover:bg-avian-green-dark'
                    : 'bg-gray-100 text-gray-400 cursor-not-allowed'"
                class="rounded-lg px-4 py-2 text-sm font-medium transition">
                Lanjut →
            </button>
        </div>
    </div>

    {{-- SUB-STEP 3: Ringkasan --}}
    <div x-show="subStepArmada === 3" class="space-y-4 rounded-lg border border-gray-200 bg-gray-50 p-4">
        <h4 class="font-semibold text-gray-800">Langkah 3: Ringkasan</h4>

        <div x-show="perusahaanTerpilih && armadaTerpilih" class="rounded-lg bg-blue-50 border border-blue-200 p-4">
            <p class="mb-3 text-sm font-medium text-gray-700">Ringkasan Pilihan:</p>
            <div class="space-y-2 text-sm">
                <div>
                    <span class="text-gray-500">Perusahaan:</span>
                    <span class="font-semibold text-gray-800" x-text="perusahaanTerpilih?.nama_perusahaan"></span>
                    <button type="button" @click="subStepArmada = 1" class="ml-2 text-xs text-avian-green hover:underline">Ganti</button>
                </div>
                <div>
                    <span class="text-gray-500">Kendaraan:</span>
                    <span class="font-semibold text-gray-800" x-text="armadaTerpilih?.kendaraan || '(Tanpa nama)'"></span>
                    <span class="text-gray-400" x-text="' • ' + (armadaTerpilih?.plat_nomor_truk || 'Tanpa plat')"></span>
                    <button type="button" @click="subStepArmada = 2" class="ml-2 text-xs text-avian-green hover:underline">Ganti</button>
                </div>
                <div>
                    <span class="text-gray-500">Muatan Maksimal:</span>
                    <span class="font-semibold text-gray-800" x-text="armadaTerpilih?.muatan"></span>
                </div>
            </div>
        </div>

        <p x-show="!(perusahaanTerpilih && armadaTerpilih)" class="text-sm text-gray-400">
            Lengkapi pilihan perusahaan dan kendaraan terlebih dahulu sebelum lanjut.
        </p>

        <div class="flex justify-between gap-3">
            <button type="button" @click="subStepArmada = 2"
                class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50">
                ← Kembali
            </button>
            <button type="button" @click="goToStep(2)" :disabled="!armadaTerpilih"
                :class="armadaTerpilih ? 'bg-avian-green text-white hover:bg-avian-green-dark' : 'bg-gray-100 text-gray-400 cursor-not-allowed'"
                class="rounded-lg px-4 py-2 text-sm font-medium transition">
                Lanjut →
            </button>
        </div>
    </div>
</div>
{{-- Step 1 — mini-stepper sama untuk kedua jenis (Sewa Truk & Kiriman Rutin).
     Yang beda cuma isi tabel/panel di tiap sub-step, bukan layout-nya. --}}
<div class="space-y-5">
    {{-- Mini-stepper untuk sub-step (3 bubble clickable) --}}
    <div class="mb-4 flex items-center gap-3 text-xs">
        {{-- Bubble 1: Perusahaan --}}
        <button type="button" @click="if (canGoToSubStep(1)) subStepKendaraan = 1"
            :disabled="!canGoToSubStep(1)"
            :class="!canGoToSubStep(1) ? 'cursor-not-allowed opacity-50' : 'cursor-pointer hover:opacity-80'"
            class="flex items-center gap-1.5 transition">
            <span :class="subStepKendaraan === 1
                ? 'bg-avian-green text-white'
                : (perusahaanTerpilih ? 'bg-avian-green-light text-avian-green' : 'bg-gray-200 text-gray-400')"
                  class="flex h-6 w-6 items-center justify-center rounded-full font-semibold">
                <span x-show="subStepKendaraan === 1">1</span>
                <span x-show="subStepKendaraan !== 1 && perusahaanTerpilih">✓</span>
                <span x-show="subStepKendaraan !== 1 && !perusahaanTerpilih">1</span>
            </span>
            <span :class="subStepKendaraan === 1
                ? 'font-semibold text-gray-800'
                : (perusahaanTerpilih ? 'font-semibold text-gray-800' : 'text-gray-400')">Perusahaan</span>
        </button>

        {{-- Garis penghubung 1-2 --}}
        <div class="h-px flex-1" :class="perusahaanTerpilih ? 'bg-avian-green' : 'bg-gray-200'"></div>

        {{-- Bubble 2: Kendaraan (Sewa Truk) / Area & Tarif (Kiriman Rutin) --}}
        <button type="button" @click="if (canGoToSubStep(2)) subStepKendaraan = 2"
            :disabled="!canGoToSubStep(2)"
            :class="!canGoToSubStep(2) ? 'cursor-not-allowed opacity-50' : 'cursor-pointer hover:opacity-80'"
            class="flex items-center gap-1.5 transition">
            <span :class="subStepKendaraan === 2
                ? 'bg-avian-green text-white'
                : (step2Done ? 'bg-avian-green-light text-avian-green' : 'bg-gray-200 text-gray-400')"
                  class="flex h-6 w-6 items-center justify-center rounded-full font-semibold">
                <span x-show="subStepKendaraan === 2">2</span>
                <span x-show="subStepKendaraan !== 2 && step2Done">✓</span>
                <span x-show="subStepKendaraan !== 2 && !step2Done">2</span>
            </span>
            <span :class="subStepKendaraan === 2
                ? 'font-semibold text-gray-800'
                : (step2Done ? 'font-semibold text-gray-800' : 'text-gray-400')"
                x-text="pengajuan.jenis_pengajuan === 'pengiriman_rutin' ? 'Area & Tarif' : 'Kendaraan'"></span>
        </button>

        {{-- Garis penghubung 2-3 --}}
        <div class="h-px flex-1" :class="step2Done ? 'bg-avian-green' : 'bg-gray-200'"></div>

        {{-- Bubble 3: Ringkasan --}}
        <button type="button" @click="if (canGoToSubStep(3)) subStepKendaraan = 3"
            :disabled="!canGoToSubStep(3)"
            :class="!canGoToSubStep(3) ? 'cursor-not-allowed opacity-50' : 'cursor-pointer hover:opacity-80'"
            class="flex items-center gap-1.5 transition">
            <span :class="subStepKendaraan === 3
                ? 'bg-avian-green text-white'
                : 'bg-gray-200 text-gray-400'"
                  class="flex h-6 w-6 items-center justify-center rounded-full font-semibold">3</span>
            <span :class="subStepKendaraan === 3
                ? 'font-semibold text-gray-800'
                : 'text-gray-400'">Ringkasan</span>
        </button>
    </div>

    {{-- SUB-STEP 1: Pilih / Tambah Perusahaan --}}
    <div x-show="subStepKendaraan === 1" class="space-y-4 rounded-lg border border-gray-200 bg-gray-50 p-4">
        <h4 class="font-semibold text-gray-800">Langkah 1: Pilih atau Tambah Perusahaan Ekspedisi</h4>

        {{-- Tabel: Pilih perusahaan existing --}}
        <div x-show="kendaraanBaru.perusahaan_mode === 'pilih'">
            <div class="mb-3">
                <x-tabel-perusahaan />
            </div>

            {{-- Tombol Lanjut ke Kendaraan --}}
            <div x-show="perusahaanTerpilih" class="mt-3 flex justify-end">
                <button
                    type="button"
                    @click="if (pengajuan.jenis_pengajuan === 'pengiriman_rutin') { resolveRateCardForVendor() } else { fetchKendaraanByPerusahaan(perusahaanTerpilih.id_perusahaan) } subStepKendaraan = 2; saveDraft()"
                    class="rounded-lg bg-avian-green px-4 py-2 text-sm font-medium text-white hover:bg-avian-green-dark"
                    x-text="pengajuan.jenis_pengajuan === 'pengiriman_rutin' ? 'Lanjut →' : 'Lanjut ke Kendaraan →'">
                </button>
            </div>

            {{-- Tombol Tambah Baru --}}
            <div class="mt-2 text-center">
                <button
                    type="button"
                    @click="kendaraanBaru.perusahaan_mode = 'baru'"
                    class="text-xs font-medium text-avian-green hover:underline">
                    + Atau Tambah Perusahaan Baru
                </button>
            </div>
        </div>

        {{-- Form: Tambah perusahaan baru --}}
        <div x-show="kendaraanBaru.perusahaan_mode === 'baru'" class="space-y-4 rounded-lg bg-gray-50 p-4">
            <div class="flex items-center justify-between">
                <h5 class="font-medium text-gray-700">Perusahaan Baru</h5>
                <button
                    type="button"
                    @click="kendaraanBaru.perusahaan_mode = 'pilih'; kendaraanBaru.nama_perusahaan = ''; kendaraanBaru.badan_usaha = ''; kendaraanBaru.no_telepon = ''; kendaraanBaru.alamat_kantor = ''; kendaraanBaru.identitas_owner_files = []; kendaraanBaru.identitas_owner_previews = []"
                    class="text-xs text-gray-500 hover:text-gray-700 underline">
                    ← Kembali ke Pilih
                </button>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <x-form-label required>Nama Perusahaan</x-form-label>
                    <input type="text" x-model="kendaraanBaru.nama_perusahaan"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-avian-green focus:outline-none">
                </div>
                <div>
                    <x-form-label required>Badan Usaha</x-form-label>
                    <select x-model="kendaraanBaru.badan_usaha"
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
                    <input type="text" x-model="kendaraanBaru.no_telepon"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-avian-green focus:outline-none">
                </div>
                <div>
                    <x-form-label required>Alamat Kantor</x-form-label>
                    <input type="text" x-model="kendaraanBaru.alamat_kantor"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-avian-green focus:outline-none">
                </div>
            </div>

            {{-- Identitas Owner Upload --}}
            <div>
                <x-form-label required>Upload Identitas Owner (KTP/NPWP/SIM) — Max 3 File</x-form-label>
                <input type="file" accept="image/*" multiple
                    x-ref="identitasOwnerInput"
                    @change="handleIdentitasOwnerUpload($event)"
                    class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-avian-green focus:outline-none">
                <p class="mt-2 text-xs text-gray-500">Format: JPG, PNG, WebP. Ukuran max 5MB per file.</p>

                {{-- Preview thumbnails --}}
                <div x-show="kendaraanBaru.identitas_owner_previews.length > 0" class="mt-3 flex gap-2 flex-wrap">
                    <template x-for="(preview, i) in kendaraanBaru.identitas_owner_previews" :key="i">
                        <div class="relative">
                            <img :src="preview" class="h-20 w-20 rounded-lg border border-gray-200 object-cover cursor-pointer hover:opacity-80"
                                @click="previewImageUrl = preview">
                            <button
                                type="button"
                                @click="kendaraanBaru.identitas_owner_files.splice(i, 1); kendaraanBaru.identitas_owner_previews.splice(i, 1); $refs.identitasOwnerInput.value = ''"
                                class="absolute -top-2 -right-2 rounded-full bg-red-500 text-white w-5 h-5 flex items-center justify-center text-xs font-bold hover:bg-red-600">
                                ×
                            </button>
                        </div>
                    </template>
                </div>
            </div>

            {{-- Image preview lightbox modal --}}
            <div x-show="previewImageUrl"
                @click.self="previewImageUrl = null"
                @keydown.escape="previewImageUrl = null"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-70 p-4">
                <div class="relative max-w-2xl max-h-[80vh] bg-white rounded-lg overflow-auto">
                    <button
                        type="button"
                        @click="previewImageUrl = null"
                        class="absolute top-3 right-3 bg-gray-200 rounded-full w-8 h-8 flex items-center justify-center shadow-lg hover:bg-gray-100 z-10">
                        ✕
                    </button>
                    <img :src="previewImageUrl" class="w-full h-auto">
                </div>
            </div>

            {{-- Buttons: Simpan / Batal --}}
            <div class="flex justify-end gap-3">
                <button
                    type="button"
                    @click="kendaraanBaru.perusahaan_mode = 'pilih'; kendaraanBaru.nama_perusahaan = ''; kendaraanBaru.badan_usaha = ''; kendaraanBaru.no_telepon = ''; kendaraanBaru.alamat_kantor = ''; kendaraanBaru.identitas_owner_files = []; kendaraanBaru.identitas_owner_previews = []"
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
    <div x-show="subStepKendaraan === 2" class="space-y-4 rounded-lg border border-gray-200 bg-gray-50 p-4">
        {{-- Data Vendor: 1 instance aja buat kedua jenis (jangan di dalam masing-masing
             cabang x-show di bawah - x-ref di dalamnya bisa dobel kalau dirender 2x). --}}
        <x-form-vendor-edit />

        {{-- Kiriman Rutin: panel ringan (tarif barang diisi di step 2 / Data Pengajuan) --}}
        <div x-show="pengajuan.jenis_pengajuan === 'pengiriman_rutin'" class="space-y-3">
            <div class="flex items-center justify-between">
                <h4 class="font-semibold text-gray-800">Langkah 2: Area &amp; Tarif Vendor</h4>
                <button type="button" @click="subStepKendaraan = 1"
                    class="text-xs text-gray-500 hover:text-gray-700 underline">← Ganti Vendor</button>
            </div>
            <p class="text-sm text-gray-600">Vendor: <strong x-text="perusahaanTerpilih?.nama_perusahaan"></strong></p>

            <p class="text-sm text-gray-500">
                Jenis barang &amp; tarif kiriman diisi di langkah berikutnya (Data Pengajuan).
                Barang yang belum punya tarif terdaftar bisa diisi harganya secara manual di sana.
            </p>

            {{-- Breakdown tarif barang yang udah terdaftar buat rate-card vendor ini (area cabang user) --}}
            <div x-show="rateCardVendorSkillIds.length && tarifKirimanRutinList.length > 0" class="rounded-lg border border-avian-green/30 bg-avian-green-light p-3">
                <p class="mb-2 text-xs font-medium text-avian-green">
                    <span x-text="tarifKirimanRutinList.length"></span> tarif barang terdaftar untuk area cabang Anda
                </p>
                <div class="space-y-1 text-sm">
                    <template x-for="t in tarifKirimanRutinList" :key="t.id_tarif">
                        <div class="flex items-center justify-between">
                            <span class="text-gray-700" x-text="t.nama_barang"></span>
                            <span class="font-medium text-gray-800" x-text="'Rp ' + Number(t.biaya_per_unit).toLocaleString('id-ID')"></span>
                        </div>
                    </template>
                </div>
            </div>
            <p x-show="rateCardVendorSkillIds.length && tarifKirimanRutinList.length === 0" class="text-sm text-gray-400">
                Belum ada tarif barang terdaftar untuk vendor ini di area cabang Anda.
            </p>

            <div class="flex justify-end">
                <button type="button" @click="subStepKendaraan = 3" :disabled="!perusahaanTerpilih"
                    :class="perusahaanTerpilih ? 'bg-avian-green text-white hover:bg-avian-green-dark' : 'bg-gray-100 text-gray-400 cursor-not-allowed'"
                    class="rounded-lg px-4 py-2 text-sm font-medium transition">Lanjut →</button>
            </div>
        </div>

        {{-- Sewa Truk: pilih/tambah kendaraan fisik --}}
        <div x-show="pengajuan.jenis_pengajuan === 'sewa_truk'" class="space-y-4">
            <div class="flex items-center justify-between">
                <h4 class="font-semibold text-gray-800">Langkah 2: Data Kendaraan</h4>
                <button
                    type="button"
                    @click="subStepKendaraan = 1"
                    class="text-xs text-gray-500 hover:text-gray-700 underline">
                    ← Ganti Perusahaan
                </button>
            </div>

            <p class="text-sm text-gray-600">Perusahaan: <strong x-text="perusahaanTerpilih?.nama_perusahaan"></strong></p>

            {{-- Daftar kendaraan existing milik perusahaan ini --}}
            <div x-show="kendaraanByPerusahaan.length > 0">
            <p class="mb-2 text-sm font-medium text-gray-700">Kendaraan Terdaftar</p>
            <div class="overflow-hidden rounded-lg border border-gray-200 bg-gray-50">
                <template x-for="a in kendaraanByPerusahaan" :key="a.id">
                    <div class="flex items-center justify-between border-b border-gray-100 px-4 py-3 last:border-0 cursor-pointer hover:bg-gray-50"
                         :class="kendaraanTerpilih?.id === a.id ? 'bg-avian-green-light' : ''"
                         @click="kendaraanTerpilih = (kendaraanTerpilih?.id === a.id ? null : a); showFormKendaraanBaru = false">
                        <div class="text-sm">
                            <span class="font-medium text-gray-800" x-text="a.kendaraan || '(Tanpa nama)'"></span>
                            <span class="text-gray-400 mx-1">·</span>
                            <span class="text-gray-500" x-text="a.plat_nomor_truk || 'Tanpa plat'"></span>
                            <span class="text-gray-400 mx-1">·</span>
                            <span class="text-gray-500" x-text="a.muatan"></span>
                        </div>
                        <span x-show="kendaraanTerpilih?.id === a.id" class="text-xs font-medium text-avian-green">✓ Dipilih</span>
                    </div>
                </template>
            </div>
        </div>
        <p x-show="!loadingKendaraanByPerusahaan && kendaraanByPerusahaan.length === 0" class="text-sm text-gray-400">
            Perusahaan ini belum punya kendaraan terdaftar.
        </p>

        {{-- Toggle form tambah kendaraan baru --}}
        <button type="button" @click="showFormKendaraanBaru = !showFormKendaraanBaru; if (showFormKendaraanBaru) kendaraanTerpilih = null"
            class="text-xs font-medium text-avian-green hover:underline"
            x-text="showFormKendaraanBaru ? '− Sembunyikan form tambah kendaraan' : '+ Tambah Kendaraan Baru'"></button>

        {{-- Form kendaraan baru --}}
        <div x-show="showFormKendaraanBaru" class="space-y-4 rounded-lg bg-gray-50 p-4">
            {{-- Jenis Kendaraan & Plat Nomor & Muatan Maksimal --}}
            <div class="grid grid-cols-3 gap-4">
                <div>
                    <x-form-label>Jenis Kendaraan</x-form-label>
                    <input type="text" x-model="kendaraanBaru.jenis_kendaraan" placeholder="Cth: Truk Box"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-avian-green focus:outline-none">
                </div>
                <div>
                    <x-form-label>Plat Nomor</x-form-label>
                    <input type="text" x-model="kendaraanBaru.plat_nomor_truk" placeholder="Cth: B 1234 XYZ"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-avian-green focus:outline-none">
                </div>
                <div>
                    <x-form-label required>Muatan Maksimal (Ton)</x-form-label>
                    <input type="number" x-model="kendaraanBaru.muatan_maksimal" step="0.01" min="0.01"
                        @wheel="$event.target.blur()"
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
                                    x-model="kendaraanBaru.id_skill"
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
                                <template x-if="kendaraanBaru.id_skill.length === 0 && kendaraanBaru.skillBaru.filter(s => s.trim() !== '').length === 0">
                                    <p class="text-xs text-gray-400">Belum ada area dipilih.</p>
                                </template>
                                <template x-for="s in kendaraanBaru.id_skill" :key="s">
                                    <span class="inline-flex items-center gap-1 rounded-full bg-avian-green-light px-2.5 py-0.5 text-xs font-medium text-avian-green">
                                        <span x-text="skillList.find(sk => String(sk.id_skill) === String(s))?.nama_skill ?? s"></span>
                                        <button
                                            type="button"
                                            @click="kendaraanBaru.id_skill = kendaraanBaru.id_skill.filter(x => x !== s)"
                                            class="hover:text-avian-green-dark leading-none">×</button>
                                    </span>
                                </template>
                                <template x-for="(s, i) in kendaraanBaru.skillBaru.filter(s => s.trim() !== '')" :key="'baru-'+i">
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
                                    @click="kendaraanBaru.skillBaru.push('')"
                                    class="rounded-lg border border-avian-green px-3 py-1 text-xs font-medium text-avian-green hover:bg-avian-green-light transition">
                                    + Tambah
                                </button>
                            </div>
                            <div class="space-y-2">
                                <template x-for="(s, i) in kendaraanBaru.skillBaru" :key="i">
                                    <div class="flex items-center gap-2">
                                        <input
                                            type="text"
                                            x-model="kendaraanBaru.skillBaru[i]"
                                            placeholder="Contoh: ACKOT CDE"
                                            class="flex-1 rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-avian-green focus:outline-none">
                                        <button
                                            type="button"
                                            @click="kendaraanBaru.skillBaru.splice(i, 1)"
                                            class="rounded-lg border border-red-200 px-2 py-2 text-red-400 hover:bg-red-50 transition">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                            </svg>
                                        </button>
                                    </div>
                                </template>
                                <p x-show="kendaraanBaru.skillBaru.length === 0" class="text-xs text-gray-400">
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
                    @click="saveKendaraan()"
                    :disabled="savingKendaraan"
                    class="rounded-lg bg-avian-green px-4 py-2 text-sm font-medium text-white hover:bg-avian-green-dark disabled:opacity-50 disabled:cursor-not-allowed">
                    <span x-text="savingKendaraan ? 'Menyimpan...' : 'Simpan & Pilih Kendaraan'"></span>
                </button>
            </div>
        </div>

        {{-- Tombol navigasi bawah --}}
        <div class="flex justify-end gap-3">
            <button
                type="button"
                @click="subStepKendaraan = 3"
                :disabled="!kendaraanTerpilih"
                :class="kendaraanTerpilih
                    ? 'bg-avian-green text-white hover:bg-avian-green-dark'
                    : 'bg-gray-100 text-gray-400 cursor-not-allowed'"
                class="rounded-lg px-4 py-2 text-sm font-medium transition">
                Lanjut →
            </button>
        </div>
        </div>{{-- END Sewa Truk sub-step 2 --}}
    </div>

    {{-- SUB-STEP 3: Ringkasan --}}
    <div x-show="subStepKendaraan === 3" class="space-y-4 rounded-lg border border-gray-200 bg-gray-50 p-4">
        <h4 class="font-semibold text-gray-800">Langkah 3: Ringkasan</h4>

        <div x-show="step2Done" class="rounded-lg bg-blue-50 border border-blue-200 p-4">
            <p class="mb-3 text-sm font-medium text-gray-700">Ringkasan Pilihan:</p>
            <div class="space-y-2 text-sm">
                <div>
                    <span class="text-gray-500" x-text="pengajuan.jenis_pengajuan === 'pengiriman_rutin' ? 'Vendor:' : 'Perusahaan:'"></span>
                    <span class="font-semibold text-gray-800" x-text="perusahaanTerpilih?.nama_perusahaan"></span>
                    <button type="button" @click="subStepKendaraan = 1" class="ml-2 text-xs text-avian-green hover:underline">Ganti</button>
                </div>
                <template x-if="pengajuan.jenis_pengajuan === 'sewa_truk'">
                    <div class="space-y-2">
                        <div>
                            <span class="text-gray-500">Kendaraan:</span>
                            <span class="font-semibold text-gray-800" x-text="kendaraanTerpilih?.kendaraan || '(Tanpa nama)'"></span>
                            <span class="text-gray-400" x-text="' • ' + (kendaraanTerpilih?.plat_nomor_truk || 'Tanpa plat')"></span>
                            <button type="button" @click="subStepKendaraan = 2" class="ml-2 text-xs text-avian-green hover:underline">Ganti</button>
                        </div>
                        <div>
                            <span class="text-gray-500">Muatan Maksimal:</span>
                            <span class="font-semibold text-gray-800" x-text="kendaraanTerpilih?.muatan"></span>
                        </div>
                    </div>
                </template>
                <template x-if="pengajuan.jenis_pengajuan === 'pengiriman_rutin'">
                    <div class="space-y-2">
                        <div>
                            <span class="text-gray-500">Badan Usaha:</span>
                            <span class="font-semibold text-gray-800" x-text="vendorEdit.badan_usaha || '—'"></span>
                        </div>
                        <div>
                            <span class="text-gray-500">Alamat:</span>
                            <span class="font-semibold text-gray-800" x-text="vendorEdit.alamat_kantor || '—'"></span>
                        </div>
                        <div x-show="tarifKirimanRutinList.length > 0">
                            <span class="text-gray-500">Tarif terdaftar:</span>
                            <span class="font-semibold text-gray-800" x-text="tarifKirimanRutinList.length + ' jenis barang'"></span>
                            <div class="mt-1 space-y-1 rounded-lg border border-gray-200 bg-white p-2 text-xs">
                                <template x-for="t in tarifKirimanRutinList" :key="t.id_tarif">
                                    <div class="flex items-center justify-between">
                                        <span class="text-gray-600" x-text="t.nama_barang"></span>
                                        <span class="font-medium text-gray-700" x-text="'Rp ' + Number(t.biaya_per_unit).toLocaleString('id-ID')"></span>
                                    </div>
                                </template>
                            </div>
                        </div>
                        <p x-show="tarifKirimanRutinList.length === 0" class="text-xs text-gray-400">
                            Belum ada tarif barang terdaftar untuk vendor ini.
                        </p>
                    </div>
                </template>
            </div>
        </div>

        <p x-show="!step2Done" class="text-sm text-gray-400">
            Lengkapi pilihan di langkah sebelumnya terlebih dahulu sebelum lanjut.
        </p>

        <div class="flex justify-between gap-3">
            <button type="button" @click="subStepKendaraan = 2"
                class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50">
                ← Kembali
            </button>
            <button type="button" @click="goToStep(2)" :disabled="!step2Done"
                :class="step2Done ? 'bg-avian-green text-white hover:bg-avian-green-dark' : 'bg-gray-100 text-gray-400 cursor-not-allowed'"
                class="rounded-lg px-4 py-2 text-sm font-medium transition">
                Lanjut →
            </button>
        </div>
    </div>
</div>{{-- END mini-stepper step 1 --}}
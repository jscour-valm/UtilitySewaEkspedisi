{{--
    Seksi "Profil Perusahaan" & "Kendaraan" — ditambahkan ke halaman edit
    Sewa Truk & Kiriman Rutin (16 Sept, keputusan Jo: digabung ke halaman yang
    sudah ada, bukan halaman Master Data terpisah). Beda dari
    form-vendor-edit.blade.php (dipakai di wizard Pengajuan Sewa, scope
    x-data="pengajuanSewa" + PATCH ditunda sampai submit pengajuan) — partial
    ini x-data LOKAL sendiri dan langsung commit ke DB tiap submit (lihat
    PengajuanController::updateVendor() & KendaraanController::store()/update()).

    Props:
    - vendorSkill: PerusahaanSkill (with('perusahaan'))
    - kendaraanList: Collection<Kendaraan> milik perusahaan ini DI CABANG yang sama
    - skillList: Collection {id_skill, nama_skill} — semua area aktif
    - cabangList: Collection {Code, Name} — dropdown "Cabang" (16 Sept, round 8)

    Field "Cabang" (16 Sept, round 8): dipindah render-nya ke sini secara
    VISUAL saja (Jo mau digrupkan bareng Profil Perusahaan) — tapi cabang_code
    tetap kepemilikan baris tarif (sesi_perusahaan_skill), BUKAN perusahaan.
    Makanya select-nya nggak dimasukkan ke x-data="vendorProfilEdit(...)" &
    nggak ikut submitProfil() (yang PATCH ke sesi_perusahaan_ekspedisi) — dia
    native <select> polos yang disambungkan ke form tarif (id="tarif-form" di
    edit.blade.php) lewat atribut HTML5 form="tarif-form", jadi tetap ke-submit
    bareng Skill/Harga Sewa dalam 1 POST/PUT yang sama.
--}}
@props(['vendorSkill', 'kendaraanList', 'skillList', 'cabangList'])

@php
    $existingPhotos = collect($vendorSkill->perusahaan->identitas_owner ?? [])
        ->map(fn ($p) => ['path' => $p, 'src' => \App\Helpers\FormatHelper::identitasOwnerSrc($p)])
        ->values();
    $isDci = auth()->user()?->userUtility?->role === 'DCI';
@endphp

{{-- ===== Profil Perusahaan ===== --}}
<div class="rounded-xl bg-white shadow-sm p-6"
    x-data="vendorProfilEdit({
        idPerusahaan: {{ (int) $vendorSkill->id_perusahaan }},
        namaPerusahaan: @js($vendorSkill->perusahaan->nama_perusahaan),
        badanUsaha: @js($vendorSkill->perusahaan->badan_usaha),
        noTelepon: @js($vendorSkill->perusahaan->no_telepon),
        alamatKantor: @js($vendorSkill->perusahaan->alamat_kantor),
        existingPhotos: @js($existingPhotos),
    })">
    <h2 class="text-lg font-semibold text-gray-900 mb-1">Profil Perusahaan</h2>
    <p class="text-sm text-gray-500 mb-4">Perubahan di sini langsung tersimpan ke data induk perusahaan (dipakai di semua cabang/skill vendor ini, bukan cuma baris ini).</p>

    <div x-show="message" :class="messageOk ? 'bg-avian-green-light text-avian-green border-avian-green/30' : 'bg-red-50 text-red-700 border-red-200'" class="rounded-lg border px-4 py-2 text-sm mb-4" x-text="message"></div>

    <div class="space-y-5">
        <div class="grid grid-cols-1 sm:grid-cols-4 gap-5">
            <div>
                <label class="mb-1 block text-xs font-medium text-gray-600">Nama Perusahaan <span class="text-red-500">*</span></label>
                <input type="text" x-model="namaPerusahaan" @disabled(!$isDci)
                    class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-avian-green focus:outline-none disabled:bg-gray-50 disabled:text-gray-500">
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-gray-600">Cabang <span class="text-red-500">*</span></label>
                <select id="cabang_code" name="cabang_code" form="tarif-form" @disabled(!$isDci)
                    class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-avian-green focus:outline-none disabled:bg-gray-50 disabled:text-gray-500">
                    @foreach($cabangList as $c)
                    <option value="{{ $c->Code }}" @selected($vendorSkill->cabang_code === $c->Code)>{{ $c->Code }} — {{ $c->Name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-gray-600">Badan Usaha <span class="text-red-500">*</span></label>
                <select x-model="badanUsaha" @disabled(!$isDci) class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-avian-green focus:outline-none disabled:bg-gray-50 disabled:text-gray-500">
                    <option value="">-- Pilih --</option>
                    <option value="PT">PT</option>
                    <option value="CV">CV</option>
                    <option value="UD">UD</option>
                    <option value="Perseorangan">Perseorangan</option>
                </select>
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-gray-600">No. Telepon <span class="text-red-500">*</span></label>
                <input type="text" x-model="noTelepon" @disabled(!$isDci) class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-avian-green focus:outline-none disabled:bg-gray-50 disabled:text-gray-500">
            </div>
            <div class="sm:col-span-4">
                <label class="mb-1 block text-xs font-medium text-gray-600">Alamat Kantor <span class="text-red-500">*</span></label>
                <input type="text" x-model="alamatKantor" @disabled(!$isDci) class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-avian-green focus:outline-none disabled:bg-gray-50 disabled:text-gray-500">
            </div>
        </div>

        <div>
            <label class="mb-1 block text-xs font-medium text-gray-600">Foto Identitas Owner (KTP/NPWP/SIM) — maks 3</label>
            <div class="flex gap-2 flex-wrap mt-2 mb-2">
                <template x-for="(photo, i) in keptPhotos" :key="'existing-' + photo.path">
                    <div class="relative">
                        <img :src="photo.src" @click="openPreview(i)" class="h-20 w-20 rounded-lg border border-gray-200 object-cover cursor-pointer hover:opacity-80">
                        @if($isDci)
                        <button type="button" @click.stop="removeKeptPhoto(i)"
                            class="absolute -top-2 -right-2 rounded-full bg-red-500 text-white w-5 h-5 flex items-center justify-center text-xs font-bold hover:bg-red-600">×</button>
                        @endif
                    </div>
                </template>
                <template x-for="(preview, i) in newPreviews" :key="'new-' + i">
                    <div class="relative">
                        <img :src="preview" @click="openPreview(keptPhotos.length + i)" class="h-20 w-20 rounded-lg border border-gray-200 object-cover cursor-pointer hover:opacity-80">
                        <button type="button" @click.stop="newFiles.splice(i, 1); newPreviews.splice(i, 1); $refs.fileInput.value = ''"
                            class="absolute -top-2 -right-2 rounded-full bg-red-500 text-white w-5 h-5 flex items-center justify-center text-xs font-bold hover:bg-red-600">×</button>
                    </div>
                </template>
            </div>

            {{-- Lightbox galeri foto identitas owner --}}
            <div x-show="previewIndex !== null" x-cloak
                @click.self="closePreview()"
                @keydown.escape.window="closePreview()"
                @keydown.arrow-left.window="prevPreview()"
                @keydown.arrow-right.window="nextPreview()"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-70 p-4">
                <div class="relative max-w-2xl w-full">
                    <button type="button" @click="closePreview()"
                        class="absolute -top-3 -right-3 bg-white rounded-full w-8 h-8 flex items-center justify-center shadow-lg hover:bg-gray-100 z-10">✕</button>

                    <div class="relative bg-white rounded-lg overflow-hidden">
                        <img :src="galleryPhotos[previewIndex]" class="w-full max-h-[65vh] object-contain bg-gray-100">
                        <template x-if="galleryPhotos.length > 1">
                            <button type="button" @click="prevPreview()"
                                class="absolute left-2 top-1/2 -translate-y-1/2 bg-white/90 rounded-full w-9 h-9 flex items-center justify-center shadow hover:bg-white">‹</button>
                        </template>
                        <template x-if="galleryPhotos.length > 1">
                            <button type="button" @click="nextPreview()"
                                class="absolute right-2 top-1/2 -translate-y-1/2 bg-white/90 rounded-full w-9 h-9 flex items-center justify-center shadow hover:bg-white">›</button>
                        </template>
                    </div>

                    <template x-if="galleryPhotos.length > 1">
                        <div class="flex gap-2 justify-center mt-3 flex-wrap">
                            <template x-for="(src, i) in galleryPhotos" :key="i">
                                <img :src="src" @click="previewIndex = i"
                                    :class="i === previewIndex ? 'ring-2 ring-avian-green opacity-100' : 'opacity-60 hover:opacity-100'"
                                    class="h-14 w-14 rounded-md object-cover cursor-pointer border border-gray-200 transition">
                            </template>
                        </div>
                    </template>
                </div>
            </div>
            @if($isDci)
            <input type="file" accept="image/*" multiple x-ref="fileInput" @change="handleFileChange($event)"
                :disabled="keptPhotos.length + newPreviews.length >= 3"
                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-avian-green focus:outline-none disabled:opacity-50 disabled:cursor-not-allowed">
            <p class="mt-1 text-xs text-gray-500" x-text="(keptPhotos.length + newPreviews.length) + '/3 foto — format JPG/PNG/WebP, maks 5MB per file'"></p>
            @endif
        </div>

        @if($isDci)
        <div class="flex justify-end pt-2 border-t border-gray-200">
            <button type="button" @click="submitProfil()" :disabled="saving"
                class="rounded-lg bg-avian-green px-4 py-2 text-sm font-medium text-white hover:bg-avian-green-dark transition disabled:opacity-50">
                <span x-show="!saving">Simpan Profil Perusahaan</span>
                <span x-show="saving">Menyimpan...</span>
            </button>
        </div>
        @endif
    </div>
</div>

{{-- ===== Kendaraan ===== --}}
{{--
    Catatan performa: sesi_master_skill sekarang punya ribuan baris (campuran
    area asli + nama kota/alamat yang ke-daftar otomatis via resolveSkillId()
    tiap ada input "area baru" bebas) — checkbox-nya WAJIB dirender client-side
    (Alpine x-for dari 1 array JSON, pola sama kayak step1.blade.php wizard),
    BUKAN @foreach Blade langsung ke HTML. Server-render 1 blok checkbox per
    baris kendaraan yang bisa diedit bakal ngalikan ukuran halaman berkali-kali
    lipat (ribuan checkbox x jumlah baris) — data skillList dikirim SEKALI ke
    Alpine (JSON), dipakai bareng sama form tambah & tiap baris edit via
    scope inheritance ($data Alpine merge otomatis ke child x-data).
--}}
<div class="rounded-xl bg-white shadow-sm p-6"
    x-data="kendaraanManager({
        idPerusahaan: {{ (int) $vendorSkill->id_perusahaan }},
        idCabang: @js($vendorSkill->cabang_code),
        skillList: @js($skillList),
    })">
    <div class="flex items-center justify-between mb-1">
        <h2 class="text-lg font-semibold text-gray-900">Kendaraan</h2>
        @if($isDci)
        <button type="button" @click="showAddForm = !showAddForm"
            :class="showAddForm ? 'bg-red-600 hover:bg-red-700' : 'bg-avian-green hover:bg-avian-green-dark'"
            class="rounded-lg px-4 py-2 text-sm font-medium text-white transition">
            <span x-show="!showAddForm">+ Tambah Kendaraan</span>
            <span x-show="showAddForm">Batal</span>
        </button>
        @endif
    </div>
    <p class="text-sm text-gray-500 mb-4">Kendaraan milik perusahaan ini di cabang {{ $vendorSkill->cabang_code }} saja.</p>

    <div x-show="message" :class="messageOk ? 'bg-avian-green-light text-avian-green border-avian-green/30' : 'bg-red-50 text-red-700 border-red-200'" class="rounded-lg border px-4 py-2 text-sm mb-4" x-text="message"></div>

    @if($isDci)
    {{-- Form tambah kendaraan baru --}}
    <div x-show="showAddForm" class="bg-blue-50 rounded-lg p-4 border border-blue-200 mb-4 space-y-3">
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <div>
                <label class="mb-1 block text-xs font-medium text-gray-600">Jenis Kendaraan</label>
                <input type="text" x-model="form.jenis_kendaraan" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-avian-green focus:outline-none">
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-gray-600">Plat Nomor</label>
                <input type="text" x-model="form.plat_nomor_truk" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-avian-green focus:outline-none">
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-gray-600">Muatan Maksimal (Ton) <span class="text-red-500">*</span></label>
                <input type="number" step="0.01" min="0.01" x-model="form.muatan_maksimal" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-avian-green focus:outline-none">
            </div>
        </div>
        <div>
            <label class="mb-1 block text-xs font-medium text-gray-600">Area/Skill <span class="text-red-500">*</span></label>
            <input type="text" x-model="addSkillSearch" placeholder="Cari area/skill..."
                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm mb-2 focus:border-avian-green focus:outline-none">
            <div class="flex flex-wrap gap-2 mb-2 max-h-40 overflow-y-auto border border-gray-200 rounded-lg p-2 bg-white">
                <template x-for="s in filteredSkills(addSkillSearch, form.id_skill)" :key="s.id_skill">
                    <label class="inline-flex items-center gap-1.5 rounded-full border border-gray-300 px-3 py-1 text-xs cursor-pointer has-[:checked]:border-avian-green has-[:checked]:bg-avian-green-light has-[:checked]:text-avian-green">
                        <input type="checkbox" :value="s.id_skill" x-model.number="form.id_skill" class="rounded border-gray-300">
                        <span x-text="s.nama_skill"></span>
                    </label>
                </template>
                <template x-if="filteredSkills(addSkillSearch, form.id_skill).length === 0">
                    <p class="text-xs text-gray-400 py-1" x-text="addSkillSearch.trim() === '' ? 'Ketik minimal 1 huruf buat cari area/skill.' : 'Nggak ketemu, coba kata kunci lain atau ketik area baru di bawah.'"></p>
                </template>
            </div>
            <input type="text" x-model="skillBaruInput" placeholder="Atau ketik nama area baru, pisahkan koma"
                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-avian-green focus:outline-none">
        </div>
        <div class="flex justify-end">
            <button type="button" @click="submitAdd()" :disabled="saving"
                class="rounded-lg bg-avian-green px-4 py-2 text-sm font-medium text-white hover:bg-avian-green-dark transition disabled:opacity-50">
                <span x-show="!saving">Simpan Kendaraan</span>
                <span x-show="saving">Menyimpan...</span>
            </button>
        </div>
    </div>
    @endif

    <div class="overflow-hidden rounded-xl border border-gray-200">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-gray-300 bg-gray-50 text-xs font-medium uppercase tracking-wide text-gray-500">
                    <th class="px-4 py-3 text-left">Jenis</th>
                    <th class="px-4 py-3 text-left">Plat Nomor</th>
                    <th class="px-4 py-3 text-right">Muatan (Ton)</th>
                    <th class="px-4 py-3 text-left">Area/Skill</th>
                    <th class="px-4 py-3 text-right">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse($kendaraanList as $k)
                <tr x-data="{
                        editing: false,
                        editSkillSearch: '',
                        edit: {
                            jenis_kendaraan: @js($k->jenis_kendaraan),
                            plat_nomor_truk: @js($k->plat_nomor_truk),
                            muatan_maksimal: {{ (float) $k->muatan_maksimal }},
                            id_skill: {{ Illuminate\Support\Js::from(array_values(array_filter(explode(',', (string) $k->id_skill), fn($s) => $s !== '' && is_numeric($s)))) }}.map(Number),
                        },
                        savingRow: false,
                    }" class="hover:bg-gray-50 transition">
                    <template x-if="!editing">
                        <td class="px-4 py-3 font-medium text-gray-800">{{ $k->jenis_kendaraan ?? '—' }}</td>
                    </template>
                    <template x-if="editing">
                        <td class="px-4 py-3"><input type="text" x-model="edit.jenis_kendaraan" class="w-full rounded-lg border border-gray-300 px-2 py-1 text-sm"></td>
                    </template>

                    <template x-if="!editing">
                        <td class="px-4 py-3 text-gray-600">{{ $k->plat_nomor_truk ?? '—' }}</td>
                    </template>
                    <template x-if="editing">
                        <td class="px-4 py-3"><input type="text" x-model="edit.plat_nomor_truk" class="w-full rounded-lg border border-gray-300 px-2 py-1 text-sm"></td>
                    </template>

                    <template x-if="!editing">
                        <td class="px-4 py-3 text-right text-gray-600">{{ rtrim(rtrim(number_format((float) $k->muatan_maksimal, 2, '.', ''), '0'), '.') }}</td>
                    </template>
                    <template x-if="editing">
                        <td class="px-4 py-3"><input type="number" step="0.01" min="0.01" x-model="edit.muatan_maksimal" class="w-full rounded-lg border border-gray-300 px-2 py-1 text-sm text-right"></td>
                    </template>

                    <template x-if="!editing">
                        <td class="px-4 py-3 text-gray-600">{{ \App\Helpers\FormatHelper::skillNames($k->id_skill) ? implode(', ', \App\Helpers\FormatHelper::skillNames($k->id_skill)) : '—' }}</td>
                    </template>
                    <template x-if="editing">
                        <td class="px-4 py-3">
                            <input type="text" x-model="editSkillSearch" placeholder="Cari area..."
                                class="w-full rounded-lg border border-gray-300 px-2 py-1 text-xs mb-1 focus:border-avian-green focus:outline-none">
                            <div class="flex flex-wrap gap-1 max-h-28 overflow-y-auto border border-gray-200 rounded-lg p-1.5 bg-white">
                                <template x-for="s in filteredSkills(editSkillSearch, edit.id_skill)" :key="s.id_skill">
                                    <label class="inline-flex items-center gap-1 rounded-full border border-gray-300 px-2 py-0.5 text-[11px] cursor-pointer has-[:checked]:border-avian-green has-[:checked]:bg-avian-green-light has-[:checked]:text-avian-green">
                                        <input type="checkbox" :value="s.id_skill" x-model.number="edit.id_skill" class="rounded border-gray-300">
                                        <span x-text="s.nama_skill"></span>
                                    </label>
                                </template>
                            </div>
                        </td>
                    </template>

                    <td class="px-4 py-3 text-right space-x-2 whitespace-nowrap">
                        @if($isDci)
                        <template x-if="!editing">
                            <span>
                                <button type="button" @click="editing = true" class="text-xs font-medium text-avian-green hover:text-avian-green-dark transition">Edit</button>
                                <button type="button" @click="if(confirm('Hapus kendaraan ini?')) deleteKendaraan({{ $k->id_kendaraan }})" class="text-xs font-medium text-red-600 hover:text-red-700 transition">Hapus</button>
                            </span>
                        </template>
                        <template x-if="editing">
                            <span>
                                <button type="button" :disabled="savingRow" @click="savingRow = true; updateKendaraan({{ $k->id_kendaraan }}, edit).finally(() => { savingRow = false; editing = false })" class="text-xs font-medium text-avian-green hover:text-avian-green-dark transition">Simpan</button>
                                <button type="button" @click="editing = false" class="text-xs font-medium text-gray-500 hover:text-gray-700 transition">Batal</button>
                            </span>
                        </template>
                        @else
                        <span class="text-gray-300">—</span>
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="5" class="py-12 text-center text-sm text-gray-400">Belum ada kendaraan terdaftar di cabang ini.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<script>
function vendorProfilEdit({ idPerusahaan, namaPerusahaan, badanUsaha, noTelepon, alamatKantor, existingPhotos }) {
    return {
        idPerusahaan,
        namaPerusahaan: namaPerusahaan || '',
        badanUsaha: badanUsaha || '',
        noTelepon: noTelepon || '',
        alamatKantor: alamatKantor || '',
        keptPhotos: [...existingPhotos], // {path, src} — sisa yang belum di-× dianggap "dipertahankan"
        newFiles: [],
        newPreviews: [],
        saving: false,
        message: '',
        messageOk: false,
        previewIndex: null, // null = lightbox tertutup; angka = index ke galleryPhotos()

        get galleryPhotos() {
            return [...this.keptPhotos.map(p => p.src), ...this.newPreviews];
        },
        openPreview(i) { this.previewIndex = i; },
        closePreview() { this.previewIndex = null; },
        prevPreview() {
            if (this.previewIndex === null) return;
            const n = this.galleryPhotos.length;
            this.previewIndex = (this.previewIndex - 1 + n) % n;
        },
        nextPreview() {
            if (this.previewIndex === null) return;
            this.previewIndex = (this.previewIndex + 1) % this.galleryPhotos.length;
        },
        removeKeptPhoto(i) {
            if (confirm('Yakin mau hapus foto ini? Foto akan hilang dari database begitu Simpan Profil Perusahaan ditekan.')) {
                this.keptPhotos.splice(i, 1);
                this.previewIndex = null;
            }
        },

        handleFileChange(e) {
            const files = Array.from(e.target.files || []);
            const total = this.keptPhotos.length + this.newPreviews.length + files.length;
            if (total > 3) {
                alert('Maksimal 3 foto identitas owner (existing + baru).');
                e.target.value = '';
                return;
            }
            this.newFiles.push(...files);
            files.forEach(f => this.newPreviews.push(URL.createObjectURL(f)));
        },

        async submitProfil() {
            if (!this.namaPerusahaan.trim() || !this.badanUsaha || !this.noTelepon.trim() || !this.alamatKantor.trim()) {
                this.message = 'Nama Perusahaan, Badan Usaha, No. Telepon, dan Alamat Kantor wajib diisi.';
                this.messageOk = false;
                return;
            }
            this.saving = true;
            this.message = '';
            try {
                const fd = new FormData();
                fd.append('nama_perusahaan', this.namaPerusahaan);
                fd.append('badan_usaha', this.badanUsaha);
                fd.append('no_telepon', this.noTelepon);
                fd.append('alamat_kantor', this.alamatKantor);
                this.keptPhotos.forEach(p => fd.append('identitas_owner_keep[]', p.path));
                this.newFiles.forEach(f => fd.append('identitas_owner[]', f));

                const res = await fetch(`/api/pengajuan/perusahaan/${this.idPerusahaan}`, {
                    method: 'POST',
                    body: fd,
                    headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '' },
                });
                const json = await res.json();
                if (json.success) {
                    this.message = json.message || 'Profil perusahaan berhasil diperbarui.';
                    this.messageOk = true;
                    window.clearDirty();
                    setTimeout(() => window.location.reload(), 800);
                } else {
                    this.message = json.message || 'Gagal menyimpan.';
                    this.messageOk = false;
                }
            } catch (e) {
                this.message = 'Error: ' + e.message;
                this.messageOk = false;
            } finally {
                this.saving = false;
            }
        },
    }
}

function kendaraanManager({ idPerusahaan, idCabang, skillList }) {
    return {
        idPerusahaan,
        idCabang,
        skillList: skillList || [],
        showAddForm: false,
        saving: false,
        message: '',
        messageOk: false,
        skillBaruInput: '',
        addSkillSearch: '',
        form: {
            jenis_kendaraan: '',
            plat_nomor_truk: '',
            muatan_maksimal: '',
            id_skill: [],
        },

        resetForm() {
            this.form = { jenis_kendaraan: '', plat_nomor_truk: '', muatan_maksimal: '', id_skill: [] };
            this.skillBaruInput = '';
            this.addSkillSearch = '';
        },

        // skillList sekarang ribuan baris (lihat catatan performa di atas
        // <template>) — jangan render semua sekaligus. Yang lagi dipilih
        // (selectedIds) selalu ikut muncul (biar bisa di-uncheck), sisanya
        // cuma muncul kalau user ngetik pencarian, dibatasi 100 hasil.
        filteredSkills(search, selectedIds) {
            const q = (search || '').trim().toLowerCase();
            const selected = new Set((selectedIds || []).map(String));
            const matches = this.skillList.filter(s => {
                if (selected.has(String(s.id_skill))) return true;
                if (q === '') return false;
                return s.nama_skill.toLowerCase().includes(q);
            });
            return matches.slice(0, 100);
        },

        csrf() {
            return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
        },

        async submitAdd() {
            const skillBaru = this.skillBaruInput.split(',').map(s => s.trim()).filter(Boolean);
            if (!this.form.muatan_maksimal || (this.form.id_skill.length === 0 && skillBaru.length === 0)) {
                this.message = 'Muatan Maksimal wajib diisi, dan pilih/tambahkan minimal 1 area/skill.';
                this.messageOk = false;
                return;
            }
            this.saving = true;
            this.message = '';
            try {
                const res = await fetch('/api/kendaraan', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': this.csrf() },
                    body: JSON.stringify({
                        id_perusahaan: this.idPerusahaan,
                        id_cabang: this.idCabang,
                        jenis_kendaraan: this.form.jenis_kendaraan || null,
                        plat_nomor_truk: this.form.plat_nomor_truk || null,
                        muatan_maksimal: this.form.muatan_maksimal,
                        id_skill: this.form.id_skill,
                        skill_baru: skillBaru,
                    }),
                });
                const json = await res.json();
                if (json.success) {
                    this.message = json.message || 'Kendaraan berhasil ditambahkan.';
                    this.messageOk = true;
                    this.resetForm();
                    this.showAddForm = false;
                    window.clearDirty();
                    setTimeout(() => window.location.reload(), 600);
                } else {
                    this.message = json.message || 'Gagal menyimpan kendaraan.';
                    this.messageOk = false;
                }
            } catch (e) {
                this.message = 'Error: ' + e.message;
                this.messageOk = false;
            } finally {
                this.saving = false;
            }
        },

        async updateKendaraan(id, edit) {
            this.message = '';
            try {
                const res = await fetch(`/api/kendaraan/${id}`, {
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': this.csrf() },
                    body: JSON.stringify({
                        jenis_kendaraan: edit.jenis_kendaraan || null,
                        plat_nomor_truk: edit.plat_nomor_truk || null,
                        muatan_maksimal: edit.muatan_maksimal,
                        id_skill: edit.id_skill,
                    }),
                });
                const json = await res.json();
                if (json.success) {
                    this.message = json.message || 'Kendaraan berhasil diperbarui.';
                    this.messageOk = true;
                    window.clearDirty();
                    setTimeout(() => window.location.reload(), 600);
                } else {
                    this.message = json.message || 'Gagal memperbarui kendaraan.';
                    this.messageOk = false;
                    alert(this.message);
                }
            } catch (e) {
                this.message = 'Error: ' + e.message;
                this.messageOk = false;
                alert(this.message);
            }
        },

        async deleteKendaraan(id) {
            try {
                const res = await fetch(`/api/kendaraan/${id}`, {
                    method: 'DELETE',
                    headers: { 'X-CSRF-TOKEN': this.csrf() },
                });
                const json = await res.json();
                if (json.success) {
                    window.clearDirty();
                    window.location.reload();
                } else {
                    alert(json.message || 'Gagal menghapus kendaraan.');
                }
            } catch (e) {
                alert('Error: ' + e.message);
            }
        },
    }
}
</script>

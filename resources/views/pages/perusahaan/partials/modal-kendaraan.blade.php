{{-- Modal tambah / edit unit kendaraan di detail perusahaan. Dipakai di dalam x-data="kelolaKendaraan(...)".
     Butuh $cabangKelola (cabang yang boleh dipilih). Cabang tidak bisa diganti saat edit. --}}
<div x-show="buka" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4" @keydown.escape.window="buka = false">
    <div class="absolute inset-0 bg-black/45" @click="buka = false"></div>
    <div class="relative z-10 max-h-[90vh] w-full max-w-xl overflow-y-auto rounded-2xl bg-white p-6 shadow-xl">
        <h3 class="mb-4 text-[17px] font-bold text-gray-900" x-text="idEdit ? 'Edit Kendaraan' : 'Tambah Kendaraan'"></h3>

        <div class="space-y-4">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <x-form-label required>Cabang</x-form-label>
                    <select x-model="form.id_cabang" @change="form.id_skill = []; muatSkill()" :disabled="!!idEdit"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-avian-green focus:outline-none disabled:bg-gray-50 disabled:text-gray-500">
                        <option value="">-- Pilih --</option>
                        @foreach($cabangKelola as $c)
                            <option value="{{ $c->Code }}">{{ $c->Code }} — {{ $c->Name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <x-form-label required>Jenis Kendaraan</x-form-label>
                    <select x-model="form.id_jenis_kendaraan" @change="pilihJenis()"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-avian-green focus:outline-none">
                        <option value="">-- Pilih --</option>
                        @foreach($jenisKendaraanList as $j)
                            <option value="{{ $j->id_jenis_kendaraan }}">{{ $j->nama_jenis }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <x-form-label>Plat Nomor</x-form-label>
                    <input type="text" x-model="form.plat_nomor_truk" maxlength="20"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm uppercase focus:border-avian-green focus:outline-none">
                </div>
                <div>
                    <x-form-label>Muatan Maksimal (Ton)</x-form-label>
                    <input type="text" :value="form.muatan_maksimal" readonly
                        class="w-full rounded-lg border border-gray-300 bg-gray-100 px-3 py-2 text-sm text-gray-600">
                    <p class="mt-1 text-xs text-gray-400">Otomatis dari jenis kendaraan.</p>
                </div>
            </div>

            <div>
                <x-form-label required>Area / Skill</x-form-label>
                <input type="text" x-model="cariSkill" placeholder="Cari area terdaftar di cabang…" :disabled="!form.id_cabang"
                    class="mb-2 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-avian-green focus:outline-none disabled:bg-gray-50">
                <div class="mb-2 flex max-h-40 flex-wrap gap-2 overflow-y-auto rounded-lg border border-gray-200 bg-white p-2">
                    <template x-for="s in skillTampil" :key="s.id_skill">
                        <label class="inline-flex cursor-pointer items-center gap-1.5 rounded-full border border-gray-300 px-3 py-1 text-xs has-[:checked]:border-avian-green has-[:checked]:bg-avian-green-light has-[:checked]:text-avian-green">
                            <input type="checkbox" :value="Number(s.id_skill)" x-model.number="form.id_skill" class="rounded border-gray-300">
                            <span x-text="s.nama_skill"></span>
                        </label>
                    </template>
                    <p x-show="skillTampil.length === 0" class="py-1 text-xs text-gray-400"
                        x-text="!form.id_cabang ? 'Pilih cabang dulu.' : (cariSkill.trim() === '' ? 'Ketik untuk mencari area.' : 'Tidak ketemu — ketik sebagai area baru di bawah.')"></p>
                </div>
                <input type="text" x-model="skillBaru" placeholder="Area baru (pisahkan dengan koma)" :disabled="!form.id_cabang"
                    class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-avian-green focus:outline-none disabled:bg-gray-50">
            </div>
        </div>

        <div class="mt-5 flex justify-end gap-3">
            <button type="button" @click="buka = false"
                class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50">Batal</button>
            <button type="button" @click="simpan()" :disabled="menyimpan"
                class="rounded-lg bg-avian-green px-4 py-2 text-sm font-medium text-white hover:bg-avian-green-dark disabled:opacity-50">
                <span x-text="menyimpan ? 'Menyimpan…' : 'Simpan'"></span>
            </button>
        </div>
    </div>
</div>

{{--
    Form "Usulkan Harga" (KG) di detail perusahaan. Dibuka lewat event `usulkan-harga` berisi
    jenis, id_skill, cabang_code, label, dan harga_sekarang (Sewa Truk) / harga_per_barang (Kiriman Rutin).
--}}
@props(['perusahaan', 'jenisBarang'])

<div x-data="formUsulanHarga(@js(route('persetujuan.harga.store')), {{ (int) $perusahaan->id_perusahaan }})"
    @usulkan-harga.window="buka($event.detail)" @keydown.escape.window="tutup()">
    <div x-show="terbuka" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/45" @click="tutup()"></div>
        <div class="relative z-10 max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-2xl bg-white p-6 shadow-xl">
            <h3 class="text-[17px] font-bold text-gray-900">Usulkan Harga Master</h3>
            <p class="mt-1 text-sm text-gray-500">
                <span x-text="ctx.jenis === 'sewa_truk' ? 'Sewa Truk' : 'Kiriman Rutin'"></span> ·
                <span x-text="ctx.label"></span>
            </p>
            <p class="mt-2 text-xs text-gray-400">Diproses validasi WM → approval WH. Harga master baru berubah setelah disetujui WH.</p>

            <div class="mt-4 space-y-4">
                <div x-show="ctx.jenis === 'pengiriman_rutin'">
                    <x-form-label required>Jenis Barang</x-form-label>
                    <select x-model="form.id_jenis_barang"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-avian-green focus:outline-none">
                        <option value="">-- Pilih --</option>
                        @foreach($jenisBarang as $jb)
                            <option value="{{ $jb->id_jenis_barang }}">{{ $jb->nama_barang }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="rounded-lg bg-gray-50 px-3 py-2 text-sm">
                    <span class="text-gray-500">Harga master sekarang:</span>
                    <span class="font-semibold text-gray-800" x-text="hargaSekarang === null ? 'belum ada' : rupiah(hargaSekarang)"></span>
                </div>

                <div>
                    <x-form-label required>Harga Usulan (Rp)<span x-show="ctx.jenis === 'pengiriman_rutin'"> per unit</span></x-form-label>
                    <input type="text" inputmode="numeric" placeholder="Contoh: 1.200.000"
                        :value="form.harga ? Number(form.harga).toLocaleString('id-ID') : ''"
                        @input="form.harga = $event.target.value.replace(/\D/g, '')"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-avian-green focus:outline-none">
                    <p x-show="selisihTeks" class="mt-1 text-xs font-medium" :class="Number(form.harga) > hargaSekarang ? 'text-red-600' : 'text-avian-green'" x-text="selisihTeks"></p>
                </div>

                <div>
                    <x-form-label>Catatan</x-form-label>
                    <textarea x-model="form.catatan" rows="3" maxlength="1000" placeholder="Alasan perubahan harga"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-avian-green focus:outline-none"></textarea>
                </div>
            </div>

            <div class="mt-5 flex justify-end gap-3">
                <button type="button" @click="tutup()"
                    class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50">Batal</button>
                <button type="button" @click="simpan()" :disabled="menyimpan"
                    class="rounded-lg bg-avian-green px-4 py-2 text-sm font-medium text-white hover:bg-avian-green-dark disabled:opacity-50">
                    <span x-text="menyimpan ? 'Mengajukan…' : 'Ajukan Usulan'"></span>
                </button>
            </div>
        </div>
    </div>
</div>

{{--
    Modal "Usulkan Harga" (KG). Dibuka lewat event `usulkan-harga` berisi jenis, id_skill,
    cabang_code, label, dan harga_sekarang (Sewa Truk) / harga_per_barang + usulan_berjalan
    (Kiriman Rutin). Kiriman Rutin: barang yang sudah punya harga di area itu tampil sebagai
    daftar pilihan; barang lain dari master bisa diusulkan lewat "Barang lain".
    Dipakai di: detail perusahaan.
    Props: perusahaan, jenisBarang.
--}}
@props(['perusahaan', 'jenisBarang'])

<div x-data="formUsulanHarga(@js(route('persetujuan.harga.store')), {{ (int) $perusahaan->id_perusahaan }}, @js($jenisBarang->map(fn ($jb) => ['id' => (int) $jb->id_jenis_barang, 'nama' => $jb->nama_barang])->values()))"
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
                <div x-show="ctx.jenis === 'pengiriman_rutin'" class="space-y-3">
                    {{-- Barang yang sudah punya harga master di area ini --}}
                    <div x-show="barangTerdaftar.length > 0">
                        <x-ui.form-label required>Harga terdaftar di area ini</x-ui.form-label>
                        <div class="divide-y divide-gray-100 overflow-hidden rounded-lg border border-gray-200">
                            <template x-for="b in barangTerdaftar" :key="b.id">
                                <button type="button" @click="pilihBarang(b.id, false)" :disabled="b.berjalan"
                                    class="flex w-full items-center justify-between gap-3 px-3 py-2 text-left text-sm transition disabled:cursor-not-allowed disabled:opacity-60"
                                    :class="!barangLain && Number(form.id_jenis_barang) === b.id ? 'bg-avian-green-light' : 'hover:bg-gray-50'">
                                    <span class="flex min-w-0 items-center gap-2">
                                        <span class="flex h-4 w-4 shrink-0 items-center justify-center rounded-full border"
                                            :class="!barangLain && Number(form.id_jenis_barang) === b.id ? 'border-avian-green' : 'border-gray-300'">
                                            <span class="h-2 w-2 rounded-full bg-avian-green" x-show="!barangLain && Number(form.id_jenis_barang) === b.id"></span>
                                        </span>
                                        <span class="truncate font-medium text-gray-800" x-text="b.nama"></span>
                                    </span>
                                    <span class="shrink-0 text-right">
                                        <span class="tabular-nums text-gray-700" x-text="rupiah(b.harga)"></span>
                                        <span class="block text-[11px] font-medium text-amber-700" x-show="b.berjalan">usulan sedang berjalan</span>
                                    </span>
                                </button>
                            </template>
                        </div>
                    </div>
                    <p x-show="barangTerdaftar.length === 0" class="text-xs text-gray-500">Belum ada harga terdaftar di area ini. Pilih barang yang mau diusulkan harganya.</p>

                    {{-- Barang dari master yang belum punya harga di area ini --}}
                    <div x-show="barangBelumAda.length > 0">
                        <button type="button" x-show="barangTerdaftar.length > 0 && !barangLain" @click="pilihBarang('', true)"
                            class="text-xs font-medium text-avian-green hover:underline">+ Usulkan harga untuk barang lain</button>
                        <div x-show="barangLain || barangTerdaftar.length === 0">
                            <x-ui.form-label required>Barang lain (belum ada harga di area ini)</x-ui.form-label>
                            <select x-model="form.id_jenis_barang" @change="barangLain = true; isiDariMaster()"
                                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-avian-green focus:outline-none">
                                <option value="">-- Pilih --</option>
                                <template x-for="b in barangBelumAda" :key="b.id">
                                    <option :value="b.id" :disabled="b.berjalan" x-text="b.nama + (b.berjalan ? ' (usulan sedang berjalan)' : '')"></option>
                                </template>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="rounded-lg bg-gray-50 px-3 py-2 text-sm">
                    <span class="text-gray-500">Harga master sekarang:</span>
                    <span class="font-semibold text-gray-800" x-text="hargaSekarang === null ? 'belum ada' : rupiah(hargaSekarang)"></span>
                </div>

                <div>
                    <x-ui.form-label required>Harga Usulan (Rp)<span x-show="ctx.jenis === 'pengiriman_rutin'"> per unit</span></x-ui.form-label>
                    <input type="text" inputmode="numeric" placeholder="Contoh: 1.200.000"
                        :value="form.harga ? Number(form.harga).toLocaleString('id-ID') : ''"
                        @input="form.harga = $event.target.value.replace(/\D/g, '')"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-avian-green focus:outline-none">
                    <p x-show="selisihTeks" class="mt-1 text-xs font-medium" :class="Number(form.harga) > hargaSekarang ? 'text-red-600' : 'text-avian-green'" x-text="selisihTeks"></p>
                </div>

                <div>
                    <x-ui.form-label>Catatan</x-ui.form-label>
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

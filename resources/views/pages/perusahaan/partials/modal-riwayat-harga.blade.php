{{-- Popup riwayat harga master 1 baris tarif; dibuka lewat $dispatch('riwayat-harga', {url, judul, barang}). --}}
<div x-data="riwayatHarga" @riwayat-harga.window="tampilkan($event.detail)" @keydown.escape.window="buka = false">
    <div x-show="buka" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/45" @click="buka = false"></div>
        <div class="relative z-10 max-h-[85vh] w-full max-w-2xl overflow-y-auto rounded-2xl bg-white p-6 shadow-xl">
            <div class="mb-4 flex items-start justify-between gap-3">
                <div>
                    <h3 class="text-[17px] font-bold text-gray-900">Riwayat Harga</h3>
                    <p class="text-sm text-gray-500" x-text="judul"></p>
                </div>
                <button type="button" @click="buka = false" class="text-xl leading-none text-gray-400 hover:text-gray-600">&times;</button>
            </div>

            <p x-show="memuat" class="py-8 text-center text-sm text-gray-400">Memuat…</p>
            <p x-show="!memuat && riwayat.length === 0" class="py-8 text-center text-sm text-gray-400">Belum ada perubahan harga tercatat.</p>

            <div x-show="!memuat && riwayat.length > 0" class="overflow-x-auto rounded-lg border border-gray-100">
                <table class="w-full min-w-[30rem] text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 bg-gray-50 text-[11px] font-semibold tracking-wide text-gray-500">
                            <th class="px-3 py-2 text-left">TANGGAL</th>
                            <th x-show="barisBarang" class="px-3 py-2 text-left">JENIS BARANG</th>
                            <th class="px-3 py-2 text-right">HARGA LAMA</th>
                            <th class="px-3 py-2 text-right">HARGA BARU</th>
                            <th class="px-3 py-2 text-left">DIUBAH OLEH</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="(r, i) in riwayat" :key="i">
                            <tr class="border-b border-gray-50">
                                <td class="whitespace-nowrap px-3 py-2 text-gray-600" x-text="r.tanggal ?? '—'"></td>
                                <td x-show="barisBarang" class="px-3 py-2 text-gray-700" x-text="r.barang ?? '—'"></td>
                                <td class="whitespace-nowrap px-3 py-2 text-right tabular-nums text-gray-500" x-text="rupiah(r.lama)"></td>
                                <td class="whitespace-nowrap px-3 py-2 text-right font-semibold tabular-nums text-gray-900" x-text="rupiah(r.baru)"></td>
                                <td class="px-3 py-2 text-gray-600" x-text="r.oleh ?? '—'"></td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

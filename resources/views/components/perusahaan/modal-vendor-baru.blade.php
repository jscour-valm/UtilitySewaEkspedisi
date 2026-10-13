{{--
    Tombol + modal tambah vendor. KG: vendor baru masuk proses validasi WM → approval WH.
    WM/WC/WH/DCI: vendor langsung aktif di master. Halaman dimuat ulang setelah tersimpan.
    Dipakai di: daftar Perusahaan, tab Vendor Baru dashboard KG.
    Props: isKg; kelasTombol (kelas ukuran tombol pemicu).
--}}
@props(['isKg' => false, 'kelasTombol' => 'px-4 py-2 text-sm'])

<div x-data="{ buka: false }" @keydown.escape.window="buka = false">
    <button type="button" @click="buka = true"
        class="whitespace-nowrap rounded-lg bg-avian-green font-medium text-white hover:bg-avian-green-dark {{ $kelasTombol }}">
        {{ $isKg ? '+ Ajukan Vendor Baru' : '+ Tambah Vendor' }}
    </button>

    <div x-show="buka" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/45" @click="buka = false"></div>
        <div class="relative z-10 max-h-[90vh] w-full max-w-2xl overflow-y-auto rounded-2xl bg-white p-6 shadow-xl"
            x-data="formVendor(@js(route($isKg ? 'pengajuan.store-perusahaan' : 'perusahaan.store')))">
            <h3 class="text-[17px] font-bold text-gray-900">{{ $isKg ? 'Ajukan Vendor Baru' : 'Tambah Vendor' }}</h3>
            <p class="mb-4 mt-1 text-sm text-gray-500">
                @if($isKg)
                    Vendor langsung bisa dipakai di pengajuan sewa, tapi pengajuannya baru bisa divalidasi WM setelah vendor disetujui (validasi WM → approval WH).
                @else
                    Vendor langsung aktif di master. Unit kendaraan ditambahkan dari halaman detail vendor.
                @endif
            </p>
            <x-perusahaan.form-vendor />
            <div class="mt-5 flex justify-end gap-3">
                <button type="button" @click="buka = false"
                    class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50">Batal</button>
                <button type="button" @click="simpan()" :disabled="menyimpan"
                    class="rounded-lg bg-avian-green px-4 py-2 text-sm font-medium text-white hover:bg-avian-green-dark disabled:opacity-50">
                    <span x-text="menyimpan ? 'Menyimpan…' : @js($isKg ? 'Ajukan Vendor' : 'Simpan Vendor')"></span>
                </button>
            </div>
        </div>
    </div>
</div>

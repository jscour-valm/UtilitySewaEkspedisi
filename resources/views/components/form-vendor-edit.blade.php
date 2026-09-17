{{--
    Form "Lengkapi Data Vendor" — awalnya cuma dipakai di step Data Pengajuan buat Kiriman Rutin,
    sekarang dipindah ke mini-stepper step1 (sub-step 2) dan dipakai buat KEDUA jenis pengajuan
    (Sewa Truk juga bisa koreksi data vendor dari sini). State Alpine `vendorEdit` sudah shared
    (di-prefill via prefillVendorEdit() tiap perusahaanTerpilih berubah, lihat app.js).

    Harus dirender di dalam scope x-data="pengajuanSewa".
--}}
<template x-if="perusahaanTerpilih">
    <div class="rounded-lg border border-gray-200 bg-gray-50 p-4 space-y-4">
        <div class="flex items-center justify-between">
            <h4 class="text-sm font-semibold text-gray-800">Data Vendor</h4>
            <span x-show="vendorEdit.dirty" class="rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-medium text-amber-700">diubah</span>
        </div>
        <p class="text-xs text-gray-500">
            Data vendor <strong x-text="perusahaanTerpilih?.nama_perusahaan"></strong> akan diperbarui saat pengajuan disubmit
            (kalau ada yang diubah). Nama perusahaan tidak bisa diubah di sini.
        </p>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div>
                <label class="mb-1 block text-xs font-medium text-gray-600">Badan Usaha <span class="text-red-500">*</span></label>
                <select x-model="vendorEdit.badan_usaha" @change="vendorEdit.dirty = true"
                    class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-avian-green focus:outline-none">
                    <option value="">-- Pilih --</option>
                    <option value="PT">PT</option>
                    <option value="CV">CV</option>
                    <option value="UD">UD</option>
                    <option value="Perseorangan">Perseorangan</option>
                </select>
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-gray-600">No. Telepon <span class="text-red-500">*</span></label>
                <input type="text" x-model="vendorEdit.no_telepon" @input="vendorEdit.dirty = true"
                    class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-avian-green focus:outline-none">
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-gray-600">Alamat Kantor <span class="text-red-500">*</span></label>
                <input type="text" x-model="vendorEdit.alamat_kantor" @input="vendorEdit.dirty = true"
                    class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-avian-green focus:outline-none">
            </div>
        </div>

        <div>
            <label class="mb-1 block text-xs font-medium text-gray-600">Upload Identitas Owner (KTP/NPWP/SIM) — Max 3 File (opsional)</label>
            <input type="file" accept="image/*" multiple
                x-ref="vendorIdentitasInput"
                @change="handleIdentitasOwnerUpload($event, 'vendorEdit')"
                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-avian-green focus:outline-none">
            <p class="mt-2 text-xs text-gray-500">Format: JPG, PNG, WebP. Max 5MB per file. Kosongkan kalau tidak ingin mengganti.</p>

            <div x-show="vendorEdit.identitas_owner_previews.length > 0" class="mt-3 flex gap-2 flex-wrap">
                <template x-for="(preview, i) in vendorEdit.identitas_owner_previews" :key="i">
                    <div class="relative">
                        <img :src="preview" class="h-20 w-20 rounded-lg border border-gray-200 object-cover cursor-pointer hover:opacity-80"
                            @click="previewImageUrl = preview">
                        <button type="button"
                            @click="vendorEdit.identitas_owner_files.splice(i, 1); vendorEdit.identitas_owner_previews.splice(i, 1); $refs.vendorIdentitasInput.value = ''"
                            class="absolute -top-2 -right-2 rounded-full bg-red-500 text-white w-5 h-5 flex items-center justify-center text-xs font-bold hover:bg-red-600">×</button>
                    </div>
                </template>
            </div>
        </div>

        {{-- Lightbox --}}
        <div x-show="previewImageUrl"
            @click.self="previewImageUrl = null"
            @keydown.escape.window="previewImageUrl = null"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-70 p-4">
            <div class="relative max-w-2xl max-h-[80vh] bg-white rounded-lg overflow-auto">
                <button type="button" @click="previewImageUrl = null"
                    class="absolute top-3 right-3 bg-white rounded-full w-8 h-8 flex items-center justify-center shadow-lg hover:bg-gray-100 z-10">✕</button>
                <img :src="previewImageUrl" class="w-full h-auto">
            </div>
        </div>
    </div>
</template>

{{-- Isian vendor (nama, badan usaha, kontak, identitas owner). Dipakai di dalam x-data="formVendor(...)". --}}
@props(['fotoLama' => []])

<div class="space-y-4">
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div>
            <x-form-label required>Nama Perusahaan</x-form-label>
            <input type="text" x-model="form.nama_perusahaan" maxlength="255"
                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-avian-green focus:outline-none">
        </div>
        <div>
            <x-form-label required>Badan Usaha</x-form-label>
            <select x-model="form.badan_usaha"
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
            <input type="text" x-model="form.no_telepon" maxlength="20"
                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-avian-green focus:outline-none">
        </div>
        <div>
            <x-form-label required>Alamat Kantor</x-form-label>
            <input type="text" x-model="form.alamat_kantor"
                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-avian-green focus:outline-none">
        </div>
    </div>

    <div>
        <x-form-label :required="empty($fotoLama)">Upload Identitas Owner (KTP/NPWP/SIM) — Maks 3 File</x-form-label>
        <input type="file" accept="image/*" multiple x-ref="fileInput" @change="pilihFile($event)"
            class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-avian-green focus:outline-none">
        <p class="mt-2 text-xs text-gray-500">
            Format: JPG, PNG, WebP. Ukuran maks 5MB per file.
            @if(! empty($fotoLama))
                Kosongkan kalau foto lama tetap dipakai; upload baru menggantikan semua foto lama.
            @endif
        </p>

        @if(! empty($fotoLama))
            <div x-show="previews.length === 0" class="mt-3 flex flex-wrap gap-2">
                @foreach($fotoLama as $i => $src)
                    <img src="{{ $src }}" alt="Identitas owner #{{ $i + 1 }}"
                        class="h-20 w-20 cursor-zoom-in rounded-lg border border-gray-200 object-cover hover:opacity-80"
                        @click="openLightbox(@js($fotoLama), {{ $i }})">
                @endforeach
            </div>
        @endif

        <div x-show="previews.length > 0" class="mt-3 flex flex-wrap gap-2">
            <template x-for="(preview, i) in previews" :key="i">
                <div class="relative">
                    <img :src="preview" class="h-20 w-20 cursor-pointer rounded-lg border border-gray-200 object-cover hover:opacity-80"
                        @click="openLightbox(previews, i)">
                    <button type="button" @click="hapusFile(i)"
                        class="absolute -right-2 -top-2 flex h-5 w-5 items-center justify-center rounded-full bg-red-500 text-xs font-bold text-white hover:bg-red-600">×</button>
                </div>
            </template>
        </div>
    </div>
</div>

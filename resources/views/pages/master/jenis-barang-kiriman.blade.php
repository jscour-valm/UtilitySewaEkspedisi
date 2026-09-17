@extends('layouts.app')

@section('title', 'Jenis Barang Kiriman')

@section('content')
<div class="flex flex-col gap-4 pb-2" x-data="jenisBarangKiriman()">
    <div class="rounded-xl bg-white shadow-sm p-6">
        <div class="flex items-center justify-between pb-4 border-b border-gray-200 mb-4">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Daftar Jenis Barang Kiriman</h1>
                <p class="text-gray-600 text-sm">Master jenis barang untuk fitur Kiriman Rutin — dipakai sbg dasar tarif per jenis barang di halaman Kelola Tarif.</p>
            </div>
            <button
                type="button"
                @click="showForm = !showForm; if (!showForm) resetForm()"
                :class="showForm ? 'bg-red-600 hover:bg-red-700' : 'bg-avian-green hover:bg-avian-green-dark'"
                class="rounded-lg px-4 py-2 text-sm font-medium text-white transition">
                <span x-show="!showForm">+ Tambah Jenis Barang</span>
                <span x-show="showForm">Batal</span>
            </button>
        </div>

        {{-- Add/Edit Form --}}
        <div x-show="showForm" class="bg-blue-50 rounded-lg p-4 border border-blue-200 mb-4">
            <h3 class="font-semibold text-gray-900 mb-4">
                <span x-show="!editingId">Tambah Jenis Barang Baru</span>
                <span x-show="editingId">Edit Jenis Barang</span>
            </h3>
            <div class="flex gap-3">
                <input
                    type="text"
                    x-model="formNamaBarang"
                    placeholder="Contoh: Cat Pail (Per Koli)"
                    class="flex-1 rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-avian-green focus:outline-none">
                <button
                    type="button"
                    @click="saveForm()"
                    :disabled="!formNamaBarang.trim() || isSaving"
                    :class="(!formNamaBarang.trim() || isSaving) ? 'opacity-50 cursor-not-allowed' : 'hover:bg-avian-green-dark'"
                    class="rounded-lg bg-avian-green px-4 py-2 text-sm font-medium text-white transition whitespace-nowrap">
                    <span x-show="!isSaving" x-text="editingId ? 'Perbarui' : 'Simpan'"></span>
                    <span x-show="isSaving">Menyimpan...</span>
                </button>
            </div>
        </div>

        {{-- Table --}}
        <div class="overflow-hidden rounded-xl border border-gray-200">
            <table class="w-full text-sm">
                <colgroup>
                    <col class="w-[40%]">
                    <col class="w-[20%]">
                    <col class="w-[20%]">
                    <col class="w-[20%]">
                </colgroup>
                <thead>
                    <tr class="border-b border-gray-300 bg-gray-50 text-xs font-medium uppercase tracking-wide text-gray-500">
                        <th class="px-4 py-3 text-left">Nama Barang</th>
                        <th class="px-4 py-3 text-left">Dibuat</th>
                        <th class="px-4 py-3 text-left">Diupdate</th>
                        <th class="px-4 py-3 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @forelse($jenisBarang as $jb)
                    <tr class="hover:bg-gray-50 transition">
                        <td class="px-4 py-3 font-medium text-gray-800">{{ $jb->nama_barang }}</td>
                        <td class="px-4 py-3 text-gray-500">{{ $jb->created_at?->translatedFormat('d M Y, H:i') ?? '—' }}</td>
                        <td class="px-4 py-3 text-gray-500">{{ $jb->updated_at?->translatedFormat('d M Y, H:i') ?? '—' }}</td>
                        <td class="px-4 py-3 text-right space-x-2">
                            <button
                                type="button"
                                @click="editItem({{ $jb->id_jenis_barang }}, @js($jb->nama_barang))"
                                class="text-xs font-medium text-avian-green hover:text-avian-green-dark transition">
                                Edit
                            </button>
                            <button
                                type="button"
                                @click="if(confirm('Hapus jenis barang ini?')) deleteItem({{ $jb->id_jenis_barang }})"
                                class="text-xs font-medium text-red-600 hover:text-red-700 transition">
                                Hapus
                            </button>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="4" class="py-12 text-center text-sm text-gray-400">Belum ada jenis barang terdaftar.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
function jenisBarangKiriman() {
    return {
        showForm: false,
        editingId: null,
        formNamaBarang: '',
        isSaving: false,

        resetForm() {
            this.editingId = null;
            this.formNamaBarang = '';
        },

        editItem(id, nama) {
            this.editingId = id;
            this.formNamaBarang = nama;
            this.showForm = true;
        },

        async saveForm() {
            if (!this.formNamaBarang.trim()) return;

            this.isSaving = true;
            try {
                const method = this.editingId ? 'PUT' : 'POST';
                const url = this.editingId ? `/api/jenis-barang-kiriman/${this.editingId}` : '/api/jenis-barang-kiriman';

                const res = await fetch(url, {
                    method: method,
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                    },
                    body: JSON.stringify({ nama_barang: this.formNamaBarang.trim() }),
                });
                const json = await res.json();

                if (json.success) {
                    location.reload();
                } else {
                    alert('Error: ' + (json.message || 'Gagal menyimpan'));
                }
            } catch (e) {
                console.error('Gagal simpan jenis barang:', e);
                alert('Gagal menyimpan jenis barang');
            } finally {
                this.isSaving = false;
            }
        },

        async deleteItem(id) {
            try {
                const res = await fetch(`/api/jenis-barang-kiriman/${id}`, {
                    method: 'DELETE',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                    },
                });
                const json = await res.json();

                if (json.success) {
                    location.reload();
                } else {
                    alert('Error: ' + (json.message || 'Gagal menghapus'));
                }
            } catch (e) {
                console.error('Gagal hapus jenis barang:', e);
                alert('Gagal menghapus jenis barang');
            }
        },
    }
}
</script>
@endsection

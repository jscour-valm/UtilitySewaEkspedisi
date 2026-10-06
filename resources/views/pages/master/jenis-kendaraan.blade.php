@extends('layouts.app')

@section('title', 'Jenis Kendaraan')

@section('content')
<div class="flex flex-col gap-4 pb-2" x-data="{
        items: @js($jenisKendaraan),
        showForm: false,
        editingId: null,
        formNama: '',
        formMuatan: '',
        isSaving: false,
        resetForm() { this.editingId = null; this.formNama = ''; this.formMuatan = '' },
        editItem(item) {
            this.editingId = item.id_jenis_kendaraan
            this.formNama = item.nama_jenis
            this.formMuatan = item.muatan_maksimal_ton
            this.showForm = true
        },
        async saveForm() {
            if (!this.formNama.trim() || !this.formMuatan) return
            this.isSaving = true
            try {
                const url = this.editingId ? `/api/jenis-kendaraan/${this.editingId}` : '/api/jenis-kendaraan'
                const res = await fetch(url, {
                    method: this.editingId ? 'PUT' : 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                    },
                    body: JSON.stringify({ nama_jenis: this.formNama, muatan_maksimal_ton: this.formMuatan }),
                })
                const json = await res.json()
                if (!res.ok || !json.success) {
                    notify(json.message || 'Gagal menyimpan jenis kendaraan', 'error')
                    return
                }
                if (this.editingId) {
                    const idx = this.items.findIndex(i => i.id_jenis_kendaraan === this.editingId)
                    if (idx !== -1) this.items[idx] = json.jenis_kendaraan
                } else {
                    this.items.push(json.jenis_kendaraan)
                    this.items.sort((a, b) => a.nama_jenis.localeCompare(b.nama_jenis))
                }
                this.showForm = false
                this.resetForm()
            } catch (e) {
                notify('Error: ' + e.message, 'error')
            } finally {
                this.isSaving = false
            }
        },
        async deleteItem(item) {
            if (!(await confirmDialog(`Hapus jenis kendaraan '${item.nama_jenis}'?`, {danger: true}))) return
            try {
                const res = await fetch(`/api/jenis-kendaraan/${item.id_jenis_kendaraan}`, {
                    method: 'DELETE',
                    headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                })
                const json = await res.json()
                if (!res.ok || !json.success) {
                    notify(json.message || 'Gagal menghapus jenis kendaraan', 'error')
                    return
                }
                this.items = this.items.filter(i => i.id_jenis_kendaraan !== item.id_jenis_kendaraan)
            } catch (e) {
                notify('Error: ' + e.message, 'error')
            }
        },
    }">
    <div class="rounded-xl bg-white shadow-sm p-6">
        <div class="flex items-center justify-between pb-4 border-b border-gray-200 mb-4">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Jenis Kendaraan</h1>
                <p class="text-gray-600 text-sm">Master lookup jenis kendaraan — muatan maksimal terkunci per jenis, biar KaGud nggak salah isi manual pas tambah kendaraan.</p>
            </div>
            <button
                type="button"
                @click="showForm = !showForm; if (!showForm) resetForm()"
                :class="showForm ? 'bg-red-600 hover:bg-red-700' : 'bg-avian-green hover:bg-avian-green-dark'"
                class="rounded-lg px-4 py-2 text-sm font-medium text-white transition">
                <span x-show="!showForm">+ Tambah Jenis Kendaraan</span>
                <span x-show="showForm">Batal</span>
            </button>
        </div>

        {{-- Add/Edit Form --}}
        <div x-show="showForm" class="bg-blue-50 rounded-lg p-4 border border-blue-200 mb-4">
            <h3 class="font-semibold text-gray-900 mb-4">
                <span x-show="!editingId">Tambah Jenis Kendaraan Baru</span>
                <span x-show="editingId">Edit Jenis Kendaraan</span>
            </h3>
            <div class="flex gap-3">
                <input
                    type="text"
                    x-model="formNama"
                    placeholder="Contoh: Truk Box"
                    class="flex-1 rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-avian-green focus:outline-none">
                <input
                    type="number" step="0.01" min="0.01"
                    x-model="formMuatan"
                    @wheel="$event.target.blur()"
                    placeholder="Muatan maksimal (ton)"
                    class="w-56 rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-avian-green focus:outline-none">
                <button
                    type="button"
                    @click="saveForm()"
                    :disabled="!formNama.trim() || !formMuatan || isSaving"
                    :class="(!formNama.trim() || !formMuatan || isSaving) ? 'opacity-50 cursor-not-allowed' : 'hover:bg-avian-green-dark'"
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
                    <col class="w-[35%]">
                    <col class="w-[20%]">
                    <col class="w-[15%]">
                    <col class="w-[15%]">
                    <col class="w-[15%]">
                </colgroup>
                <thead>
                    <tr class="border-b border-gray-300 bg-gray-50 text-xs font-medium uppercase tracking-wide text-gray-500">
                        <th class="px-4 py-3 text-left">Jenis Kendaraan</th>
                        <th class="px-4 py-3 text-right">Muatan Maksimal</th>
                        <th class="px-4 py-3 text-left">Dibuat</th>
                        <th class="px-4 py-3 text-left">Diupdate</th>
                        <th class="px-4 py-3 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <template x-for="item in items" :key="item.id_jenis_kendaraan">
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 font-medium text-gray-800" x-text="item.nama_jenis"></td>
                            <td class="px-4 py-3 text-right text-gray-600" x-text="Number(item.muatan_maksimal_ton).toLocaleString('id-ID') + ' ton'"></td>
                            <td class="px-4 py-3 text-gray-500" x-text="item.created_at ? new Date(item.created_at).toLocaleDateString('id-ID') : '—'"></td>
                            <td class="px-4 py-3 text-gray-500" x-text="item.updated_at ? new Date(item.updated_at).toLocaleDateString('id-ID') : '—'"></td>
                            <td class="px-4 py-3 text-right">
                                <button type="button" @click="editItem(item)" class="text-xs font-medium text-avian-green hover:underline mr-3">Edit</button>
                                <button type="button" @click="deleteItem(item)" class="text-xs font-medium text-red-500 hover:underline">Hapus</button>
                            </td>
                        </tr>
                    </template>
                    <tr x-show="items.length === 0">
                        <td colspan="5" class="py-12 text-center text-sm text-gray-400">Belum ada jenis kendaraan.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

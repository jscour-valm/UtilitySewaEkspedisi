{{--
  Simple Master CRUD Card Component
  Used in: Master Jenis Barang Kiriman, Master Jenis Biaya Tambahan

  Kartu list + form add/edit + delete buat master data 1-kolom-nama sederhana.

  @props([
    'title', 'description', 'addLabel', 'items',
    'idField', 'nameField', 'apiEndpoint', 'columnLabel', 'placeholder', 'itemNoun',
  ])
  @example
    <x-crud-simple-master
        title="Daftar Jenis Barang Kiriman"
        description="Master jenis barang untuk fitur Kiriman Rutin."
        addLabel="+ Tambah Jenis Barang"
        :items="$jenisBarang"
        idField="id_jenis_barang"
        nameField="nama_barang"
        apiEndpoint="/api/jenis-barang-kiriman"
        columnLabel="Nama Barang"
        placeholder="Contoh: Cat Pail (Per Koli)"
        itemNoun="jenis barang"
    />
--}}

@props([
    'title',
    'description',
    'addLabel',
    'items',
    'idField',
    'nameField',
    'apiEndpoint',
    'columnLabel',
    'placeholder',
    'itemNoun',
])

<div class="flex flex-col gap-4 pb-2" x-data="crudSimpleMaster({
        idField: {{ Illuminate\Support\Js::from($idField) }},
        nameField: {{ Illuminate\Support\Js::from($nameField) }},
        apiBase: {{ Illuminate\Support\Js::from($apiEndpoint) }},
    })">
    <div class="rounded-xl bg-white shadow-sm p-6">
        <div class="flex items-center justify-between pb-4 border-b border-gray-200 mb-4">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">{{ $title }}</h1>
                <p class="text-gray-600 text-sm">{{ $description }}</p>
            </div>
            <button
                type="button"
                @click="showForm = !showForm; if (!showForm) resetForm()"
                :class="showForm ? 'bg-red-600 hover:bg-red-700' : 'bg-avian-green hover:bg-avian-green-dark'"
                class="rounded-lg px-4 py-2 text-sm font-medium text-white transition">
                <span x-show="!showForm">{{ $addLabel }}</span>
                <span x-show="showForm">Batal</span>
            </button>
        </div>

        {{-- Add/Edit Form --}}
        <div x-show="showForm" class="bg-blue-50 rounded-lg p-4 border border-blue-200 mb-4">
            <h3 class="font-semibold text-gray-900 mb-4">
                <span x-show="!editingId">Tambah {{ $itemNoun }} Baru</span>
                <span x-show="editingId">Edit {{ ucfirst($itemNoun) }}</span>
            </h3>
            <div class="flex gap-3">
                <input
                    type="text"
                    x-model="formName"
                    placeholder="{{ $placeholder }}"
                    class="flex-1 rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-avian-green focus:outline-none">
                <button
                    type="button"
                    @click="saveForm()"
                    :disabled="!formName.trim() || isSaving"
                    :class="(!formName.trim() || isSaving) ? 'opacity-50 cursor-not-allowed' : 'hover:bg-avian-green-dark'"
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
                        <th class="px-4 py-3 text-left">{{ $columnLabel }}</th>
                        <th class="px-4 py-3 text-left">Dibuat</th>
                        <th class="px-4 py-3 text-left">Diupdate</th>
                        <th class="px-4 py-3 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @forelse($items as $item)
                    <tr class="hover:bg-gray-50 transition">
                        <td class="px-4 py-3 font-medium text-gray-800">{{ $item->{$nameField} }}</td>
                        <td class="px-4 py-3 text-gray-500">{{ $item->created_at?->translatedFormat('d M Y, H:i') ?? '—' }}</td>
                        <td class="px-4 py-3 text-gray-500">{{ $item->updated_at?->translatedFormat('d M Y, H:i') ?? '—' }}</td>
                        <td class="px-4 py-3 text-right space-x-2">
                            <button
                                type="button"
                                @click="editItem({{ $item->{$idField} }}, @js($item->{$nameField}))"
                                class="text-xs font-medium text-avian-green hover:text-avian-green-dark transition">
                                Edit
                            </button>
                            <button
                                type="button"
                                @click="confirmDialog('Hapus {{ $itemNoun }} ini?', {danger: true}).then(ok => ok && deleteItem({{ $item->{$idField} }}))"
                                class="text-xs font-medium text-red-600 hover:text-red-700 transition">
                                Hapus
                            </button>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="4" class="py-12 text-center text-sm text-gray-400">Belum ada {{ $itemNoun }} terdaftar.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
function crudSimpleMaster({ idField, nameField, apiBase }) {
    return {
        showForm: false,
        editingId: null,
        formName: '',
        isSaving: false,
        idField,
        nameField,
        apiBase,

        resetForm() {
            this.editingId = null;
            this.formName = '';
        },

        editItem(id, nama) {
            this.editingId = id;
            this.formName = nama;
            this.showForm = true;
        },

        async saveForm() {
            if (!this.formName.trim()) return;

            this.isSaving = true;
            try {
                const method = this.editingId ? 'PUT' : 'POST';
                const url = this.editingId ? `${this.apiBase}/${this.editingId}` : this.apiBase;

                const res = await fetch(url, {
                    method: method,
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                    },
                    body: JSON.stringify({ [this.nameField]: this.formName.trim() }),
                });
                const json = await res.json();

                if (json.success) {
                    location.reload();
                } else {
                    notify('Error: ' + (json.message || 'Gagal menyimpan'), 'error');
                }
            } catch (e) {
                console.error('Gagal simpan data:', e);
                notify('Gagal menyimpan data', 'error');
            } finally {
                this.isSaving = false;
            }
        },

        async deleteItem(id) {
            try {
                const res = await fetch(`${this.apiBase}/${id}`, {
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
                    notify('Error: ' + (json.message || 'Gagal menghapus'), 'error');
                }
            } catch (e) {
                console.error('Gagal hapus data:', e);
                notify('Gagal menghapus data', 'error');
            }
        },
    }
}
</script>

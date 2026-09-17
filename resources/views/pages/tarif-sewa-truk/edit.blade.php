@extends('layouts.app')

@section('title', 'Edit Tarif Sewa Truk')

@section('content')
@php $isDci = auth()->user()?->userUtility?->role === 'DCI'; @endphp
<div data-dirty-root class="flex flex-col gap-4 mb-2">
    @if(isset($errors) && $errors->any())
    <div class="rounded-xl bg-red-50 border border-red-200 text-red-700 px-4 py-3 text-sm">
        {{ $errors->first() }}
    </div>
    @endif

    <form id="tarif-form" class="hidden" method="POST" action="{{ route('kelola-tarif.sewa-truk.update', $vendorSkill->id_vendor_skill) }}">
        @csrf
        @method('PUT')
    </form>

    <x-form-vendor-kendaraan-edit :vendorSkill="$vendorSkill" :kendaraanList="$kendaraanList" :skillList="$skillList" :cabangList="$cabangList" />

    <div class="rounded-xl bg-white shadow-sm p-6"
        x-data="skillPicker({
            skillList: {{ Illuminate\Support\Js::from($tarifSkillList) }},
            selectedId: {{ $isSkillInScope ? (int) $vendorSkill->id_skill : 'null' }},
            skillBaru: '',
            legacyNama: {{ $isSkillInScope ? 'null' : Illuminate\Support\Js::from($vendorSkill->nama_skill) }},
            hargaSewa: {{ old('harga_sewa', $vendorSkill->harga_sewa) === null ? 'null' : old('harga_sewa', $vendorSkill->harga_sewa) }},
        })"
        @cabang-skill-updated.window="onCabangChanged($event.detail)">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">Harga Sewa per Skill</h2>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            {{-- Kiri: checklist skill/area yang terdaftar utk cabang ini --}}
            <div class="rounded-lg border border-gray-300 bg-white px-3 py-2 max-h-72 overflow-y-auto">
                <template x-if="skillList.length === 0">
                    <p class="text-xs text-gray-400 py-1">Nggak ada area terdaftar untuk cabang ini.</p>
                </template>
                <template x-for="s in skillList" :key="s.id_skill">
                    <label class="flex items-center gap-2 cursor-pointer py-1 hover:bg-gray-50 px-1 rounded">
                        <input type="checkbox" :checked="String(selectedId) === String(s.id_skill)" @change="select(s.id_skill)"
                            @disabled(!$isDci)
                            class="rounded border-gray-300 text-avian-green focus:ring-avian-green">
                        <span class="text-sm text-gray-700" x-text="s.nama_skill"></span>
                    </label>
                </template>
            </div>

            {{-- Kanan: area dipilih, tambah area baru, harga sewa (biar nggak ada kolom kosong) --}}
            <div class="flex flex-col gap-3">
                <div class="rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 min-h-20">
                    <p class="mb-2 text-xs font-medium text-gray-500">Area Dipilih</p>
                    <div class="flex flex-wrap gap-1.5">
                        <template x-if="!selectedId && skillBaru.trim() === '' && (!legacyNama || legacyDismissed || skillBaru.trim() === legacyNama)">
                            <p class="text-xs text-gray-400">Belum ada area dipilih.</p>
                        </template>
                        <template x-if="selectedId">
                            <span class="inline-flex items-center gap-1 rounded-full bg-avian-green-light px-2.5 py-0.5 text-xs font-medium text-avian-green">
                                <span x-text="selectedLabel"></span>
                                @if($isDci)
                                <button type="button" @click="selectedId = null" class="hover:text-avian-green-dark leading-none">×</button>
                                @endif
                            </span>
                        </template>
                        <template x-if="!selectedId && skillBaru.trim() !== ''">
                            <span class="inline-flex items-center gap-1 rounded-full bg-blue-50 px-2.5 py-0.5 text-xs font-medium text-blue-600">
                                <span x-text="skillBaru"></span>
                                <span class="text-blue-400 text-[10px]">baru</span>
                            </span>
                        </template>
                        {{-- Tag "data lama" — independen, tetap tampil meski selectedId/skillBaru udah keisi, sampai user klik × sendiri --}}
                        <template x-if="legacyNama && !legacyDismissed && skillBaru.trim() !== legacyNama">
                            <span class="inline-flex items-center gap-1 rounded-full bg-amber-50 border border-amber-300 px-2.5 py-0.5 text-xs font-medium text-amber-700">
                                @if($isDci)
                                <button type="button" @click="useLegacy()" class="hover:underline" x-text="legacyNama" title="Klik buat daftarkan area ini"></button>
                                @else
                                <span x-text="legacyNama"></span>
                                @endif
                                <span class="text-amber-500 text-[10px]">data lama</span>
                                @if($isDci)
                                <button type="button" @click="dismissLegacy()" class="hover:text-amber-900 leading-none" title="Abaikan data lama ini">×</button>
                                @endif
                            </span>
                        </template>
                    </div>
                </div>

                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-600">Tambah Area Baru</label>
                    <input type="text" x-model="skillBaru" @input="onSkillBaruInput()"
                        @disabled(!$isDci)
                        placeholder="Contoh: ACKOT CDE"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-avian-green focus:outline-none disabled:bg-gray-50 disabled:text-gray-500">
                </div>

                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-600">Harga Sewa (Rp) <span class="text-red-500">*</span></label>
                    <input
                        type="text" inputmode="numeric"
                        :value="formatRibuan(hargaSewa)"
                        @input="hargaSewa = parseRibuan($event.target.value)"
                        @disabled(!$isDci)
                        class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-avian-green focus:outline-none disabled:bg-gray-50 disabled:text-gray-500">
                </div>
            </div>
        </div>

        <input type="hidden" name="id_skill" form="tarif-form" :value="selectedId">
        <input type="hidden" name="skill_baru" form="tarif-form" :value="skillBaru">
        <input type="hidden" name="harga_sewa" form="tarif-form" :value="hargaSewa">

        <div class="flex justify-end gap-3 pt-4 mt-4 border-t border-gray-200">
            <a href="{{ route('kelola-tarif.sewa-truk') }}"
                class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 transition">
                {{ $isDci ? 'Batal' : 'Kembali' }}
            </a>
            @if($isDci)
            <button type="submit" form="tarif-form"
                class="rounded-lg bg-avian-green px-4 py-2 text-sm font-medium text-white hover:bg-avian-green-dark transition">
                Simpan Perubahan
            </button>
            @endif
        </div>
    </div>
</div>

<script>
function skillPicker({ skillList, selectedId, skillBaru, legacyNama, hargaSewa }) {
    return {
        skillList: skillList || [],
        selectedId: selectedId,
        skillBaru: skillBaru || '',
        legacyNama: legacyNama || null,
        legacyDismissed: false,
        hargaSewa: hargaSewa,
        select(id) {
            this.selectedId = (String(this.selectedId) === String(id)) ? null : id;
            if (this.selectedId) this.skillBaru = '';
        },
        onSkillBaruInput() {
            if (this.skillBaru.trim() !== '') this.selectedId = null;
        },
        useLegacy() {
            this.skillBaru = this.legacyNama;
            this.selectedId = null;
        },
        dismissLegacy() {
            this.legacyDismissed = true;
        },
        onCabangChanged(list) {
            this.skillList = list;
            if (!list.some(s => String(s.id_skill) === String(this.selectedId))) {
                this.selectedId = null;
            }
        },
        get selectedLabel() {
            const found = this.skillList.find(s => String(s.id_skill) === String(this.selectedId));
            return found ? found.nama_skill : null;
        },
    }
}

document.addEventListener('DOMContentLoaded', function () {
    const cabangSelect = document.getElementById('cabang_code');
    if (!cabangSelect) return;

    cabangSelect.addEventListener('change', async function (e) {
        try {
            const res = await fetch('/api/master-skill/by-cabang/' + encodeURIComponent(e.target.value));
            const list = await res.json();
            window.dispatchEvent(new CustomEvent('cabang-skill-updated', { detail: list }));
        } catch (err) {
            console.error('Gagal memuat daftar skill untuk cabang ini:', err);
        }
    });
});
</script>
@endsection

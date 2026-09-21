@extends('layouts.app')

@section('title', 'Edit Tarif Sewa Truk')

@section('content')
@php $isDci = auth()->user()?->userUtility?->role === 'DCI'; @endphp
<div data-dirty-root class="flex flex-col gap-4 mb-2">
    <x-alert-error />

    <form id="tarif-form" class="hidden" method="POST" action="{{ route('perusahaan.sewa-truk.update', $vendorSkill->id_vendor_skill) }}">
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

        <x-skill-picker-fields :isDci="$isDci" maxHeight="max-h-72">
            <div>
                <label class="mb-1 block text-xs font-medium text-gray-600">Harga Sewa (Rp) <span class="text-red-500">*</span></label>
                <input
                    type="text" inputmode="numeric"
                    :value="formatRibuan(hargaSewa)"
                    @input="hargaSewa = parseRibuan($event.target.value)"
                    @disabled(!$isDci)
                    class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-avian-green focus:outline-none disabled:bg-gray-50 disabled:text-gray-500">
            </div>
        </x-skill-picker-fields>

        <input type="hidden" name="harga_sewa" form="tarif-form" :value="hargaSewa">

        <div class="flex justify-end gap-3 pt-4 mt-4 border-t border-gray-200">
            <a href="{{ route('perusahaan.show', $vendorSkill->id_perusahaan) }}"
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
@endsection

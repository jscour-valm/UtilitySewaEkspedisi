@extends('layouts.app')

@section('title', 'Edit Tarif Kiriman Rutin')

@section('content')
@php $isDci = auth()->user()?->userUtility?->role === 'DCI'; @endphp
<div data-dirty-root class="flex flex-col gap-4 pb-2">
    <x-alert-error />

    <form id="tarif-form" class="hidden" method="POST" action="{{ route('perusahaan.kiriman-rutin.update', $vendorSkill->id_vendor_skill) }}">
        @csrf
        @method('PUT')
    </form>

    <x-form-vendor-kendaraan-edit :vendorSkill="$vendorSkill" :kendaraanList="$kendaraanList" :skillList="$skillList" :cabangList="$cabangList" :jenisKendaraanList="$jenisKendaraanList" />

    @php
        $hargaInit = collect($jenisBarangList)->mapWithKeys(
            fn ($jb) => [$jb->id_jenis_barang => old('harga.' . $jb->id_jenis_barang, optional($tarifExisting->get($jb->id_jenis_barang))->biaya_per_unit)]
        );
    @endphp

    <div class="rounded-xl bg-white shadow-sm p-6"
        x-data="skillPicker({
            skillList: {{ Illuminate\Support\Js::from($tarifSkillList) }},
            selectedId: {{ $isSkillInScope ? (int) $vendorSkill->id_skill : 'null' }},
            skillBaru: '',
            legacyNama: {{ $isSkillInScope ? 'null' : Illuminate\Support\Js::from($vendorSkill->nama_skill) }},
        })"
        @cabang-skill-updated.window="onCabangChanged($event.detail)">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">Tarif Kiriman Rutin</h2>

        <x-skill-picker-fields :isDci="$isDci" maxHeight="max-h-56" gridClass="mb-5" />

        <p class="text-sm text-gray-600 mb-3">Isi harga per unit tiap jenis barang. Kosongkan kalau vendor ini tidak melayani jenis barang tersebut di area ini.</p>

        <div class="overflow-hidden rounded-xl border border-gray-200" x-data="{ harga: {{ Illuminate\Support\Js::from($hargaInit) }} }">
            <table class="w-full text-sm">
                <colgroup>
                    <col class="w-[60%]">
                    <col class="w-[40%]">
                </colgroup>
                <thead>
                    <tr class="border-b border-gray-300 bg-gray-50 text-xs font-medium uppercase tracking-wide text-gray-500">
                        <th class="px-4 py-3 text-left">Jenis Barang</th>
                        <th class="px-4 py-3 text-left">Biaya Per Unit (Rp)</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @foreach($jenisBarangList as $jb)
                    <tr>
                        <td class="px-4 py-3 font-medium text-gray-800">{{ $jb->nama_barang }}</td>
                        <td class="px-4 py-2">
                            <input
                                type="text" inputmode="numeric"
                                :value="formatRibuan(harga[{{ $jb->id_jenis_barang }}])"
                                @input="harga[{{ $jb->id_jenis_barang }}] = parseRibuan($event.target.value)"
                                @disabled(!$isDci)
                                placeholder="—"
                                class="w-full rounded-lg border border-gray-300 px-3 py-1.5 text-sm focus:border-avian-green focus:outline-none disabled:bg-gray-50 disabled:text-gray-500">
                            <input type="hidden" name="harga[{{ $jb->id_jenis_barang }}]" form="tarif-form" :value="harga[{{ $jb->id_jenis_barang }}]">
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="flex justify-end gap-3 pt-4 mt-4 border-t border-gray-200">
            <a href="{{ route('perusahaan.show', $vendorSkill->id_perusahaan) }}"
                class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 transition">
                {{ $isDci ? 'Batal' : 'Kembali' }}
            </a>
            @if($isDci)
            <button type="submit" form="tarif-form"
                class="rounded-lg bg-avian-green px-4 py-2 text-sm font-medium text-white hover:bg-avian-green-dark transition">
                Simpan Semua Perubahan
            </button>
            @endif
        </div>
    </div>
</div>
@endsection

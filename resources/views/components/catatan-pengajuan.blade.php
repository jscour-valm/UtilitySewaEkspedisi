{{--
    Catatan KG + peringatan harga Sewa Truk yang beda dari master tanpa usulan perubahan master
    (mis. ada tambahan toko). KG menjelaskan alasannya lewat catatan pengajuan.
--}}
@props(['pengajuan', 'hargaMaster' => null])

@php
    use App\Helpers\FormatHelper;

    $master = $pengajuan->jenis_pengajuan === 'sewa_truk' ? ($hargaMaster['sewa'] ?? null) : null;
    $hargaBeda = $master !== null
        && round((float) $pengajuan->harga_sewa, 2) !== round((float) $master, 2)
        && ! $pengajuan->id_usulan_harga;
@endphp

@if($hargaBeda || $pengajuan->catatan_pengajuan)
<div {{ $attributes->merge(['class' => 'rounded-xl border border-gray-100 bg-white p-6 shadow-sm']) }}>
    @if($hargaBeda)
        <div class="mb-4 flex items-start gap-3 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
            <svg xmlns="http://www.w3.org/2000/svg" class="mt-0.5 h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" />
            </svg>
            <p>
                <strong>Harga beda dari harga master.</strong>
                Master {{ FormatHelper::rupiah($master) }}, diajukan {{ FormatHelper::rupiah($pengajuan->harga_sewa) }}
                (tanpa usulan perubahan master).
                {{ $pengajuan->catatan_pengajuan ? 'Penjelasan KG ada di catatan di bawah.' : 'KG tidak menulis catatan.' }}
            </p>
        </div>
    @endif
    @if($pengajuan->catatan_pengajuan)
        <p class="mb-3 text-xs font-semibold uppercase tracking-widest text-gray-400">Catatan</p>
        <p class="whitespace-pre-wrap text-sm leading-relaxed text-gray-700">{{ $pengajuan->catatan_pengajuan }}</p>
    @endif
</div>
@endif

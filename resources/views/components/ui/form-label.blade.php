{{--
    Label isian form; `required` menambah tanda bintang merah.
    Dipakai di: semua form (vendor, kendaraan, master skill, wizard, pengaturan).
--}}
@props(['required' => false])
<label {{ $attributes->merge(['class' => 'mb-1 block text-xs font-medium text-gray-600']) }}>
    {{ $slot }}
    @if($required)<span class="text-red-500">*</span>@endif
</label>

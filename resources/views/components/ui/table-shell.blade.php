{{--
    Wadah tabel lebar yang bisa di-scroll: border membulat, tinggi maksimal 70vh dengan header
    sticky, tabel `table-fixed` dengan lebar kolom lewat <colgroup>. Kolom sticky dan header-nya
    tetap diatur di view pemanggil.
    Dipakai di: Perusahaan (ketiga tab).
    Props: width (jumlah lebar semua <col>, px), fluid.
    - fluid=false (default): lebar px murni. Wajib kalau ada lebih dari satu kolom sticky berantai,
      karena offset `left` tiap kolom dihitung dari lebar kolom sebelumnya.
    - fluid=true: `width: max(100%, Npx)`, tabel melebar mengisi wadah tapi tidak menyempit di bawah
      N px. Hanya aman kalau kolom sticky paling banyak satu dengan `left: 0`.
    Slot: <colgroup>, <thead>, <tbody>. Kelas tambahan diteruskan ke wadah luar.
--}}
@props(['width' => null, 'fluid' => false])

@php
    $tableStyle = $width ? ($fluid ? 'width: max(100%, ' . (int) $width . 'px)' : 'width: ' . (int) $width . 'px') : null;
@endphp

<div {{ $attributes->merge(['class' => 'overflow-auto max-h-[70vh] rounded-xl border border-gray-200']) }}>
    <table class="table-fixed border-separate border-spacing-0 text-sm" @if($tableStyle) style="{{ $tableStyle }}" @endif>
        {{ $slot }}
    </table>
</div>

{{--
  Table Shell Component
  Used in: Halaman Perusahaan (perusahaan.index) — ketiga tab (Semua, Sewa Truk, Kiriman Rutin)

  Wadah tabel data lebar yang bisa di-scroll: border + sudut membulat, tinggi maksimal
  70vh (header sticky), tabel `table-fixed` dengan lebar kolom eksplisit lewat <colgroup>.
  Dipakai bareng biar ketiga tab tampil satu keluarga — kolom kiri yang mau nempel/sticky
  dan header-nya tetap dipasang di view (lihat contoh), komponen ini cuma wadahnya.

  Lebar tabel = jumlah lebar semua <col>. Dua mode:
  - `fluid=false` (default): lebar px MURNI (`width: Npx`) — WAJIB kalau tabel punya >1 kolom
    sticky berantai (offset `left` px dihitung dari lebar kolom sebelumnya; kalau tabel melebar,
    `table-fixed` mendistribusikan sisa lebar ke SEMUA kolom termasuk yang sticky dan offset
    `left` yang di-hardcode di view jadi salah / kolom bertumpuk).
  - `fluid=true`: `width: max(100%, Npx)` — tabel melebar mengisi wadah di layar lebar (nggak ada
    gutter kosong di kanan), tapi nggak menyempit di bawah N px (tetap scroll di layar kecil).
    HANYA aman kalau kolom sticky-nya paling banyak 1 dengan offset `left: 0` (lebar kolom itu
    sendiri boleh melar tanpa merusak apa pun, karena offsetnya selalu 0).

  @props(['width' => null, 'fluid' => false])
  Slot: <colgroup>, <thead>, <tbody>. Class tambahan diteruskan ke wadah luar.
  @example
    <x-table-shell :width="1090">
        <colgroup><col style="width: 260px">...</colgroup>
        <thead>...</thead>
        <tbody>...</tbody>
    </x-table-shell>
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

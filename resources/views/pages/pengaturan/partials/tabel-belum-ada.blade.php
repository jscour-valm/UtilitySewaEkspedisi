{{-- Peringatan di halaman Pengaturan kalau tabel pengaturannya belum dibuat (migration belum jalan). --}}
@unless($tabelSiap)
    <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
        Tabel pengaturan ini belum ada di database (migration belum dijalankan). Sementara dipakai aturan bawaan dan belum bisa diubah.
    </div>
@endunless

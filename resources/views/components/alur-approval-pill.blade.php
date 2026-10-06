@props([
    'alur' => ['WM'],          // urutan peran, mis. ['WM','WC','WH']
    'sudah' => [],             // peran yang sudah approve sejak submit terakhir
    'berikutnya' => null,      // peran yang sedang giliran (null kalau final/ditolak)
    'status' => 'pending',     // pending | approved | rejected
    'actingRole' => null,      // peran user yang sedang melihat (untuk label "Anda")
])

@php
    $status = strtolower($status);
    // Langkah yang menolak = langkah pertama yang belum approve saat status rejected.
    $ditolakOleh = null;
    if ($status === 'rejected') {
        foreach ($alur as $peranCek) {
            if (!in_array($peranCek, $sudah, true)) { $ditolakOleh = $peranCek; break; }
        }
    }
@endphp

<div class="flex items-center gap-2.5 mt-3.5 flex-wrap">
    <span class="text-[11px] font-semibold tracking-[0.09em] text-gray-500">ALUR</span>
    <div class="flex items-center gap-2.5 flex-wrap">
        @foreach($alur as $i => $peran)
            @php
                $aksi = $peran === 'WM' ? 'Validasi' : 'Approval';
                if (in_array($peran, $sudah, true)) {
                    [$wrap, $dot, $teks, $label] = ['bg-green-50 border-green-200', 'bg-green-600 text-white', 'text-green-800 font-semibold', 'Selesai'];
                } elseif ($peran === $ditolakOleh) {
                    [$wrap, $dot, $teks, $label] = ['bg-red-50 border-red-200', 'bg-red-600 text-white', 'text-red-700 font-semibold', 'Ditolak'];
                } elseif ($peran === $berikutnya) {
                    [$wrap, $dot, $teks, $label] = ['bg-amber-50 border-amber-200', 'bg-amber-500 text-white', 'text-amber-800 font-semibold', $peran === $actingRole ? 'Giliran Anda' : "Menunggu $aksi"];
                } else {
                    [$wrap, $dot, $teks, $label] = ['border-gray-200', 'border border-dashed border-gray-300 text-gray-400', 'text-gray-400', $aksi];
                }
            @endphp
            @if($i > 0)
                <span class="h-0.5 w-5 bg-gray-200"></span>
            @endif
            <div class="flex items-center gap-2 rounded-full border py-1 pl-1.5 pr-3 {{ $wrap }}">
                <span class="flex h-[19px] w-[19px] items-center justify-center rounded-full text-[11px] font-bold {{ $dot }}">{{ $i + 1 }}</span>
                <span class="text-[12.5px] {{ $teks }}">{{ $peran }} &middot; {{ $label }}</span>
            </div>
        @endforeach
    </div>
</div>

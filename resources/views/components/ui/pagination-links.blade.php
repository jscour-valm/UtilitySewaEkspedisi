{{--
    Pagination server-side (tautan GET biasa): teks "Menampilkan X–Y dari Z · Halaman N dari M",
    tombol Sebelumnya / nomor halaman dengan "…" / Berikutnya. Tidak tampil kalau hanya 1 halaman.
    Dipakai di: Perusahaan, Daftar Pengajuan, Master Skill, Setting Approver.
    Props: paginator (LengthAwarePaginator hasil ->paginate()->withQueryString()).
--}}
@props(['paginator'])

@if($paginator->hasPages())
<div class="mt-3 flex items-center justify-between flex-wrap gap-2">
    <span class="text-xs text-gray-500">
        Menampilkan {{ $paginator->firstItem() }}–{{ $paginator->lastItem() }} dari {{ $paginator->total() }} data
        · Halaman {{ $paginator->currentPage() }} dari {{ $paginator->lastPage() }}
    </span>
    <div class="flex flex-wrap items-center justify-end gap-1">
        {{-- Sebelumnya --}}
        @if($paginator->onFirstPage())
        <span class="rounded-lg px-3 py-1.5 text-sm text-gray-300 cursor-not-allowed">Sebelumnya</span>
        @else
        <a href="{{ $paginator->previousPageUrl() }}" class="rounded-lg px-3 py-1.5 text-sm text-gray-600 hover:bg-gray-100">Sebelumnya</a>
        @endif

        {{-- Nomor halaman dgn windowing "…" (onEachSide bawaan Laravel) --}}
        @foreach ($paginator->getUrlRange(1, $paginator->lastPage()) as $page => $url)
            @php
                $onEachSide = 1;
                $current = $paginator->currentPage();
                $last = $paginator->lastPage();
                $show = $page === 1 || $page === $last || abs($page - $current) <= $onEachSide;
            @endphp
            @if($show)
                @if($page === $current)
                <span class="rounded-lg px-3 py-1.5 text-sm font-medium bg-avian-green text-white">{{ $page }}</span>
                @else
                <a href="{{ $url }}" class="rounded-lg px-3 py-1.5 text-sm font-medium text-gray-600 hover:bg-gray-100">{{ $page }}</a>
                @endif
            @elseif($page === 2 && $current - $onEachSide > 2)
                <span class="rounded-lg px-3 py-1.5 text-sm text-gray-300 cursor-default">…</span>
            @elseif($page === $last - 1 && $current + $onEachSide < $last - 1)
                <span class="rounded-lg px-3 py-1.5 text-sm text-gray-300 cursor-default">…</span>
            @endif
        @endforeach

        {{-- Berikutnya --}}
        @if($paginator->hasMorePages())
        <a href="{{ $paginator->nextPageUrl() }}" class="rounded-lg px-3 py-1.5 text-sm text-gray-600 hover:bg-gray-100">Berikutnya</a>
        @else
        <span class="rounded-lg px-3 py-1.5 text-sm text-gray-300 cursor-not-allowed">Berikutnya</span>
        @endif
    </div>
</div>
@endif

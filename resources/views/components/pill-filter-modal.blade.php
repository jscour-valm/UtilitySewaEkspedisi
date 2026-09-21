{{--
  Pill Filter Modal Component
  Used in: Halaman Perusahaan (perusahaan.index)

  Tombol "Filter" + modal facet berbentuk pill (multi-select per kategori).
  Server-side: pill = checkbox di dalam <form method="GET"> milik halaman
  pemanggil, jadi filter ikut ke query string dan tetap benar dikombinasi
  dengan pagination/sort (beda dari versi lama di listKendaraan yang
  sembunyiin baris lewat JS). Komponen ini WAJIB dirender di dalam form GET.

  Style pill dipasang SEKALI di container (arbitrary variant), bukan per pill,
  karena opsi bisa ratusan (mis. Area/Skill) dan HTML-nya bakal bengkak.
  Facet dengan opsi banyak otomatis dapat kotak cari kecil (filter di sisi klien,
  cuma nyembunyiin pill yang nggak cocok — nggak ngubah pilihan yang sudah dicentang).

  @props(['facets'])   facets = [['key' => 'cabang', 'label' => 'Cabang', 'options' => [['value' => '01A', 'label' => '01A - Medan'], ...]], ...]
  @example
    <form method="GET">
        <x-pill-filter-modal :facets="$facets" />
    </form>
--}}

@props(['facets'])

@php
    $facetKeys = collect($facets)->pluck('key');
    $selected = collect($facets)->mapWithKeys(fn ($f) => [$f['key'] => array_map('strval', (array) request()->input($f['key'], []))]);
    $activeCount = $selected->sum(fn ($v) => count($v));
    // Reset = buang semua facet + page, sisanya (tab, search, sort) dipertahankan.
    $resetQuery = collect(request()->query())->except($facetKeys->push('page')->all())->all();
    $resetUrl = url()->current() . ($resetQuery ? '?' . http_build_query($resetQuery) : '');
    $searchThreshold = 15;
@endphp

<div x-data="{ open: false }" class="contents">
    <button type="button" @click="open = true"
        class="relative flex shrink-0 items-center gap-2 rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3 4h18M6 10h12M10 16h4" />
        </svg>
        Filter
        @if($activeCount > 0)
            <span class="rounded-full bg-avian-green px-1.5 py-0.5 text-[10px] font-semibold text-white">{{ $activeCount }}</span>
        @endif
    </button>

    <div x-show="open" x-cloak @keydown.escape.window="open = false"
        class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/40" @click="open = false" aria-hidden="true"></div>

        <div class="relative flex max-h-[85vh] w-full max-w-lg flex-col rounded-xl bg-white shadow-xl">
            <div class="flex items-center justify-between border-b border-gray-200 px-5 py-4">
                <h2 class="text-base font-semibold text-gray-800">Filter</h2>
                <button type="button" @click="open = false" class="rounded-full p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <div class="flex-1 space-y-5 overflow-y-auto px-5 py-4">
                @foreach($facets as $facet)
                    <div data-pill-facet>
                        <h3 class="mb-2 text-sm font-semibold text-gray-700">{{ $facet['label'] }}</h3>
                        @if(empty($facet['options']))
                            <span class="text-xs text-gray-400">Tidak ada data.</span>
                        @else
                            @if(count($facet['options']) > $searchThreshold)
                                <input type="search" data-pill-search placeholder="Cari {{ strtolower($facet['label']) }}..."
                                    class="mb-2 w-full rounded-lg border border-gray-300 px-3 py-1.5 text-xs focus:border-avian-green focus:outline-none">
                            @endif
                            <div class="flex max-h-44 flex-wrap gap-2 overflow-y-auto pr-1
                                [&_input]:sr-only [&_label]:cursor-pointer
                                [&_span]:block [&_span]:rounded-full [&_span]:border [&_span]:border-gray-300 [&_span]:px-3 [&_span]:py-1.5
                                [&_span]:text-xs [&_span]:font-medium [&_span]:text-gray-600 [&_span]:transition
                                [&_label:hover_span]:bg-gray-50
                                [&_input:checked+span]:border-avian-green [&_input:checked+span]:bg-avian-green-light [&_input:checked+span]:text-avian-green
                                [&_input:focus-visible+span]:ring-2 [&_input:focus-visible+span]:ring-avian-green/40">
                                @foreach($facet['options'] as $opt)
                                    <label><input type="checkbox" name="{{ $facet['key'] }}[]" value="{{ $opt['value'] }}" @checked(in_array((string) $opt['value'], $selected[$facet['key']], true))><span>{{ $opt['label'] }}</span></label>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>

            <div class="flex items-center justify-between border-t border-gray-200 px-5 py-4">
                <a href="{{ $resetUrl }}" class="text-sm font-medium text-gray-500 hover:text-gray-700">Reset</a>
                <button type="submit"
                    class="rounded-lg bg-avian-green px-4 py-2 text-sm font-medium text-white hover:bg-avian-green-dark">
                    Terapkan
                </button>
            </div>
        </div>
    </div>
</div>

<script>
if (!window.__pillFilterSearchWired) {
    window.__pillFilterSearchWired = true;
    document.addEventListener('input', function (e) {
        const input = e.target.closest('[data-pill-search]');
        if (!input) return;
        const q = input.value.trim().toLowerCase();
        input.closest('[data-pill-facet]').querySelectorAll('label').forEach(function (label) {
            label.hidden = q !== '' && !label.textContent.toLowerCase().includes(q);
        });
    });
    // Enter di kotak cari pill jangan submit form halaman.
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' && e.target.closest('[data-pill-search]')) e.preventDefault();
    });
}
</script>

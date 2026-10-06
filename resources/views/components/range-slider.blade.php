{{--
  Range Slider Component
  Used in: pill-filter-modal (facet "Harga Sewa" & "Harga per Unit" di halaman Perusahaan)

  Slider dua-pegangan (min & maks) dibangun sendiri pakai 2 <input type="range"> bertumpuk
  di atas 1 track (trik standar: kedua input full-width & transparan, `pointer-events-none`
  di track-nya, cuma thumb yang `pointer-events-auto` lewat arbitrary variant pseudo-element
  `[&::-webkit-slider-thumb]`/`[&::-moz-range-thumb]` — sama pola arbitrary-variant yang
  dipakai di pill-filter-modal buat styling pill). Nggak butuh library luar.

  Batasnya (`bounds`) diambil dari data asli (MIN/MAX kolom terkait) — user nggak bisa
  geser di luar rentang yang beneran ada. Kalau slider nggak digeser dari ujung, hidden
  input dikosongkan (nggak ngirim filter sama sekali) — konsisten sama query string bersih.

  @props(['minKey', 'maxKey', 'label', 'bounds', 'min' => null, 'max' => null, 'prefix' => ''])
    bounds = ['min' => 100000, 'max' => 5000000] (dari DB, MIN/MAX kolom asli)
    min/max = nilai yang lagi ke-apply dari query string (null = belum digeser, pakai bounds)
  @example
    <x-range-slider min-key="harga_min" max-key="harga_max" label="Harga Sewa" prefix="Rp"
        :bounds="['min' => 100000, 'max' => 5000000]" :min="$filters['harga_min']" :max="$filters['harga_max']" />
--}}

@props(['minKey', 'maxKey', 'label', 'bounds', 'min' => null, 'max' => null, 'prefix' => ''])

@php
    $boundMin = (int) ($bounds['min'] ?? 0);
    $boundMax = (int) ($bounds['max'] ?? 0);
    $vMin = $min !== null ? (int) $min : $boundMin;
    $vMax = $max !== null ? (int) $max : $boundMax;
    $thumbClass = '[&::-webkit-slider-thumb]:pointer-events-auto [&::-webkit-slider-thumb]:h-4 [&::-webkit-slider-thumb]:w-4'
        . ' [&::-webkit-slider-thumb]:appearance-none [&::-webkit-slider-thumb]:rounded-full [&::-webkit-slider-thumb]:bg-avian-green'
        . ' [&::-webkit-slider-thumb]:border-2 [&::-webkit-slider-thumb]:border-white [&::-webkit-slider-thumb]:shadow'
        . ' [&::-moz-range-thumb]:pointer-events-auto [&::-moz-range-thumb]:h-4 [&::-moz-range-thumb]:w-4'
        . ' [&::-moz-range-thumb]:appearance-none [&::-moz-range-thumb]:rounded-full [&::-moz-range-thumb]:bg-avian-green'
        . ' [&::-moz-range-thumb]:border-2 [&::-moz-range-thumb]:border-white [&::-moz-range-thumb]:shadow';
@endphp

<div x-data="{
        lo: {{ $vMin }}, hi: {{ $vMax }}, bmin: {{ $boundMin }}, bmax: {{ $boundMax }},
        fmt(v) { return '{{ $prefix }}' + Number(v).toLocaleString('id-ID'); },
        pct(v) { return this.bmax > this.bmin ? ((v - this.bmin) / (this.bmax - this.bmin)) * 100 : 0; },
    }" x-init="if (hi < lo) hi = lo">
    <h3 class="mb-2 text-sm font-semibold text-gray-700">{{ $label }}</h3>
    @if($boundMax <= $boundMin)
        <p class="text-xs text-gray-400 italic">Belum ada data harga.</p>
    @else
        <div class="mb-1.5 flex items-center justify-between text-xs font-medium text-gray-600">
            <span x-text="fmt(lo)"></span>
            <span x-text="fmt(hi)"></span>
        </div>
        <div class="relative h-5">
            <div class="absolute inset-x-0 top-1/2 h-1.5 -translate-y-1/2 rounded-full bg-gray-200"></div>
            <div class="absolute top-1/2 h-1.5 -translate-y-1/2 rounded-full bg-avian-green"
                :style="'left:' + pct(lo) + '%; right:' + (100 - pct(hi)) + '%'"></div>
            <input type="range" min="{{ $boundMin }}" max="{{ $boundMax }}" x-model.number="lo" @input="if (lo > hi) lo = hi"
                class="pointer-events-none absolute inset-x-0 top-0 h-5 w-full cursor-pointer appearance-none bg-transparent {{ $thumbClass }}">
            <input type="range" min="{{ $boundMin }}" max="{{ $boundMax }}" x-model.number="hi" @input="if (hi < lo) hi = lo"
                class="pointer-events-none absolute inset-x-0 top-0 h-5 w-full cursor-pointer appearance-none bg-transparent {{ $thumbClass }}">
        </div>
        <input type="hidden" name="{{ $minKey }}" :value="lo === bmin ? '' : lo">
        <input type="hidden" name="{{ $maxKey }}" :value="hi === bmax ? '' : hi">
    @endif
</div>

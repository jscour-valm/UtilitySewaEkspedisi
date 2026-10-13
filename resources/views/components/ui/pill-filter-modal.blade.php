{{--
    Tombol "Filter" + modal berisi facet pill (multi-pilih per kategori) dan facet rentang angka
    (<x-ui.range-slider>, selalu di bawah facet pill). Semua isian adalah input form GET milik
    halaman pemanggil, jadi filter ikut ke query string. Wajib dirender di dalam <form method="GET">.
    Dipakai di: Perusahaan, Daftar Pengajuan.
    Props:
    - facets: [['key' => 'cabang', 'label' => 'Cabang', 'options' => [['value' => '01A', 'label' => '01A - Medan'], ...],
               'count' => 153, 'requires' => 'key facet lain' (opsional)], ...]
    - ranges: [['key' => 'harga', 'label' => 'Harga Sewa', 'prefix' => 'Rp', 'bounds' => ['min' => .., 'max' => ..],
               'min' => nilai request, 'max' => nilai request], ...]
    - skillOptionsUrl: endpoint opsi Area Kirim; hanya dipakai kalau ada facet `skill` dengan `requires`.
    - kunciTambahan: nama parameter query milik slot `atas`, supaya ikut dihitung di badge jumlah
      filter aktif dan ikut dibuang oleh Reset.
    Slot `atas` (opsional): filter lain milik halaman pemanggil, dirender di atas facet pill.
    Catatan:
    - Gaya pill dipasang sekali di wadah (arbitrary variant), bukan per pill, karena opsi bisa ratusan.
    - Facet dengan banyak opsi otomatis mendapat kotak cari (menyaring di browser, pilihan tidak berubah).
    - Facet ber-`requires` baru menampilkan opsinya setelah facet yang disyaratkan punya pilihan;
      begitu pilihan itu berubah, opsinya diambil ulang lewat `skillOptionsUrl` tanpa reload.
--}}
@props(['facets', 'ranges' => [], 'skillOptionsUrl' => null, 'kunciTambahan' => []])

@php
    $facetKeys = collect($facets)->pluck('key');
    $rangeKeys = collect($ranges)->flatMap(fn ($r) => [$r['key'] . '_min', $r['key'] . '_max']);
    $selected = collect($facets)->mapWithKeys(fn ($f) => [$f['key'] => array_map('strval', (array) request()->input($f['key'], []))]);
    $activeCount = $selected->sum(fn ($v) => count($v)) + collect($ranges)->filter(function ($r) {
        return request()->filled($r['key'] . '_min') || request()->filled($r['key'] . '_max');
    })->count() + collect($kunciTambahan)->filter(fn ($k) => request()->filled($k))->count();
    // Reset = buang semua facet + range + page, sisanya (tab, search, sort) dipertahankan.
    $resetQuery = collect(request()->query())->except($facetKeys->merge($rangeKeys)->merge($kunciTambahan)->push('page')->all())->all();
    $resetUrl = url()->current() . ($resetQuery ? '?' . http_build_query($resetQuery) : '');
    $searchThreshold = 15;
    $facetsByKey = collect($facets)->keyBy('key');
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
                {{ $atas ?? '' }}
                @foreach($facets as $facet)
                    @php
                        $requires = $facet['requires'] ?? null;
                        $requiresMet = !$requires || count($selected[$requires] ?? []) > 0;
                    @endphp
                    <div data-pill-facet="{{ $facet['key'] }}" data-facet-label="{{ $facet['label'] }}" @if($requires) data-requires="{{ $requires }}" data-options-url="{{ $skillOptionsUrl }}" @endif>
                        <h3 class="mb-2 text-sm font-semibold text-gray-700">
                            {{ $facet['label'] }}
                            <span class="text-[11px] font-normal text-gray-400" data-facet-count>&middot; {{ $facet['count'] ?? count($facet['options']) }} pilihan</span>
                        </h3>
                        @if($requires)
                            <p class="text-xs italic text-gray-400" data-requires-placeholder @if($requiresMet) hidden @endif>
                                Pilih {{ $facetsByKey[$requires]['label'] ?? $requires }} dulu untuk menentukan {{ $facet['label'] }}.
                            </p>
                        @endif
                        <div data-pill-body @if($requires && !$requiresMet) hidden @endif>
                            {{-- Ringkasan pilihan sebagai chip dengan tombol ×; diisi & disinkronkan pillSyncChips() dari checkbox yang tercentang --}}
                            <div class="mb-2 rounded-lg border border-gray-200 bg-gray-50 px-3 py-2" data-pill-chips-box hidden>
                                <p class="mb-1.5 text-[11px] font-medium text-gray-500">{{ $facet['label'] }} Dipilih</p>
                                <div class="flex flex-wrap gap-1.5" data-pill-chips></div>
                            </div>
                            <span class="text-xs text-gray-400" data-empty-msg @if(!$requiresMet || !empty($facet['options'])) hidden @endif>Tidak ada data.</span>
                            <input type="search" data-pill-search placeholder="Cari {{ strtolower($facet['label']) }}..."
                                @if(!$requiresMet || count($facet['options']) <= $searchThreshold) hidden @endif
                                class="mb-2 w-full rounded-lg border border-gray-300 px-3 py-1.5 text-xs focus:border-avian-green focus:outline-none">
                            <div data-pill-options class="flex max-h-44 flex-wrap gap-2 overflow-y-auto pr-1
                                [&_input]:sr-only [&_label]:cursor-pointer
                                [&_span]:block [&_span]:rounded-full [&_span]:border [&_span]:border-gray-300 [&_span]:px-3 [&_span]:py-1.5
                                [&_span]:text-xs [&_span]:font-medium [&_span]:text-gray-600 [&_span]:transition
                                [&_label:hover_span]:bg-gray-50
                                [&_input:checked+span]:border-avian-green [&_input:checked+span]:bg-avian-green-light [&_input:checked+span]:text-avian-green
                                [&_input:focus-visible+span]:ring-2 [&_input:focus-visible+span]:ring-avian-green/40">
                                @if($requiresMet)
                                    @if($facet['key'] === 'skill')
                                        @include('partials.perusahaan-skill-options', ['options' => $facet['options'], 'selected' => $selected['skill']])
                                    @else
                                        @foreach($facet['options'] as $opt)
                                            <label><input type="checkbox" name="{{ $facet['key'] }}[]" value="{{ $opt['value'] }}" @checked(in_array((string) $opt['value'], $selected[$facet['key']], true))><span>{{ $opt['label'] }}</span></label>
                                        @endforeach
                                    @endif
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach

                @foreach($ranges as $range)
                    <x-ui.range-slider
                        :min-key="$range['key'] . '_min'"
                        :max-key="$range['key'] . '_max'"
                        :label="$range['label']"
                        :bounds="$range['bounds']"
                        :min="$range['min']"
                        :max="$range['max']"
                        :prefix="$range['prefix'] ?? ''" />
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

// Cascading Area Kirim: begitu Cabang dicentang/dilepas, tampilkan/sembunyikan blok Area
// Kirim SEKETIKA (tanpa nunggu klik "Terapkan"), lalu fetch ulang opsinya lewat AJAX supaya
// beneran sesuai cabang yang baru dicentang (bukan cuma nongol dgn daftar lama).
if (!window.__pillFilterCascadeWired) {
    window.__pillFilterCascadeWired = true;
    document.addEventListener('change', function (e) {
        if (!e.target.matches('[data-pill-facet="cabang"] input[type=checkbox]')) return;

        const cabangFacet = e.target.closest('[data-pill-facet="cabang"]');
        const checkedCabang = Array.from(cabangFacet.querySelectorAll('input[type=checkbox]:checked')).map(i => i.value);

        document.querySelectorAll('[data-pill-facet][data-requires="cabang"]').forEach(function (facet) {
            const placeholder = facet.querySelector('[data-requires-placeholder]');
            const body = facet.querySelector('[data-pill-body]');
            const optionsUrl = facet.getAttribute('data-options-url');
            if (!optionsUrl) return;

            if (checkedCabang.length === 0) {
                if (placeholder) placeholder.hidden = false;
                if (body) body.hidden = true;
                return;
            }
            if (placeholder) placeholder.hidden = true;
            if (body) body.hidden = false;

            // Pertahankan opsi facet ini yang sudah kecentang biar nggak hilang pas di-swap.
            const keptSelected = Array.from(facet.querySelectorAll('input[type=checkbox]:checked')).map(i => i.value);
            const params = new URLSearchParams();
            checkedCabang.forEach(v => params.append('cabang[]', v));
            keptSelected.forEach(v => params.append('skill[]', v));

            fetch(optionsUrl + '?' + params.toString())
                .then(function (r) { return r.text(); })
                .then(function (html) {
                    const container = facet.querySelector('[data-pill-options]');
                    if (!container) return;
                    container.innerHTML = html;
                    const n = container.querySelectorAll('label').length;
                    const countEl = facet.querySelector('[data-facet-count]');
                    if (countEl) countEl.textContent = '· ' + n + ' pilihan';
                    const emptyMsg = facet.querySelector('[data-empty-msg]');
                    if (emptyMsg) emptyMsg.hidden = n > 0;
                    const searchBox = facet.querySelector('[data-pill-search]');
                    if (searchBox) { searchBox.hidden = n <= {{ $searchThreshold }}; searchBox.value = ''; }
                    // Opsi barunya bawa balik checkbox yg ke-preserve (keptSelected) — sinkronkan
                    // ulang chip "Dipilih" facet ini juga, bukan cuma facet cabang yang di-klik.
                    pillSyncChips(facet);
                });
        });
    });
}

// Ringkasan "X Dipilih" per facet (Cabang, Area Kirim, Badan Usaha, dst) — dibangun dari
// checkbox yang beneran checked (bukan state Alpine, komponen ini vanilla-JS/form GET biasa),
// jadi selalu sinkron biarpun opsinya baru diganti AJAX (cascade Area Kirim) atau dicentang
// manual. Klik × di chip = uncheck checkbox aslinya + dispatch 'change' (bubbles) supaya
// listener cascade di atas & pillSyncChips lain tetap ke-trigger normal.
// Kotak chip default hidden — cuma ditampilkan kalau ADA yang dipilih DAN daftar opsinya
// panjang (butuh scroll, pakai ambang yang sama dgn kotak cari `$searchThreshold`); kalau
// opsinya sedikit (kelihatan semua tanpa scroll), chip cuma bikin ramai, jadi tetap hidden.
function pillSyncChips(facetEl) {
    const chipsBox = facetEl.querySelector('[data-pill-chips-box]');
    const box = facetEl.querySelector('[data-pill-chips]');
    if (!box || !chipsBox) return;

    const checked = Array.from(facetEl.querySelectorAll('[data-pill-options] input[type=checkbox]:checked'));
    const totalOptions = facetEl.querySelectorAll('[data-pill-options] label').length;
    chipsBox.hidden = !(checked.length > 0 && totalOptions > {{ $searchThreshold }});

    box.innerHTML = '';
    if (checked.length === 0) return;
    checked.forEach(function (input) {
        const text = input.nextElementSibling ? input.nextElementSibling.textContent.trim() : input.value;
        const chip = document.createElement('span');
        chip.className = 'inline-flex items-center gap-1 rounded-full bg-avian-green-light px-2.5 py-0.5 text-xs font-medium text-avian-green';

        const textSpan = document.createElement('span');
        textSpan.textContent = text;

        const removeBtn = document.createElement('button');
        removeBtn.type = 'button';
        removeBtn.className = 'hover:text-avian-green-dark leading-none';
        removeBtn.textContent = '×';
        removeBtn.addEventListener('click', function () {
            input.checked = false;
            input.dispatchEvent(new Event('change', { bubbles: true }));
        });

        chip.appendChild(textSpan);
        chip.appendChild(removeBtn);
        box.appendChild(chip);
    });
}

if (!window.__pillFilterChipsWired) {
    window.__pillFilterChipsWired = true;
    document.querySelectorAll('[data-pill-facet]').forEach(pillSyncChips);
    document.addEventListener('change', function (e) {
        if (!e.target.matches('[data-pill-options] input[type=checkbox]')) return;
        const facetEl = e.target.closest('[data-pill-facet]');
        if (facetEl) pillSyncChips(facetEl);
    });
}
</script>

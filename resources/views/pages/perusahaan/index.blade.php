@extends('layouts.app')

@section('title', 'Perusahaan')

@section('content')
@php
    $tabs = ['semua' => 'Semua', 'sewa-truk' => 'Sewa Truk', 'kiriman-rutin' => 'Kiriman Rutin'];
    $tabQuery = collect(request()->query())->except(['tab', 'page', 'sort', 'order'])->all();
    $stickyTh = 'sticky top-0 z-30 bg-gray-50';
    $plainTh = 'sticky top-0 z-20 bg-gray-50';
@endphp

<div class="flex flex-col gap-4 pb-2">
    <x-alert-success />

    <div class="rounded-xl bg-white shadow-sm p-6">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Perusahaan</h1>
                <p class="text-sm text-gray-500">Vendor ekspedisi beserta tarif dan kendaraannya.</p>
            </div>
            {{-- KG: 1 halaman aja (nggak ada gunanya split 3 tab, datanya udah disempitkan
                 ke cabang sendiri) — WM/WH/DCI tetap lihat switcher 3 tab. --}}
            @unless($isKg)
            <div class="inline-flex rounded-lg border border-gray-200 bg-gray-50 p-0.5 text-sm">
                @foreach($tabs as $key => $label)
                    <a href="{{ route('perusahaan.index', array_merge($tabQuery, $key === 'semua' ? [] : ['tab' => $key])) }}"
                        class="rounded-md px-3.5 py-1.5 font-medium transition
                        {{ $tab === $key ? 'bg-white text-avian-green shadow-sm' : 'text-gray-500 hover:text-gray-700' }}">
                        {{ $label }}
                    </a>
                @endforeach
            </div>
            @endunless
        </div>

        <form method="GET" class="mb-4 flex flex-wrap items-center gap-3">
            <input type="hidden" name="tab" value="{{ $tab }}">
            @if($sortBy)
                <input type="hidden" name="sort" value="{{ $sortBy }}">
                <input type="hidden" name="order" value="{{ $sortOrder }}">
            @endif
            <div class="relative min-w-0 flex-1" x-data="{ q: @js($search) }">
                <input
                    type="text"
                    name="search"
                    x-model="q"
                    placeholder="{{ $tab === 'semua' ? 'Cari nama perusahaan, badan usaha, cabang, atau area...' : 'Cari nama ekspedisi, kode cabang, atau area kirim...' }}"
                    class="w-full rounded-lg border border-gray-300 px-4 py-2 pr-9 text-sm focus:border-avian-green focus:outline-none">
                <button type="button" x-show="q.length > 0" x-cloak
                    @click="q = ''; $el.closest('form').submit()"
                    class="absolute right-2.5 top-1/2 -translate-y-1/2 rounded-full p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600"
                    aria-label="Bersihkan pencarian">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            <x-pill-filter-modal :facets="$facets" :ranges="$ranges" :skill-options-url="route('perusahaan.facet.skill-options')" />
        </form>

        {{-- Penjelas level agregasi — tab "Semua" 1 baris = 1 vendor (ringkasan), tab Sewa
        Truk/Kiriman Rutin 1 baris = 1 kombinasi vendor+cabang+area (rate card). Dua level
        beda ini disengaja (tujuan beda), tapi bikin bingung tanpa keterangan (review mentor
        item 3) — cukup 1 baris teks, tanpa ubah query apapun. --}}
        <p class="mb-4 text-xs text-gray-400">
            @if($tab === 'semua')
                Menampilkan 1 baris per vendor (ringkasan seluruh cabang &amp; area).
            @else
                Menampilkan 1 baris per kombinasi vendor + cabang + area kirim (rate card).
            @endif
        </p>

        @if($tab === 'semua')
            {{-- ==================== TAB SEMUA: 1 baris = 1 perusahaan ==================== --}}
            @if($isKg)
                {{-- POV KaGud (30 Sept): Cakupan/Kendaraan diganti detail per jenis tarif —
                     Sewa Truk = thumbnail dokumen identitas, Kiriman Rutin = kolom harga per
                     jenis barang (kolom yang sering keisi di kiri, jarang digeser ke kanan). --}}
                @php
                    $widths = ['nama' => 200, 'badan_usaha' => 140, 'dokumen' => 160, 'harga_sewa' => 160, 'diperbarui' => 130, 'aksi' => 90];
                    $totalWidth = array_sum($widths) + count($jenisBarangCols) * 140;
                @endphp
                {{-- 1 Okt: shared hover-popover (1 x-teleport doang buat SELURUH tabel) — tiap
                baris bisa punya banyak sel hover (Harga Sewa + N kolom jenis barang), dan
                x-teleport per-sel (versi sebelumnya) kena bug Alpine (teleport ke-2 dst dalam
                baris yang sama gak pernah muncul). Dipindah ke 1 state bersama di wrapper ini. --}}
                <div x-data="{
                        hoverPop: { open: false, pos: '', left: 0, title: '', items: [], el: null },
                        showPop(el, title, items) {
                            if (!items || !items.length) return;
                            this.hoverPop.el = el;
                            this.repositionPop();
                            this.hoverPop.title = title;
                            this.hoverPop.items = items;
                            this.hoverPop.open = true;
                        },
                        hidePop() { this.hoverPop.open = false; },
                        repositionPop() {
                            if (!this.hoverPop.el) return;
                            const rect = this.hoverPop.el.getBoundingClientRect();
                            this.hoverPop.left = Math.max(8, Math.min(rect.left, window.innerWidth - 272));
                            this.hoverPop.pos = (rect.bottom + 200 > window.innerHeight) ? 'bottom:' + (window.innerHeight - rect.top) + 'px' : 'top:' + rect.bottom + 'px';
                        }
                    }"
                    @scroll.window.capture="if (hoverPop.open) repositionPop()">
                <x-table-shell :width="$totalWidth" :fluid="true">
                    <colgroup>
                        <col style="width: {{ $widths['nama'] }}px">
                        <col style="width: {{ $widths['badan_usaha'] }}px">
                        <col style="width: {{ $widths['dokumen'] }}px">
                        <col style="width: {{ $widths['harga_sewa'] }}px">
                        @foreach($jenisBarangCols as $col)
                            <col style="width: 140px">
                        @endforeach
                        <col style="width: {{ $widths['diperbarui'] }}px">
                        <col style="width: {{ $widths['aksi'] }}px">
                    </colgroup>
                    <thead>
                        <tr class="border-b border-gray-300 bg-gray-50 text-xs font-medium uppercase tracking-wide text-gray-500">
                            <x-sortable-th col="nama" label="Perusahaan" :class="$stickyTh . ' border-r border-gray-200'" style="left: 0px" :sortBy="$sortBy" :sortOrder="$sortOrder" sortParam="sort" orderParam="order" />
                            <x-sortable-th col="badan_usaha" label="Badan Usaha" :class="$plainTh" :sortBy="$sortBy" :sortOrder="$sortOrder" sortParam="sort" orderParam="order" />
                            <th class="{{ $plainTh }} px-3 py-3 text-left whitespace-nowrap">Dokumen Identitas</th>
                            <th class="{{ $plainTh }} px-3 py-3 text-right whitespace-nowrap">Harga Sewa</th>
                            @foreach($jenisBarangCols as $col)
                                <th class="{{ $plainTh }} px-3 py-3 text-right whitespace-nowrap" title="{{ $col['nama_barang'] }}">{{ $col['nama_barang'] }}</th>
                            @endforeach
                            <x-sortable-th col="diperbarui" label="Diperbarui" :class="$plainTh" :sortBy="$sortBy" :sortOrder="$sortOrder" sortParam="sort" orderParam="order" />
                            <th class="{{ $plainTh }} px-3 py-3 text-right whitespace-nowrap">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @forelse($rows as $r)
                        <tr class="group hover:bg-gray-50 transition">
                            <td class="sticky z-10 bg-white group-hover:bg-gray-50 px-3 py-3 font-medium text-gray-800 border-r border-gray-200 truncate" style="left: 0px" title="{{ $r->nama_perusahaan }}">{{ $r->nama_perusahaan }}</td>
                            <td class="px-3 py-3 text-gray-600 truncate">{{ $r->badan_usaha ?: '—' }}</td>
                            <td class="px-3 py-3">
                                @if(count($r->identitas_owner_src))
                                    <div class="flex items-center gap-1">
                                        @foreach(array_slice($r->identitas_owner_src, 0, 3) as $i => $src)
                                            <img src="{{ $src }}" @click="openLightbox(@js($r->identitas_owner_src), {{ $i }})"
                                                class="h-10 w-10 cursor-zoom-in rounded-md border border-gray-200 object-cover hover:ring-2 hover:ring-avian-green"
                                                alt="Dokumen identitas {{ $r->nama_perusahaan }}">
                                        @endforeach
                                    </div>
                                @else
                                    <span class="text-gray-400">—</span>
                                @endif
                            </td>
                            <td class="px-3 py-3 text-right text-gray-600 whitespace-nowrap"
                                @mouseenter="showPop($el, 'Harga Sewa', @js($r->sewa_items))"
                                @mouseleave="hidePop()">
                                @if(count($r->sewa_items) > 1)
                                    <span class="cursor-default border-b border-dotted border-gray-400">{{ count($r->sewa_items) }} harga</span>
                                @elseif(count($r->sewa_items) === 1)
                                    <span class="cursor-default border-b border-dotted border-gray-400">Rp {{ number_format($r->sewa_items[0]['harga'], 0, ',', '.') }}</span>
                                @else
                                    <span class="text-gray-400">—</span>
                                @endif
                            </td>
                            @foreach($jenisBarangCols as $col)
                                @php $items = $r->kiriman_items[$col['id_jenis_barang']] ?? []; @endphp
                                <td class="px-3 py-3 text-right tabular-nums text-gray-600 whitespace-nowrap"
                                    @mouseenter="showPop($el, @js($col['nama_barang']), @js($items))"
                                    @mouseleave="hidePop()">
                                    @if(count($items) > 1)
                                        <span class="cursor-default border-b border-dotted border-gray-400">{{ count($items) }} harga</span>
                                    @elseif(count($items) === 1)
                                        <span class="cursor-default border-b border-dotted border-gray-400">Rp {{ number_format($items[0]['harga'], 0, ',', '.') }}</span>
                                    @else
                                        <span class="text-gray-400">—</span>
                                    @endif
                                </td>
                            @endforeach
                            <td class="px-3 py-3 text-gray-600 whitespace-nowrap">
                                {{ $r->last_update ? \Carbon\Carbon::parse($r->last_update)->translatedFormat('d M Y') : '—' }}
                            </td>
                            <td class="px-3 py-3 text-right">
                                <a href="{{ route('perusahaan.show', $r->id_perusahaan) }}"
                                    class="rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-600 hover:bg-gray-50">
                                    Detail
                                </a>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="{{ 6 + count($jenisBarangCols) }}" class="py-12 text-center text-sm text-gray-400">
                                @if($search !== '')
                                    Tidak ada hasil untuk &quot;{{ $search }}&quot;.
                                @else
                                    Belum ada perusahaan yang terhubung ke cabang Anda.
                                @endif
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </x-table-shell>
                <template x-teleport="body">
                    <div x-show="hoverPop.open" x-cloak @mouseenter="hoverPop.open = true" @mouseleave="hidePop()"
                        class="fixed z-50 py-1" :style="hoverPop.pos + ';left:' + hoverPop.left + 'px'">
                        <div class="max-h-72 w-64 overflow-y-auto rounded-lg border border-gray-200 bg-white p-3 text-xs shadow-lg text-left">
                            <p class="mb-1 font-semibold text-gray-700" x-text="hoverPop.title + ' (' + hoverPop.items.length + ')'"></p>
                            <ul class="space-y-1 text-gray-600">
                                <template x-for="(it, i) in hoverPop.items" :key="i">
                                    <li class="flex justify-between gap-2 tabular-nums">
                                        <span class="truncate" x-text="it.area || '—'"></span>
                                        <span class="font-medium text-gray-800 whitespace-nowrap" x-text="'Rp ' + Number(it.harga).toLocaleString('id-ID')"></span>
                                    </li>
                                </template>
                            </ul>
                        </div>
                    </div>
                </template>
                </div>
            @else
            @php
                $widths = ['nama' => 280, 'badan_usaha' => 160, 'cakupan' => 170, 'tarif' => 190, 'kendaraan' => 120, 'diperbarui' => 130, 'aksi' => 90];
            @endphp
            <x-table-shell :width="array_sum($widths)" :fluid="true">
                <colgroup>
                    @foreach($widths as $w)
                        <col style="width: {{ $w }}px">
                    @endforeach
                </colgroup>
                <thead>
                    <tr class="border-b border-gray-300 bg-gray-50 text-xs font-medium uppercase tracking-wide text-gray-500">
                        <x-sortable-th col="nama" label="Perusahaan" :class="$stickyTh . ' border-r border-gray-200'" style="left: 0px" :sortBy="$sortBy" :sortOrder="$sortOrder" sortParam="sort" orderParam="order" />
                        <x-sortable-th col="badan_usaha" label="Badan Usaha" :class="$plainTh" :sortBy="$sortBy" :sortOrder="$sortOrder" sortParam="sort" orderParam="order" />
                        <x-sortable-th col="cakupan" label="Cakupan" :class="$plainTh" :sortBy="$sortBy" :sortOrder="$sortOrder" sortParam="sort" orderParam="order" />
                        <th class="{{ $plainTh }} px-3 py-3 text-left whitespace-nowrap">Tarif</th>
                        <x-sortable-th col="kendaraan" label="Kendaraan" :class="$plainTh" :sortBy="$sortBy" :sortOrder="$sortOrder" sortParam="sort" orderParam="order" />
                        <x-sortable-th col="diperbarui" label="Diperbarui" :class="$plainTh" :sortBy="$sortBy" :sortOrder="$sortOrder" sortParam="sort" orderParam="order" />
                        <th class="{{ $plainTh }} px-3 py-3 text-right whitespace-nowrap">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @forelse($rows as $r)
                    <tr class="group hover:bg-gray-50 transition">
                        <td class="sticky z-10 bg-white group-hover:bg-gray-50 px-3 py-3 font-medium text-gray-800 border-r border-gray-200 truncate" style="left: 0px" title="{{ $r->nama_perusahaan }}">{{ $r->nama_perusahaan }}</td>
                        <td class="px-3 py-3 text-gray-600 truncate">{{ $r->badan_usaha ?: '—' }}</td>
                        <x-cakupan-popover mode="server" :row="$r" padding="px-3 py-3" />
                        <td class="px-3 py-3">
                            <div class="flex flex-wrap gap-1">
                                @if($r->has_sewa)
                                    <span class="rounded-full bg-avian-green-light px-2 py-0.5 text-[11px] font-medium text-avian-green">Sewa Truk</span>
                                @endif
                                @if($r->has_kiriman)
                                    <span class="rounded-full bg-blue-50 px-2 py-0.5 text-[11px] font-medium text-blue-600">Kiriman Rutin</span>
                                @endif
                                @if(!$r->has_sewa && !$r->has_kiriman)
                                    <span class="rounded-full bg-gray-100 px-2 py-0.5 text-[11px] font-medium text-gray-500">Belum ada tarif</span>
                                @endif
                            </div>
                        </td>
                        {{-- Popover position:fixed --}}
                        <td class="px-3 py-3 text-gray-600"
                            x-data="{ open: false, pos: '', left: 0 }"
                            @mouseenter="const r = $el.getBoundingClientRect(); left = Math.max(8, Math.min(r.left, window.innerWidth - 336)); pos = (r.bottom + 300 > window.innerHeight) ? 'bottom:' + (window.innerHeight - r.top) + 'px' : 'top:' + r.bottom + 'px'; open = true"
                            @mouseleave="open = false"
                            @scroll.window.capture="open = false">
                            @if(count($r->kendaraan))
                                <span class="cursor-default border-b border-dotted border-gray-400">{{ count($r->kendaraan) }} unit</span>
                                <div x-show="open" x-cloak class="fixed z-50 py-1" :style="pos + ';left:' + left + 'px'">
                                    <div class="max-h-72 w-80 overflow-y-auto rounded-lg border border-gray-200 bg-white p-2 text-xs shadow-lg">
                                        @foreach($r->kendaraan as $k)
                                            <div class="flex items-start justify-between gap-2 rounded-md px-2 py-1.5 hover:bg-gray-50">
                                                <div class="min-w-0">
                                                    <p class="truncate font-medium text-gray-800">
                                                        {{ $k['jenis'] ?: 'Kendaraan' }}
                                                        &middot;
                                                        @if($k['plat'])
                                                            <span class="font-mono">{{ $k['plat'] }}</span>
                                                        @else
                                                            <span class="font-normal text-gray-400">tanpa plat</span>
                                                        @endif
                                                    </p>
                                                    <p class="truncate text-gray-500">
                                                        {{ $k['muatan'] }} &middot; Cab. {{ $k['cabang'] }}@if($k['skills']) &middot; {{ implode(', ', array_slice($k['skills'], 0, 3)) }}{{ count($k['skills']) > 3 ? '…' : '' }}@endif
                                                    </p>
                                                </div>
                                                @if($k['pengajuan_url'])
                                                    <a href="{{ $k['pengajuan_url'] }}"
                                                        class="shrink-0 rounded-md bg-avian-green px-2 py-1 text-[11px] font-medium text-white hover:bg-avian-green-dark">
                                                        Ajukan
                                                    </a>
                                                @endif
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @else
                                <span class="text-gray-400">—</span>
                            @endif
                        </td>
                        <td class="px-3 py-3 text-gray-600 whitespace-nowrap">
                            {{ $r->last_update ? \Carbon\Carbon::parse($r->last_update)->translatedFormat('d M Y') : '—' }}
                        </td>
                        <td class="px-3 py-3 text-right">
                            <a href="{{ route('perusahaan.show', $r->id_perusahaan) }}"
                                class="rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-600 hover:bg-gray-50">
                                Detail
                            </a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="py-12 text-center text-sm text-gray-400">
                            @if($search !== '')
                                Tidak ada hasil untuk &quot;{{ $search }}&quot;.
                            @else
                                Tidak ada perusahaan yang cocok.
                            @endif
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </x-table-shell>
            @endif

        @elseif($tab === 'sewa-truk')
            {{-- ==================== TAB SEWA TRUK: 1 baris = 1 vendor_skill ==================== --}}
            @php
                $stickyWidths = ['kode_area' => 80, 'cabang' => 260, 'nama' => 200];
                $left = ['kode_area' => 0];
                $left['cabang'] = $left['kode_area'] + $stickyWidths['kode_area'];
                $left['nama'] = $left['cabang'] + $stickyWidths['cabang'];
                $otherWidths = [150, 160, 220, 150, 130, 130, 90];
            @endphp
            <x-table-shell :width="array_sum($stickyWidths) + array_sum($otherWidths)">
                <colgroup>
                    @foreach($stickyWidths as $w)
                        <col style="width: {{ $w }}px">
                    @endforeach
                    @foreach($otherWidths as $w)
                        <col style="width: {{ $w }}px">
                    @endforeach
                </colgroup>
                <thead>
                    <tr class="border-b border-gray-300 bg-gray-50 text-xs font-medium uppercase tracking-wide text-gray-500">
                        <x-sortable-th col="kode_area" label="Area" :class="$stickyTh" :style="'left: ' . $left['kode_area'] . 'px'" :sortBy="$sortBy" :sortOrder="$sortOrder" sortParam="sort" orderParam="order" />
                        <x-sortable-th col="cabang" label="Cabang" :class="$stickyTh" :style="'left: ' . $left['cabang'] . 'px'" :sortBy="$sortBy" :sortOrder="$sortOrder" sortParam="sort" orderParam="order" />
                        <x-sortable-th col="nama" label="Nama Ekspedisi" :class="$stickyTh . ' border-r border-gray-200'" :style="'left: ' . $left['nama'] . 'px'" :sortBy="$sortBy" :sortOrder="$sortOrder" sortParam="sort" orderParam="order" />
                        <x-sortable-th col="badan_usaha" label="Badan Usaha" :class="$plainTh" :sortBy="$sortBy" :sortOrder="$sortOrder" sortParam="sort" orderParam="order" />
                        <th class="{{ $plainTh }} px-3 py-3 text-center truncate">Identitas Owner</th>
                        <x-sortable-th col="area_kirim" label="Area Kirim" :class="$plainTh" :sortBy="$sortBy" :sortOrder="$sortOrder" sortParam="sort" orderParam="order" />
                        <x-sortable-th col="harga" label="Harga Sewa" align="right" :class="$plainTh" :sortBy="$sortBy" :sortOrder="$sortOrder" sortParam="sort" orderParam="order" />
                        <x-sortable-th col="diupdate" label="Diupdate" :class="$plainTh" :sortBy="$sortBy" :sortOrder="$sortOrder" sortParam="sort" orderParam="order" />
                        <x-sortable-th col="dibuat" label="Dibuat" :class="$plainTh" :sortBy="$sortBy" :sortOrder="$sortOrder" sortParam="sort" orderParam="order" />
                        <th class="{{ $plainTh }} px-3 py-3 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @forelse($rows as $r)
                    <tr class="group hover:bg-gray-50 transition">
                        <td class="sticky z-10 bg-white group-hover:bg-gray-50 px-3 py-3 text-gray-600 truncate" style="left: {{ $left['kode_area'] }}px">{{ $r->kode_area ?? '—' }}</td>
                        <td class="sticky z-10 bg-white group-hover:bg-gray-50 px-3 py-3 text-gray-600 truncate" style="left: {{ $left['cabang'] }}px">{{ $r->cabang_code }} — {{ $r->nama_cabang ?? '—' }}</td>
                        <td class="sticky z-10 bg-white group-hover:bg-gray-50 px-3 py-3 font-medium text-gray-800 border-r border-gray-200 truncate" style="left: {{ $left['nama'] }}px" title="{{ $r->nama_perusahaan }}">{{ $r->nama_perusahaan }}</td>
                        <td class="px-3 py-3 text-gray-600 truncate">{{ $r->badan_usaha }}</td>
                        <td class="px-3 py-3 text-center">
                            @if(!empty($r->identitas_owner))
                            @php $ownerSrcs = collect($r->identitas_owner)->map(fn ($i) => \App\Helpers\FormatHelper::identitasOwnerSrc($i))->values(); @endphp
                            <button type="button" @click="openLightbox(@js($ownerSrcs), 0)" class="relative inline-block">
                                <img src="{{ $ownerSrcs[0] }}" alt="Identitas Owner" class="w-10 h-10 rounded object-cover border border-gray-200">
                                @if($ownerSrcs->count() > 1)
                                <span class="absolute -top-1 -right-1 bg-avian-green text-white text-[10px] leading-none rounded-full w-4 h-4 flex items-center justify-center">+{{ $ownerSrcs->count() - 1 }}</span>
                                @endif
                            </button>
                            @else
                            <span class="text-gray-400">—</span>
                            @endif
                        </td>
                        <td class="px-3 py-3 text-gray-600 truncate">{{ $r->area_kirim }}</td>
                        <td class="px-3 py-3 text-right font-medium text-gray-800">
                            {{ $r->harga_sewa !== null ? 'Rp ' . number_format($r->harga_sewa, 0, ',', '.') : '—' }}
                        </td>
                        <td class="px-3 py-3 text-gray-600">{{ $r->diupdate ? \Carbon\Carbon::parse($r->diupdate)->translatedFormat('d M Y') : '—' }}</td>
                        <td class="px-3 py-3 text-gray-600">{{ $r->created_at ? \Carbon\Carbon::parse($r->created_at)->translatedFormat('d M Y') : '—' }}</td>
                        <td class="px-3 py-3 text-right">
                            <a href="{{ route('perusahaan.show', $r->id_perusahaan) }}"
                                class="rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-600 hover:bg-gray-50">
                                Detail
                            </a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="10" class="py-12 text-center text-sm text-gray-400">
                            @if($search !== '')
                                Tidak ada hasil untuk &quot;{{ $search }}&quot;.
                            @else
                                Belum ada data Sewa Truk yang cocok.
                            @endif
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </x-table-shell>

        @else
            {{-- ==================== TAB KIRIMAN RUTIN: 1 baris = 1 vendor_skill ==================== --}}
            @php
                $stickyWidths = ['kode_area' => 80, 'cabang' => 260, 'nama' => 200];
                $left = ['kode_area' => 0];
                $left['cabang'] = $left['kode_area'] + $stickyWidths['kode_area'];
                $left['nama'] = $left['cabang'] + $stickyWidths['cabang'];
                // Lebar tetap (bukan ngikutin panjang nama lagi) — nama panjang di-truncate +
                // tooltip di header-nya sendiri (lihat sortable-th.blade.php: title + class
                // truncate). Sebelumnya lebar dihitung dari panjang nama barang, jadi makin
                // banyak jenis barang & makin panjang namanya, tabel makin lebar & makin
                // jauh harus scroll horizontal (Revisi: Jo, review mentor item 1).
                $barangWidths = $jenisBarangList->map(fn ($jb) => 90);
                $areaKirimWidth = 220;
                $tableWidth = array_sum($stickyWidths) + $areaKirimWidth + $barangWidths->sum() + 130 + 130 + 90;
            @endphp
            <x-table-shell :width="$tableWidth">
                <colgroup>
                    @foreach($stickyWidths as $w)
                        <col style="width: {{ $w }}px">
                    @endforeach
                    <col style="width: {{ $areaKirimWidth }}px">
                    @foreach($barangWidths as $w)
                    <col style="width: {{ $w }}px">
                    @endforeach
                    <col style="width: 130px">
                    <col style="width: 130px">
                    <col style="width: 90px">
                </colgroup>
                <thead>
                    <tr class="border-b border-gray-300 bg-gray-50 text-xs font-medium uppercase tracking-wide text-gray-500">
                        <x-sortable-th col="kode_area" label="Area" :class="$stickyTh" :style="'left: ' . $left['kode_area'] . 'px'" :sortBy="$sortBy" :sortOrder="$sortOrder" sortParam="sort" orderParam="order" />
                        <x-sortable-th col="cabang" label="Cabang" :class="$stickyTh" :style="'left: ' . $left['cabang'] . 'px'" :sortBy="$sortBy" :sortOrder="$sortOrder" sortParam="sort" orderParam="order" />
                        <x-sortable-th col="nama" label="Nama Ekspedisi" :class="$stickyTh . ' border-r border-gray-200'" :style="'left: ' . $left['nama'] . 'px'" :sortBy="$sortBy" :sortOrder="$sortOrder" sortParam="sort" orderParam="order" />
                        <x-sortable-th col="area_kirim" label="Area Kirim" :class="$plainTh" :sortBy="$sortBy" :sortOrder="$sortOrder" sortParam="sort" orderParam="order" />
                        @foreach($jenisBarangList as $jb)
                        <x-sortable-th :col="'barang_' . $jb->id_jenis_barang" :label="$jb->nama_barang" align="right" :class="$plainTh" :sortBy="$sortBy" :sortOrder="$sortOrder" sortParam="sort" orderParam="order" />
                        @endforeach
                        <x-sortable-th col="diupdate" label="Diupdate" :class="$plainTh" :sortBy="$sortBy" :sortOrder="$sortOrder" sortParam="sort" orderParam="order" />
                        <x-sortable-th col="dibuat" label="Dibuat" :class="$plainTh" :sortBy="$sortBy" :sortOrder="$sortOrder" sortParam="sort" orderParam="order" />
                        <th class="{{ $plainTh }} px-3 py-3 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @forelse($rows as $r)
                    <tr class="group hover:bg-gray-50 transition">
                        <td class="sticky z-10 bg-white group-hover:bg-gray-50 px-3 py-3 text-gray-600 truncate" style="left: {{ $left['kode_area'] }}px">{{ $r->kode_area ?? '—' }}</td>
                        <td class="sticky z-10 bg-white group-hover:bg-gray-50 px-3 py-3 text-gray-600 truncate" style="left: {{ $left['cabang'] }}px">{{ $r->cabang_code }} — {{ $r->nama_cabang ?? '—' }}</td>
                        <td class="sticky z-10 bg-white group-hover:bg-gray-50 px-3 py-3 font-medium text-gray-800 border-r border-gray-200 truncate" style="left: {{ $left['nama'] }}px" title="{{ $r->nama_perusahaan }}">{{ $r->nama_perusahaan }}</td>
                        <td class="px-3 py-3 text-gray-600 truncate" title="{{ $r->area_kirim }}">{{ $r->area_kirim }}</td>
                        @foreach($jenisBarangList as $jb)
                        <td class="px-3 py-3 text-right text-gray-600">
                            {{ isset($r->harga[$jb->id_jenis_barang]) ? number_format($r->harga[$jb->id_jenis_barang], 0, ',', '.') : '—' }}
                        </td>
                        @endforeach
                        <td class="px-3 py-3 text-gray-600">{{ $r->updated_at ? \Carbon\Carbon::parse($r->updated_at)->translatedFormat('d M Y') : '—' }}</td>
                        <td class="px-3 py-3 text-gray-600">{{ $r->created_at ? \Carbon\Carbon::parse($r->created_at)->translatedFormat('d M Y') : '—' }}</td>
                        <td class="px-3 py-3 text-right">
                            <a href="{{ route('perusahaan.show', $r->id_perusahaan) }}"
                                class="rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-600 hover:bg-gray-50">
                                Detail
                            </a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="{{ 7 + $jenisBarangList->count() }}" class="py-12 text-center text-sm text-gray-400">
                            @if($search !== '')
                                Tidak ada hasil untuk &quot;{{ $search }}&quot;.
                            @else
                                Belum ada data Kiriman Rutin yang cocok.
                            @endif
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </x-table-shell>
        @endif

        <x-pagination-links :paginator="$rows" />
    </div>
</div>
@endsection

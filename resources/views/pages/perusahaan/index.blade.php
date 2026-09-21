@extends('layouts.app')

@section('title', 'Perusahaan')

@section('content')
@php
    $tabs = ['semua' => 'Semua', 'sewa-truk' => 'Sewa Truk', 'kiriman-rutin' => 'Kiriman Rutin'];
    // Ganti tab: pertahankan search & facet, buang page + sort (kolom sort beda per tab).
    $tabQuery = collect(request()->query())->except(['tab', 'page', 'sort', 'order'])->all();
@endphp

<div class="flex flex-col gap-4 pb-2">
    <x-alert-success />

    <div class="rounded-xl bg-white shadow-sm p-6">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Perusahaan</h1>
                <p class="text-sm text-gray-500">Vendor ekspedisi beserta tarif dan kendaraannya.</p>
            </div>
            <div class="inline-flex rounded-lg border border-gray-200 bg-gray-50 p-0.5 text-sm">
                @foreach($tabs as $key => $label)
                    <a href="{{ route('perusahaan.index', array_merge($tabQuery, $key === 'semua' ? [] : ['tab' => $key])) }}"
                        class="rounded-md px-3.5 py-1.5 font-medium transition
                        {{ $tab === $key ? 'bg-white text-avian-green shadow-sm' : 'text-gray-500 hover:text-gray-700' }}">
                        {{ $label }}
                    </a>
                @endforeach
            </div>
        </div>

        <form method="GET" class="mb-4 flex flex-wrap items-center gap-3">
            <input type="hidden" name="tab" value="{{ $tab }}">
            @if($sortBy)
                <input type="hidden" name="sort" value="{{ $sortBy }}">
                <input type="hidden" name="order" value="{{ $sortOrder }}">
            @endif
            <input
                type="text"
                name="search"
                value="{{ $search }}"
                placeholder="{{ $tab === 'semua' ? 'Cari nama perusahaan, badan usaha, cabang, atau area...' : 'Cari nama ekspedisi, kode cabang, atau area kirim...' }}"
                class="min-w-0 flex-1 rounded-lg border border-gray-300 px-4 py-2 text-sm focus:border-avian-green focus:outline-none">
            <x-pill-filter-modal :facets="$facets" />
        </form>

        @if($tab === 'semua')
            {{-- ==================== TAB SEMUA: 1 baris = 1 perusahaan ==================== --}}
            <div class="rounded-xl border border-gray-200">
                <table class="w-full table-fixed text-sm">
                    <colgroup>
                        <col style="width: 25%;">
                        <col style="width: 10%;">
                        <col style="width: 17%;">
                        <col style="width: 18%;">
                        <col style="width: 10%;">
                        <col style="width: 12%;">
                        <col style="width: 8%;">
                    </colgroup>
                    <thead class="bg-gray-50 [&>tr>th:first-child]:rounded-tl-xl [&>tr>th:last-child]:rounded-tr-xl">
                        <tr class="border-b border-gray-300 text-xs font-medium uppercase tracking-wide text-gray-500">
                            <x-sortable-th col="nama" label="Perusahaan" :sortBy="$sortBy" :sortOrder="$sortOrder" sortParam="sort" orderParam="order" />
                            <x-sortable-th col="badan_usaha" label="Badan Usaha" :sortBy="$sortBy" :sortOrder="$sortOrder" sortParam="sort" orderParam="order" />
                            <th class="px-3 py-3 text-left whitespace-nowrap">Cakupan</th>
                            <th class="px-3 py-3 text-left whitespace-nowrap">Tarif</th>
                            <th class="px-3 py-3 text-left whitespace-nowrap">Kendaraan</th>
                            <x-sortable-th col="diperbarui" label="Diperbarui" :sortBy="$sortBy" :sortOrder="$sortOrder" sortParam="sort" orderParam="order" />
                            <th class="px-3 py-3 text-right whitespace-nowrap">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @forelse($rows as $r)
                        <tr class="hover:bg-gray-50 transition">
                            <td class="px-3 py-3 font-medium text-gray-800 truncate" title="{{ $r->nama_perusahaan }}">{{ $r->nama_perusahaan }}</td>
                            <td class="px-3 py-3 text-gray-600 truncate">{{ $r->badan_usaha ?: '—' }}</td>
                            <td class="px-3 py-3 text-gray-600 whitespace-nowrap">
                                @if($r->cabang_count || $r->area_count)
                                    {{ $r->cabang_count }} cabang &middot; {{ $r->area_count }} area
                                @else
                                    <span class="text-gray-400">—</span>
                                @endif
                            </td>
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
                            <td class="group relative px-3 py-3 text-gray-600">
                                @if(count($r->kendaraan))
                                    <span class="cursor-default border-b border-dotted border-gray-400">{{ count($r->kendaraan) }} unit</span>
                                    <div class="absolute left-0 top-full z-30 hidden pt-1 group-hover:block">
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
                            <td class="px-3 py-3 text-gray-500 whitespace-nowrap">
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
                            <td colspan="7" class="py-12 text-center text-sm text-gray-400">Tidak ada perusahaan yang cocok.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

        @elseif($tab === 'sewa-truk')
            {{-- ==================== TAB SEWA TRUK: 1 baris = 1 vendor_skill ==================== --}}
            @php
                $stickyWidths = ['kode_area' => 70, 'cabang' => 200, 'nama' => 180];
                $left = ['kode_area' => 0];
                $left['cabang'] = $left['kode_area'] + $stickyWidths['kode_area'];
                $left['nama'] = $left['cabang'] + $stickyWidths['cabang'];
                $stickyTh = 'sticky top-0 z-30 bg-gray-50';
                $plainTh = 'sticky top-0 z-20 bg-gray-50';
            @endphp
            <div class="overflow-auto max-h-[70vh] rounded-xl border border-gray-200">
                <table class="table-fixed border-separate border-spacing-0 text-sm">
                    <colgroup>
                        <col style="width: {{ $stickyWidths['kode_area'] }}px">
                        <col style="width: {{ $stickyWidths['cabang'] }}px">
                        <col style="width: {{ $stickyWidths['nama'] }}px">
                        <col style="width: 120px">
                        <col style="width: 90px">
                        <col style="width: 150px">
                        <col style="width: 140px">
                        <col style="width: 130px">
                        <col style="width: 130px">
                        <col style="width: 90px">
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
                                <a href="{{ \App\Helpers\FormatHelper::identitasOwnerSrc($r->identitas_owner[0]) }}" target="_blank" class="relative inline-block">
                                    <img src="{{ \App\Helpers\FormatHelper::identitasOwnerSrc($r->identitas_owner[0]) }}" alt="Identitas Owner" class="w-10 h-10 rounded object-cover border border-gray-200">
                                    @if(count($r->identitas_owner) > 1)
                                    <span class="absolute -top-1 -right-1 bg-avian-green text-white text-[10px] leading-none rounded-full w-4 h-4 flex items-center justify-center">+{{ count($r->identitas_owner) - 1 }}</span>
                                    @endif
                                </a>
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
                            <td colspan="10" class="py-12 text-center text-sm text-gray-400">Belum ada data Sewa Truk yang cocok.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

        @else
            {{-- ==================== TAB KIRIMAN RUTIN: 1 baris = 1 vendor_skill ==================== --}}
            @php
                $stickyWidths = ['kode_area' => 70, 'cabang' => 200, 'nama' => 180];
                $left = ['kode_area' => 0];
                $left['cabang'] = $left['kode_area'] + $stickyWidths['kode_area'];
                $left['nama'] = $left['cabang'] + $stickyWidths['cabang'];
                $barangWidth = 130;
                $stickyTh = 'sticky top-0 z-30 bg-gray-50';
                $plainTh = 'sticky top-0 z-20 bg-gray-50';
            @endphp
            <div class="overflow-auto max-h-[70vh] rounded-xl border border-gray-200">
                <table class="table-fixed border-separate border-spacing-0 text-sm">
                    <colgroup>
                        <col style="width: {{ $stickyWidths['kode_area'] }}px">
                        <col style="width: {{ $stickyWidths['cabang'] }}px">
                        <col style="width: {{ $stickyWidths['nama'] }}px">
                        <col style="width: 160px">
                        @foreach($jenisBarangList as $jb)
                        <col style="width: {{ $barangWidth }}px">
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
                            <td colspan="{{ 7 + $jenisBarangList->count() }}" class="py-12 text-center text-sm text-gray-400">Belum ada data Kiriman Rutin yang cocok.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @endif

        <x-pagination-links :paginator="$rows" />
    </div>
</div>
@endsection

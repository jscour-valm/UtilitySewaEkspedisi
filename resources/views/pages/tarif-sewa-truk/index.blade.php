@extends('layouts.app')

@section('title', 'Kelola Tarif — Sewa Truk')

@section('content')
@php $isDci = auth()->user()?->userUtility?->role === 'DCI'; @endphp
<div class="flex flex-col gap-4 pb-2">
    @if(session('success'))
    <div class="rounded-xl bg-avian-green-light border border-avian-green/30 text-avian-green px-4 py-3 text-sm">
        {{ session('success') }}
    </div>
    @endif

    <div class="rounded-xl bg-white shadow-sm p-6">
        <h1 class="text-2xl font-bold text-gray-900 mb-4">Master Tabel Sewa Truk</h1>
        <form method="GET" class="mb-4">
            <input
                type="text"
                name="search"
                value="{{ $search }}"
                placeholder="Cari nama ekspedisi, kode cabang, atau area kirim..."
                class="w-full rounded-lg border border-gray-300 px-4 py-2 text-sm focus:border-avian-green focus:outline-none">
        </form>

        @php
            $stickyWidths = ['kode_area' => 30, 'cabang' => 200, 'nama' => 180];
            $left = ['kode_area' => 0];
            $left['cabang'] = $left['kode_area'] + $stickyWidths['kode_area'];
            $left['nama'] = $left['cabang'] + $stickyWidths['cabang'];
        @endphp

        <div class="overflow-auto max-h-[70vh] rounded-xl border border-gray-200">
            <table class="table-fixed border-separate border-spacing-0 text-sm">
                <colgroup>
                    <col style="width: {{ $stickyWidths['kode_area'] }}px">
                    <col style="width: {{ $stickyWidths['cabang'] }}px">
                    <col style="width: {{ $stickyWidths['nama'] }}px">
                    <col style="width: 110px">
                    <col style="width: 90px">
                    <col style="width: 150px">
                    <col style="width: 130px">
                    <col style="width: 140px">
                    <col style="width: 140px">
                    <col style="width: 90px">
                </colgroup>
                <thead>
                    <tr class="border-b border-gray-300 bg-gray-50 text-xs font-medium uppercase tracking-wide text-gray-500">
                        <th class="sticky top-0 z-30 bg-gray-50 px-3 py-3 text-left truncate" style="left: {{ $left['kode_area'] }}px">Area</th>
                        <th class="sticky top-0 z-30 bg-gray-50 px-3 py-3 text-left truncate" style="left: {{ $left['cabang'] }}px">Cabang</th>
                        <th class="sticky top-0 z-30 bg-gray-50 px-3 py-3 text-left border-r border-gray-200 truncate" style="left: {{ $left['nama'] }}px">Nama Ekspedisi</th>
                        <th class="sticky top-0 z-20 bg-gray-50 px-3 py-3 text-left truncate">Badan Usaha</th>
                        <th class="sticky top-0 z-20 bg-gray-50 px-3 py-3 text-center truncate">Identitas Owner</th>
                        <th class="sticky top-0 z-20 bg-gray-50 px-3 py-3 text-left truncate">Area Kirim</th>
                        <th class="sticky top-0 z-20 bg-gray-50 px-3 py-3 text-right truncate">Harga Sewa</th>
                        <th class="sticky top-0 z-20 bg-gray-50 px-3 py-3 text-left truncate">Diupdate</th>
                        <th class="sticky top-0 z-20 bg-gray-50 px-3 py-3 text-left truncate">Dibuat</th>
                        <th class="sticky top-0 z-20 bg-gray-50 px-3 py-3 text-right">Aksi</th>
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
                            <a href="{{ route('kelola-tarif.sewa-truk.edit', $r->id_vendor_skill) }}"
                                class="rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-600 hover:bg-gray-50">
                                {{ $isDci ? 'Edit' : 'Detail' }}
                            </a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="10" class="py-12 text-center text-sm text-gray-400">Belum ada data Sewa Truk.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <x-pagination-links :paginator="$rows" />
    </div>
</div>
@endsection

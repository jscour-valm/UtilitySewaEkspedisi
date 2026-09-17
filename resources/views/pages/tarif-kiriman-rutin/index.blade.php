@extends('layouts.app')

@section('title', 'Kelola Tarif — Kiriman Rutin')

@section('content')
@php $isDci = auth()->user()?->userUtility?->role === 'DCI'; @endphp
<div class="flex flex-col gap-4 pb-2">
    @if(session('success'))
    <div class="rounded-xl bg-avian-green-light border border-avian-green/30 text-avian-green px-4 py-3 text-sm">
        {{ session('success') }}
    </div>
    @endif

    <div class="rounded-xl bg-white shadow-sm p-6">
        <h1 class="text-2xl font-bold text-gray-900 mb-4">Master Tabel Kiriman Rutin</h1>
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
            $barangWidth = 110;
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
                    <col style="width: 140px">
                    <col style="width: 140px">
                    <col style="width: 90px">
                </colgroup>
                <thead>
                    <tr class="border-b border-gray-300 bg-gray-50 text-xs font-medium uppercase tracking-wide text-gray-500">
                        <th class="sticky top-0 z-30 bg-gray-50 px-3 py-3 text-left truncate" style="left: {{ $left['kode_area'] }}px">Area</th>
                        <th class="sticky top-0 z-30 bg-gray-50 px-3 py-3 text-left truncate" style="left: {{ $left['cabang'] }}px">Cabang</th>
                        <th class="sticky top-0 z-30 bg-gray-50 px-3 py-3 text-left border-r border-gray-200 truncate" style="left: {{ $left['nama'] }}px">Nama Ekspedisi</th>
                        <th class="sticky top-0 z-20 bg-gray-50 px-3 py-3 text-left truncate">Area Kirim</th>
                        @foreach($jenisBarangList as $jb)
                        <th class="sticky top-0 z-20 bg-gray-50 px-3 py-3 text-right truncate" title="{{ $jb->nama_barang }}">{{ $jb->nama_barang }}</th>
                        @endforeach
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
                        <td class="px-3 py-3 text-gray-600 truncate" title="{{ $r->area_kirim }}">{{ $r->area_kirim }}</td>
                        @foreach($jenisBarangList as $jb)
                        <td class="px-3 py-3 text-right text-gray-600">
                            {{ isset($r->harga[$jb->id_jenis_barang]) ? number_format($r->harga[$jb->id_jenis_barang], 0, ',', '.') : '—' }}
                        </td>
                        @endforeach
                        <td class="px-3 py-3 text-gray-600">{{ $r->updated_at ? \Carbon\Carbon::parse($r->updated_at)->translatedFormat('d M Y') : '—' }}</td>
                        <td class="px-3 py-3 text-gray-600">{{ $r->created_at ? \Carbon\Carbon::parse($r->created_at)->translatedFormat('d M Y') : '—' }}</td>
                        <td class="px-3 py-3 text-right">
                            <a href="{{ route('kelola-tarif.kiriman-rutin.edit', $r->id_vendor_skill) }}"
                                class="rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-600 hover:bg-gray-50">
                                {{ $isDci ? 'Edit' : 'Detail' }}
                            </a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="{{ 7 + $jenisBarangList->count() }}" class="py-12 text-center text-sm text-gray-400">Belum ada data Kiriman Rutin.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <x-pagination-links :paginator="$rows" />
    </div>
</div>
@endsection

@extends('layouts.app')

@section('title', 'WM per Cabang')

@section('content')
<div class="flex flex-col gap-4 pb-2">
    <x-ui.alert-success />
    <x-ui.alert-error />

    <div class="rounded-xl bg-white shadow-sm p-6">
        <h1 class="text-2xl font-bold text-gray-900 mb-1">WM per Cabang</h1>
        <p class="text-sm text-gray-500 mb-4">
            Kelola manual WM yang bisa approve tiap cabang — buat kasus khusus (cabang baru atau cabang yang belum punya WM). Data default sudah ke-sync otomatis dari sistem cabang, halaman ini cuma buat pengecualian.
        </p>

        {{-- Bulk-assign per Area — 1 WM langsung ke SEMUA cabang dalam 1 area --}}
        <div class="rounded-lg border border-avian-green/30 bg-avian-green-light px-4 py-4 mb-4">
            <h2 class="text-sm font-semibold text-avian-green-dark mb-3">Assign per Area</h2>
            <form method="POST" action="{{ route('setting-approver.store-by-area') }}" class="flex flex-col sm:flex-row items-end gap-3"
                onsubmit="event.preventDefault(); confirmDialog('Assign WM ini ke SEMUA cabang di area yang dipilih?').then(ok => ok && this.submit()); return false">
                @csrf
                <div class="flex-1 w-full">
                    <label class="mb-1 block text-xs font-medium text-gray-600">Area</label>
                    <select name="area" required class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-avian-green focus:outline-none">
                        <option value="">-- Pilih Area --</option>
                        @foreach($areaList as $a)
                        <option value="{{ $a }}">{{ $a }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex-1 w-full">
                    <label class="mb-1 block text-xs font-medium text-gray-600">WM</label>
                    <select name="user_id" required class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-avian-green focus:outline-none">
                        <option value="">-- Pilih WM --</option>
                        @foreach($wmUsers as $wmOption)
                        <option value="{{ $wmOption->id }}">{{ $wmOption->name }} ({{ $wmOption->username }})</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="w-full sm:w-auto rounded-lg bg-avian-green px-4 py-2 text-sm font-medium text-white hover:bg-avian-green-dark transition">
                    Assign ke Semua Cabang di Area Ini
                </button>
            </form>
        </div>

        <form method="GET" class="mb-4 flex flex-col sm:flex-row gap-3">
            <input
                type="text"
                name="search"
                value="{{ $search }}"
                placeholder="Cari kode atau nama cabang..."
                class="flex-1 rounded-lg border border-gray-300 px-4 py-2 text-sm focus:border-avian-green focus:outline-none">
            <select name="area" onchange="this.form.submit()" class="rounded-lg border border-gray-300 px-4 py-2 text-sm focus:border-avian-green focus:outline-none">
                <option value="">-- Semua Area --</option>
                @foreach($areaList as $a)
                <option value="{{ $a }}" @selected($area === $a)>Area {{ $a }}</option>
                @endforeach
            </select>
        </form>

        <div class="overflow-hidden">
            <table class="w-full text-sm">
                <thead>
                    <tr class="kepala-tabel text-xs font-medium uppercase tracking-wide text-gray-500">
                        <th class="px-4 py-3 text-left">Area</th>
                        <th class="px-4 py-3 text-left">Kode Cabang</th>
                        <th class="px-4 py-3 text-left">Nama Cabang</th>
                        <th class="px-4 py-3 text-left">WM dari Sistem</th>
                        <th class="px-4 py-3 text-left">WM Tambahan (manual)</th>
                        <th class="px-4 py-3 text-right">Aksi</th>
                    </tr>
                </thead>
                @forelse($cabangs as $c)
                @php $manualTampil = $c->wmManual->reject(fn ($wm) => $wm->rangkap); @endphp
                <tbody x-data="{ open: false }" class="divide-y divide-gray-100 border-b border-gray-100">
                    <tr class="hover:bg-gray-50 transition">
                        <td class="px-4 py-3 font-medium text-gray-600">{{ $c->Area ?? '—' }}</td>
                        <td class="px-4 py-3 text-gray-800">{{ $c->Code }}</td>
                        <td class="px-4 py-3 text-gray-600">{{ $c->Name }}</td>
                        {{-- WM dari mapping area sistem IT; tidak bisa diubah dari sini --}}
                        <td class="px-4 py-3">
                            @if($c->wmSistem->isEmpty())
                                @if($manualTampil->isEmpty())
                                <span class="inline-flex rounded-full bg-red-50 px-2.5 py-0.5 text-xs font-medium text-red-600">Belum ada approver</span>
                                @else
                                <span class="text-gray-300">—</span>
                                @endif
                            @else
                            <div class="flex flex-wrap gap-1.5">
                                @foreach($c->wmSistem as $wm)
                                <span class="inline-flex rounded-full bg-blue-50 px-2.5 py-0.5 text-xs font-medium text-blue-700">{{ $wm->name }}</span>
                                @endforeach
                            </div>
                            @endif
                        </td>
                        {{-- WM yang ditambahkan DCI lewat halaman ini (cabang baru / cabang tanpa WM) --}}
                        <td class="px-4 py-3">
                            @if($manualTampil->isEmpty())
                            <span class="inline-flex rounded-full border border-dashed border-gray-300 px-2.5 py-0.5 text-xs text-gray-400">Belum diisi</span>
                            @else
                            <div class="flex flex-wrap gap-1.5">
                                @foreach($manualTampil as $wm)
                                <span class="inline-flex rounded-full bg-avian-green-light px-2.5 py-0.5 text-xs font-medium text-avian-green">{{ $wm->name }}</span>
                                @endforeach
                            </div>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right">
                            <button type="button" @click="open = !open" class="text-xs font-medium text-avian-green hover:text-avian-green-dark transition">
                                <span x-text="open ? 'Tutup' : 'Kelola'"></span>
                            </button>
                        </td>
                    </tr>
                    <tr x-show="open" x-cloak>
                        <td colspan="6" class="px-4 py-4 bg-gray-50">
                            <div class="space-y-3">
                                @if($c->wmSistem->isNotEmpty() || $c->wmManual->isNotEmpty())
                                <div class="space-y-1.5">
                                    @foreach($c->wmSistem as $wm)
                                    <div class="flex items-center justify-between rounded-lg border border-gray-200 bg-white px-3 py-2">
                                        <span class="text-sm text-gray-700">{{ $wm->name }} <span class="text-gray-400">({{ $wm->username ?? '' }})</span></span>
                                        <span class="text-xs text-gray-400" title="Mapping otomatis dari sistem IT (view rolling area) — bukan baris manual, jadi tidak bisa dihapus dari sini. Kalau WM ini seharusnya tidak memegang cabang ini lagi, perbaikannya di sisi IT (rolling area).">Otomatis dari sistem</span>
                                    </div>
                                    @endforeach
                                    @foreach($c->wmManual as $wm)
                                    <div class="flex items-center justify-between gap-3 rounded-lg border border-gray-200 bg-white px-3 py-2">
                                        <span class="text-sm text-gray-700">
                                            {{ $wm->name }} <span class="text-gray-400">({{ $wm->username ?? '' }})</span>
                                            <span class="ml-1 text-xs text-gray-400">{{ $wm->rangkap ? '· tambahan manual, sudah otomatis dari sistem' : '· tambahan manual' }}</span>
                                        </span>
                                        <form method="POST" action="{{ route('setting-approver.destroy', $wm->id_approval_rule) }}"
                                            onsubmit="event.preventDefault(); confirmDialog('Hapus {{ $wm->name }} dari cabang {{ $c->Code }}?', {danger: true}).then(ok => ok && this.submit()); return false">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-xs font-medium text-red-600 hover:text-red-700 transition">Hapus</button>
                                        </form>
                                    </div>
                                    @endforeach
                                </div>
                                @endif

                                <form method="POST" action="{{ route('setting-approver.store') }}" class="flex items-end gap-2">
                                    @csrf
                                    <input type="hidden" name="cabang_code" value="{{ $c->Code }}">
                                    <div class="flex-1">
                                        <label class="mb-1 block text-xs font-medium text-gray-600">Tambah WM manual</label>
                                        <select name="user_id" required class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-avian-green focus:outline-none">
                                            <option value="">-- Pilih WM --</option>
                                            @foreach($wmUsers as $wmOption)
                                            <option value="{{ $wmOption->id }}">{{ $wmOption->name }} ({{ $wmOption->username }})</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <button type="submit" class="rounded-lg bg-avian-green px-4 py-2 text-sm font-medium text-white hover:bg-avian-green-dark transition">
                                        + Tambah
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                </tbody>
                @empty
                <tbody>
                    <tr>
                        <td colspan="6" class="py-12 text-center text-sm text-gray-400">Belum ada data cabang.</td>
                    </tr>
                </tbody>
                @endforelse
            </table>
        </div>
        <x-ui.pagination-links :paginator="$cabangs" />
    </div>
</div>
@endsection

@extends('layouts.app')

@section('title', $isKg ? 'History Pengajuan' : 'Daftar Pengajuan')

@section('content')
@php
    use App\Models\PengajuanSewa;

    $badge = PengajuanSewa::BADGE_STATUS;
    $kolom = 9 + ($multiCabang ? 1 : 0);
    $tgl = fn ($nilai) => $nilai ? \Carbon\Carbon::parse($nilai)->translatedFormat('d M Y') : '—';
@endphp

<div class="flex flex-col gap-4 pb-2">
    <div class="rounded-xl bg-white p-6 shadow-sm">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">{{ $isKg ? 'History Pengajuan' : 'Daftar Pengajuan' }}</h1>
                <p class="text-sm text-gray-500">
                    Semua pengajuan sewa {{ $multiCabang ? 'di cabang yang Anda pegang' : 'di cabang Anda' }}, tanpa batas periode.
                </p>
            </div>
            @if($isKg)
                <a href="{{ route('pengajuan.kg') }}"
                    class="rounded-lg bg-avian-green px-4 py-2 text-sm font-medium text-white hover:bg-avian-green-dark">+ Pengajuan Baru</a>
            @endif
        </div>

        {{-- Cari + tombol Filter (rentang tanggal diajukan & pill di dalam modal); semua lewat query string --}}
        <form method="GET" class="mb-4 flex flex-wrap items-center gap-3">
            <div class="relative min-w-[16rem] flex-1" x-data="{ q: @js($search) }">
                <input type="text" name="search" x-model="q" placeholder="Cari nama perusahaan, pengaju, atau kode pengajuan…"
                    class="w-full rounded-lg border border-gray-300 px-4 py-2 pr-9 text-sm focus:border-avian-green focus:outline-none">
                <button type="button" x-show="q.length > 0" x-cloak @click="q = ''; $nextTick(() => $el.closest('form').submit())"
                    class="absolute right-2.5 top-1/2 -translate-y-1/2 rounded-full p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600"
                    aria-label="Bersihkan pencarian">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <x-ui.pill-filter-modal :facets="$facets" :kunci-tambahan="['tgl_dari', 'tgl_sampai']">
                <x-slot:atas>
                    <div x-data="{ dari: @js($tglDari), sampai: @js($tglSampai) }">
                        <h3 class="mb-2 text-sm font-semibold text-gray-700">Tanggal Diajukan</h3>
                        <input type="hidden" name="tgl_dari" :value="dari" :disabled="!dari">
                        <input type="hidden" name="tgl_sampai" :value="sampai" :disabled="!sampai">
                        <div class="grid grid-cols-2 gap-3">
                            <x-ui.input-tanggal model="dari" placeholder="Dari" />
                            <x-ui.input-tanggal model="sampai" placeholder="Sampai" rata="kanan" />
                        </div>
                    </div>
                </x-slot:atas>
            </x-ui.pill-filter-modal>
            @if($adaFilter)
                <a href="{{ route('pengajuan.daftar') }}" class="text-sm text-gray-500 hover:underline">Reset</a>
            @endif
        </form>

        <p class="mb-3 text-xs text-gray-400">{{ number_format($rows->total(), 0, ',', '.') }} pengajuan{{ $adaFilter ? ' cocok dengan filter' : '' }}.</p>

        <div class="overflow-x-auto">
            <table class="w-full min-w-[70rem] text-sm">
                <thead>
                    <tr class="kepala-tabel text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                        <th class="px-4 py-3">Kode</th>
                        <th class="px-4 py-3">Diajukan</th>
                        <th class="px-4 py-3">Pengiriman</th>
                        @if($multiCabang)
                            <th class="px-4 py-3">Cabang</th>
                        @endif
                        <th class="px-4 py-3">Pengaju</th>
                        <th class="px-4 py-3">Nama Perusahaan</th>
                        <th class="px-4 py-3">Jenis</th>
                        <th class="px-4 py-3 text-right">Harga Sewa</th>
                        <th class="px-4 py-3 text-right">Rasio Sewa</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3 text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($rows as $p)
                        @php
                            $kunci = strtolower($p->status_pengajuan ?? '');
                            ['kelas' => $kelas, 'label' => $label] = $badge[$kunci] ?? ['kelas' => 'bg-gray-100 text-gray-600', 'label' => $p->status_pengajuan];
                            if ($kunci === 'pending' && $p->giliran) {
                                $label = $p->giliran === 'WM' ? 'Menunggu Validasi WM' : 'Menunggu Approval ' . $p->giliran;
                            }
                        @endphp
                        <tr class="hover:bg-gray-50">
                            <td class="whitespace-nowrap px-4 py-3 font-medium text-gray-800">{{ \App\Models\PengajuanSewa::kodeDari($p->kode_pengajuan ?? null, $p->id) }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-gray-600">{{ $tgl($p->submitted_at ?? $p->created_at) }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-gray-600">{{ $tgl($p->tanggal_pengiriman) }}</td>
                            @if($multiCabang)
                                <td class="whitespace-nowrap px-4 py-3 text-gray-600">{{ $p->id_cabang }}</td>
                            @endif
                            <td class="px-4 py-3 text-gray-600">{{ $p->pengaju ?? '—' }}</td>
                            <td class="px-4 py-3 text-gray-800">{{ trim(($p->badan_usaha && $p->badan_usaha !== '-' ? $p->badan_usaha . ' ' : '') . ($p->nama_perusahaan ?? '')) ?: '—' }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-gray-600">
                                {{ PengajuanSewa::JENIS[$p->jenis_pengajuan] ?? $p->jenis_pengajuan }}
                                <span class="text-xs text-gray-400">· {{ $p->tujuan_penyewaan }}</span>
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-right tabular-nums text-gray-700">Rp {{ number_format((float) $p->harga_sewa, 0, ',', '.') }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-right tabular-nums text-gray-700">{{ $p->pakai_rasio ? number_format((float) $p->rasio_sewa, 2, ',', '.') . '%' : '—' }}</td>
                            <td class="px-4 py-3">
                                <span class="whitespace-nowrap rounded-full px-2 py-0.5 text-xs font-medium {{ $kelas }}">{{ $label }}</span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <a href="{{ route('pengajuan.buka', $p->id) }}"
                                    class="inline-block whitespace-nowrap rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-600 hover:bg-gray-50">Detail</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $kolom + 1 }}" class="py-12 text-center text-sm text-gray-400">
                                {{ $adaFilter ? 'Tidak ada pengajuan yang cocok.' : 'Belum ada pengajuan sewa.' }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <x-ui.pagination-links :paginator="$rows" />
    </div>
</div>
@endsection

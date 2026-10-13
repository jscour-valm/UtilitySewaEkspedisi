{{--
    Tabel proses vendor baru atau usulan harga master di tab dashboard, dengan kotak cari dan
    (selain KG) kartu statistik yang bisa diklik untuk menyaring baris. Penyaringan di browser
    lewat Alpine `filterPersetujuan` (baris `tr[data-baris]`).
    Dipakai di: <x-dashboard.tab>.
    Props: jenis (vendor | harga), rows (koleksi model proses), peran (role user yang melihat).
--}}
@props(['jenis', 'rows', 'peran'])

@php
    use App\Helpers\FormatHelper;

    $isVendor = $jenis === 'vendor';
    // WM/WH: "pending" = yang menunggu keputusan peran ini; role lain = semua yang masih berjalan.
    $approver = in_array($peran, ['WM', 'WH'], true);
    $pending = fn ($r) => $approver ? $r->giliranPersetujuan() === $peran : $r->sedangBerjalan();
    $kartu = $peran === 'KG' ? [] : [
        ['semua', 'Total ' . ($isVendor ? 'Vendor Baru' : 'Usulan Harga'), $rows->count(), 'Semua status', 'gray'],
        ['pending', 'Pending', $rows->filter($pending)->count(), match ($peran) { 'WM' => 'Menunggu validasi Anda', 'WH' => 'Menunggu approval Anda', default => 'Masih berjalan' }, 'yellow'],
        ['approved', 'Disetujui', $rows->filter(fn ($r) => $r->sudahDisetujui())->count(), $isVendor ? 'Vendor disetujui' : 'Usulan disetujui', 'green'],
        ['rejected', 'Ditolak', $rows->filter(fn ($r) => $r->statusPersetujuan() === 'rejected')->count(), $isVendor ? 'Vendor ditolak' : 'Usulan ditolak', 'red'],
    ];
@endphp

<div x-data="filterPersetujuan" class="space-y-4">
@if($kartu)
    <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
        @foreach($kartu as [$kunci, $label, $jumlah, $keterangan, $warna])
            <div class="grid cursor-pointer rounded-xl ring-2 transition hover:ring-avian-green/40 focus-visible:ring-avian-green"
                :class="status === @js($kunci) ? 'ring-avian-green/60' : 'ring-transparent'"
                role="button" tabindex="0" @click="pilihStatus(@js($kunci))" @keydown.enter.prevent="pilihStatus(@js($kunci))">
                <x-ui.stat-card :label="$label" :count="$jumlah" :subtitle="$keterangan" :color="$warna" />
            </div>
        @endforeach
    </div>
@endif

<div class="rounded-xl bg-white shadow-sm">
    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 px-6 py-4">
        <div class="min-w-0">
            <h2 class="text-sm font-semibold text-gray-700">{{ $isVendor ? 'Pengajuan Vendor Baru' : 'Usulan Perubahan Harga Master' }}</h2>
        </div>
        {{-- KG: filter periode + jalan pintas mengajukan (role lain: filter ada di baris tab) --}}
        @if($peran === 'KG')
            <div class="flex flex-wrap items-center gap-3">
                <x-dashboard.filter-tanggal />
                @if($isVendor)
                    <x-perusahaan.modal-vendor-baru :is-kg="true" kelas-tombol="px-3 py-1.5 text-xs" />
                @else
                    {{-- Usulan harga diajukan per tarif, jadi vendornya dipilih dulu di halaman Perusahaan --}}
                    <a href="{{ route('perusahaan.index') }}" title="Pilih vendor, lalu klik Usulkan Harga pada tarifnya"
                        class="whitespace-nowrap rounded-lg bg-avian-green px-3 py-1.5 text-xs font-medium text-white hover:bg-avian-green-dark">
                        + Usulkan Harga
                    </a>
                @endif
            </div>
        @endif
    </div>

    <div class="overflow-x-auto p-6">
        @if($rows->isEmpty())
            <p class="py-10 text-center text-sm text-gray-400">
                Belum ada {{ $isVendor ? 'pengajuan vendor baru' : 'usulan perubahan harga' }} di periode ini.
            </p>
        @else
            <input type="text" x-model="q" @input="terapkan()"
                placeholder="{{ $isVendor ? 'Cari nama vendor, pengaju, atau cabang...' : 'Cari nama vendor, area, pengaju, atau cabang...' }}"
                class="mb-4 w-full rounded-lg border border-gray-300 px-4 py-2 text-sm focus:border-avian-green focus:outline-none">
            <table class="w-full min-w-[48rem] text-sm">
                <thead>
                    <tr class="border-b border-gray-200 text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                        <th class="py-2.5 pr-3">{{ $isVendor ? 'Vendor' : 'Vendor & Tarif' }}</th>
                        <th class="py-2.5 pr-3">Cabang</th>
                        @unless($isVendor)
                            <th class="py-2.5 pr-8">Master → Usulan</th>
                        @endunless
                        <th class="py-2.5 pr-3">Diajukan</th>
                        <th class="py-2.5 pr-3">Status</th>
                        <th class="py-2.5 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($rows as $r)
                        @php
                            $giliranSaya = $r->giliranPersetujuan() === $peran;
                            $url = $isVendor ? route('persetujuan.vendor.show', $r->id_perusahaan) : route('persetujuan.harga.show', $r->id_usulan_harga);
                            $cari = $isVendor
                                ? [$r->badan_usaha, $r->nama_perusahaan]
                                : [$r->nama_vendor, $r->nama_area, $r->jenisBarang?->nama_barang];
                            $cari = strtolower(implode(' ', array_filter([...$cari, $r->id_cabang_pengaju, $r->submittedBy?->name])));
                        @endphp
                        <tr class="hover:bg-gray-50" data-baris data-cari="{{ $cari }}"
                            data-status="{{ $r->statusPersetujuan() }}" data-pending="{{ $pending($r) ? '1' : '0' }}">
                            <td class="py-3 pr-3">
                                @if($isVendor)
                                    <p class="font-medium text-gray-800">{{ trim(($r->badan_usaha && $r->badan_usaha !== '-' ? $r->badan_usaha . ' ' : '') . $r->nama_perusahaan) }}</p>
                                    <p class="text-xs text-gray-400">{{ $r->no_telepon ?: '—' }}</p>
                                @else
                                    <p class="font-medium text-gray-800">{{ $r->nama_vendor }}</p>
                                    <p class="text-xs text-gray-500">
                                        {{ $r->jenis === 'sewa_truk' ? 'Sewa Truk' : 'Kiriman Rutin' }} · {{ $r->nama_area }}
                                        @if($r->jenisBarang) · {{ $r->jenisBarang->nama_barang }} @endif
                                    </p>
                                @endif
                            </td>
                            <td class="py-3 pr-3 text-gray-600">{{ $r->id_cabang_pengaju }}</td>
                            @unless($isVendor)
                                @php
                                    $lama = $r->harga_lama !== null ? (float) $r->harga_lama : null;
                                    $naik = $lama !== null && (float) $r->harga_usulan > $lama;
                                @endphp
                                <td class="whitespace-nowrap py-3 pr-8 tabular-nums">
                                    <span class="text-gray-500">{{ $lama !== null ? FormatHelper::rupiah($lama) : 'baru' }}</span>
                                    <span class="text-gray-300">→</span>
                                    <span class="font-semibold {{ $lama === null ? 'text-gray-800' : ($naik ? 'text-red-600' : 'text-avian-green') }}">{{ FormatHelper::rupiah($r->harga_usulan) }}</span>
                                </td>
                            @endunless
                            <td class="py-3 pr-3 text-gray-600">
                                <p>{{ $r->submittedBy?->name ?? '-' }}</p>
                                <p class="text-xs text-gray-400">{{ $r->submitted_at?->translatedFormat('d M Y') }}</p>
                            </td>
                            <td class="py-3 pr-3">
                                <span class="whitespace-nowrap rounded-full px-2 py-0.5 text-xs font-medium {{ $r->warnaStatusPersetujuan() }}">{{ $r->labelStatusPersetujuan() }}</span>
                            </td>
                            <td class="py-3 text-right">
                                @if($giliranSaya)
                                    <a href="{{ $url }}" class="inline-block whitespace-nowrap rounded-lg bg-avian-green px-3 py-1.5 text-xs font-medium text-white hover:bg-avian-green-dark">
                                        {{ $peran === 'WM' ? 'Validasi' : 'Review' }}
                                    </a>
                                @else
                                    <a href="{{ $url }}" class="inline-block whitespace-nowrap rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-600 hover:bg-gray-50">Detail</a>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <p x-show="tampil === 0" x-cloak class="py-8 text-center text-sm text-gray-400">Tidak ada yang cocok dengan pencarian / filter.</p>
        @endif
    </div>
</div>
</div>

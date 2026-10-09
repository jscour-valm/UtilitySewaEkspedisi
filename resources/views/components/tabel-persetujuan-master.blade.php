{{-- Tabel proses vendor baru (jenis=vendor) atau usulan harga master (jenis=harga) di tab dashboard. --}}
@props(['jenis', 'rows', 'peran'])

@php
    use App\Helpers\FormatHelper;

    $isVendor = $jenis === 'vendor';
    $warnaStatus = fn ($status) => match ($status) {
        'approved' => 'bg-avian-green-light text-avian-green',
        'rejected' => 'bg-red-50 text-red-600',
        default => 'bg-amber-50 text-amber-700',
    };
@endphp

<div class="rounded-xl bg-white shadow-sm">
    <div class="border-b border-gray-100 px-6 py-4">
        <h2 class="text-sm font-semibold text-gray-700">{{ $isVendor ? 'Pengajuan Vendor Baru' : 'Usulan Perubahan Harga Master' }}</h2>
        <p class="mt-0.5 text-xs text-gray-400">
            Proses yang masih berjalan selalu tampil; yang sudah diputuskan mengikuti periode filter.
            @if($peran === 'KG')
                Ajukan {{ $isVendor ? 'vendor baru' : 'perubahan harga' }} dari halaman
                <a href="{{ route('perusahaan.index') }}" class="text-avian-green hover:underline">Perusahaan</a>.
            @endif
        </p>
    </div>

    <div class="overflow-x-auto p-6">
        @if($rows->isEmpty())
            <p class="py-10 text-center text-sm text-gray-400">
                Belum ada {{ $isVendor ? 'pengajuan vendor baru' : 'usulan perubahan harga' }} di periode ini.
            </p>
        @else
            <table class="w-full min-w-[48rem] text-sm">
                <thead>
                    <tr class="border-b border-gray-200 text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                        <th class="py-2.5 pr-3">{{ $isVendor ? 'Vendor' : 'Vendor & Tarif' }}</th>
                        <th class="py-2.5 pr-3">Cabang</th>
                        @unless($isVendor)
                            <th class="py-2.5 pr-3 text-right">Master → Usulan</th>
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
                        @endphp
                        <tr class="{{ $giliranSaya ? 'bg-amber-50/40' : '' }}">
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
                                <td class="whitespace-nowrap py-3 pr-3 text-right tabular-nums">
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
                                <span class="whitespace-nowrap rounded-full px-2 py-0.5 text-xs font-medium {{ $warnaStatus($r->statusPersetujuan()) }}">{{ $r->labelStatusPersetujuan() }}</span>
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
        @endif
    </div>
</div>

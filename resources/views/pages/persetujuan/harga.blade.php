@extends('layouts.app')

@section('title', 'Usulan Harga Master')

@section('content')
@php
    use App\Helpers\FormatHelper;

    $status = $usulan->status;
    $warnaStatus = match ($status) {
        'approved' => 'bg-avian-green-light text-avian-green',
        'rejected' => 'bg-red-50 text-red-600',
        default => 'bg-amber-50 text-amber-700',
    };
    $namaVendor = $vendor ? trim(($vendor->badan_usaha && $vendor->badan_usaha !== '-' ? $vendor->badan_usaha . ' ' : '') . $vendor->nama_perusahaan) : '-';
    $isRutin = $usulan->jenis === 'pengiriman_rutin';
    $lama = $usulan->harga_lama !== null ? (float) $usulan->harga_lama : null;
    $usul = (float) $usulan->harga_usulan;
    $selisih = $lama !== null ? $usul - $lama : null;
    $persen = ($selisih !== null && $lama > 0) ? abs($selisih) / $lama * 100 : null;
    $labelAksi = $peran === 'WM' ? 'Validasi' : 'Setujui';
    $sectionLabel = 'text-[11px] font-semibold tracking-[0.09em] text-gray-500';
    $langkah = [
        ['label' => 'Diajukan KG', 'selesai' => true],
        ['label' => 'Validasi WM', 'selesai' => in_array($status, ['menunggu_approval', 'approved'], true)],
        ['label' => 'Approval WH', 'selesai' => $status === 'approved'],
    ];
@endphp

<div class="space-y-4 pb-6">
    <div class="rounded-xl border border-gray-100 bg-white p-5 shadow-sm">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="min-w-0">
                <p class="{{ $sectionLabel }}">USULAN HARGA MASTER · {{ $isRutin ? 'KIRIMAN RUTIN' : 'SEWA TRUK' }}</p>
                <h1 class="mt-1 text-[22px] font-bold tracking-tight text-gray-900">{{ $namaVendor }}</h1>
                <p class="mt-1 text-[13px] text-gray-500">
                    {{ $namaArea ?? '-' }} &middot; Cab. {{ $usulan->cabang_code }}{{ $namaCabang ? ' (' . $namaCabang . ')' : '' }}
                    @if($isRutin) &middot; {{ $usulan->jenisBarang?->nama_barang ?? '-' }} @endif
                </p>
                <p class="mt-0.5 text-[13px] text-gray-500">
                    Diajukan oleh <strong class="text-gray-700">{{ $usulan->submittedBy?->name ?? '-' }}</strong>
                    &middot; {{ $usulan->submitted_at?->translatedFormat('d M Y H:i') }}
                    &middot; {{ $usulan->sumber === 'pengajuan' ? 'bersama pengajuan sewa' : 'dari halaman Perusahaan' }}
                </p>
            </div>
            <span class="rounded-full px-3 py-1 text-sm font-semibold {{ $warnaStatus }}">{{ $usulan->labelStatusPersetujuan() }}</span>
        </div>

        <div class="mt-4 flex flex-wrap items-center gap-2 text-xs">
            @foreach($langkah as $i => $l)
                @if($i > 0)<span class="text-gray-300">→</span>@endif
                <span class="rounded-full px-2.5 py-1 font-medium {{ $l['selesai'] ? 'bg-avian-green-light text-avian-green' : 'bg-gray-100 text-gray-500' }}">
                    {{ $l['selesai'] ? '✓ ' : '' }}{{ $l['label'] }}
                </span>
            @endforeach
        </div>

        {{-- Harga master sekarang vs usulan --}}
        <div class="mt-5 grid grid-cols-1 gap-3 border-t border-gray-100 pt-4 sm:grid-cols-3">
            <div>
                <p class="{{ $sectionLabel }}">HARGA MASTER {{ $status === 'approved' ? 'SEBELUMNYA' : 'SEKARANG' }}</p>
                <p class="mt-1 text-xl font-bold text-gray-700">{{ $lama !== null ? FormatHelper::rupiah($lama) : 'Belum ada' }}</p>
            </div>
            <div>
                <p class="{{ $sectionLabel }}">HARGA USULAN{{ $isRutin ? ' / UNIT' : '' }}</p>
                <p class="mt-1 text-xl font-bold text-avian-green">{{ FormatHelper::rupiah($usul) }}</p>
            </div>
            <div>
                <p class="{{ $sectionLabel }}">SELISIH</p>
                @if($selisih === null)
                    <p class="mt-1 text-sm text-gray-500">Harga baru (belum ada master)</p>
                @else
                    <p class="mt-1 text-xl font-bold {{ $selisih > 0 ? 'text-red-600' : 'text-avian-green' }}">
                        {{ $selisih > 0 ? '▲ +' : '▼ −' }}{{ FormatHelper::rupiah(abs($selisih)) }}
                        @if($persen !== null)<span class="text-sm font-semibold">({{ number_format($persen, 1, ',', '.') }}%)</span>@endif
                    </p>
                @endif
            </div>
        </div>

        @if($usulan->catatan)
            <div class="mt-4 rounded-lg bg-gray-50 px-4 py-3 text-sm text-gray-700">
                <p class="text-xs font-semibold text-gray-500">Catatan KG</p>
                <p class="mt-0.5 whitespace-pre-wrap">{{ $usulan->catatan }}</p>
            </div>
        @endif

        @if($status === 'rejected' && $usulan->alasan_penolakan)
            <div class="mt-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                <p class="font-semibold">Alasan penolakan</p>
                <p class="mt-0.5">{{ $usulan->alasan_penolakan }}</p>
            </div>
        @endif

        @if($bisaPutuskan)
            <div class="mt-5 flex flex-wrap items-center justify-between gap-3 border-t border-gray-100 pt-4">
                <p class="text-sm text-gray-600">
                    {{ $peran === 'WM' ? 'Menunggu validasi Anda.' : 'Menunggu approval Anda. Kalau disetujui, harga master langsung diganti.' }}
                </p>
                <div class="flex gap-2.5">
                    <button type="button"
                        onclick="putuskanPersetujuan(@js(route('persetujuan.harga.putuskan', $usulan->id_usulan_harga)), false, { objek: 'usulan harga ini' })"
                        class="rounded-lg border border-red-200 bg-white px-5 py-2.5 text-sm font-semibold text-red-600 transition hover:bg-red-50">
                        Tolak
                    </button>
                    <button type="button"
                        onclick="putuskanPersetujuan(@js(route('persetujuan.harga.putuskan', $usulan->id_usulan_harga)), true, { label: @js($labelAksi), objek: 'usulan harga ini' })"
                        class="rounded-lg bg-avian-green px-5 py-2.5 text-sm font-semibold text-white transition hover:brightness-90">
                        {{ $labelAksi }}
                    </button>
                </div>
            </div>
        @endif
    </div>

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-[1fr_1.3fr]">
        <div class="rounded-xl border border-gray-100 bg-white p-5 shadow-sm">
            <p class="{{ $sectionLabel }} mb-3">RIWAYAT</p>
            <ol class="space-y-3 text-sm">
                <li class="flex gap-3">
                    <span class="mt-1.5 h-2 w-2 shrink-0 rounded-full bg-gray-400"></span>
                    <div>
                        <p class="font-medium text-gray-800">Diajukan oleh KG</p>
                        <p class="text-xs text-gray-500">{{ $usulan->submittedBy?->name ?? '-' }} &middot; {{ $usulan->submitted_at?->translatedFormat('d M Y H:i') }}</p>
                    </div>
                </li>
                @foreach($usulan->approvalLogs as $log)
                    @php $setuju = $log->status === 'approved'; @endphp
                    <li class="flex gap-3">
                        <span class="mt-1.5 h-2 w-2 shrink-0 rounded-full {{ $setuju ? 'bg-avian-green' : 'bg-red-500' }}"></span>
                        <div>
                            <p class="font-medium text-gray-800">{{ $setuju ? ($log->role_approver === 'WM' ? 'Divalidasi' : 'Disetujui') : 'Ditolak' }} {{ $log->role_approver }}</p>
                            <p class="text-xs text-gray-500">{{ $log->approver?->name ?? '-' }} &middot; {{ $log->decided_at?->translatedFormat('d M Y H:i') }}</p>
                            @if($log->alasan_penolakan)
                                <p class="mt-0.5 text-xs italic text-red-600">"{{ $log->alasan_penolakan }}"</p>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ol>
        </div>

        <div class="rounded-xl border border-gray-100 bg-white p-5 shadow-sm">
            <p class="{{ $sectionLabel }} mb-1">PENGAJUAN SEWA YANG IKUT MENGUSULKAN HARGA INI</p>
            <p class="mb-3 text-xs text-gray-400">Pengajuan tetap diproses dengan harga yang diajukan, apa pun keputusan usulan ini.</p>
            @if($pengajuan->isEmpty())
                <p class="text-sm text-gray-400">Tidak ada — usulan ini diajukan langsung dari halaman Perusahaan.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[28rem] text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                                <th class="py-2 pr-3">No.</th>
                                <th class="py-2 pr-3">Tgl Kirim</th>
                                <th class="py-2 pr-3">Diajukan oleh</th>
                                <th class="py-2"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach($pengajuan as $p)
                                <tr>
                                    <td class="py-2 pr-3 font-medium text-gray-800">#{{ $p->id_pengajuan_sewa }}</td>
                                    <td class="py-2 pr-3 text-gray-600">{{ $p->tanggal_pengiriman?->format('d M Y') }}</td>
                                    <td class="py-2 pr-3 text-gray-600">{{ $p->submittedBy?->name ?? '-' }}</td>
                                    <td class="py-2 text-right">
                                        <a href="{{ route('pengajuan.buka', $p->id_pengajuan_sewa) }}" class="text-xs font-medium text-avian-green hover:underline">Buka →</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
            @if($vendor?->flag)
                <a href="{{ route('perusahaan.show', $usulan->id_perusahaan) }}" class="mt-4 inline-block text-sm font-medium text-avian-green hover:underline">Lihat tarif di halaman Perusahaan →</a>
            @endif
        </div>
    </div>
</div>
@endsection

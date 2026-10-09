{{--
    Tab dashboard: Pengajuan Sewa (isi slot) | Vendor Baru | Perubahan Harga, dengan badge jumlah
    yang menunggu. Hanya untuk KG, WM, WH, DCI; role lain cuma melihat isi slot.
    $filter = tampilkan filter periode di baris tab (berlaku untuk semua tab).
--}}
@props(['filter' => false])

@php
    $user = auth()->user();
    $peran = $user?->userUtility?->role;
    $pakaiTab = in_array($peran, \App\Services\DaftarPersetujuanMaster::PERAN, true);
    $daftar = $pakaiTab
        ? app(\App\Services\DaftarPersetujuanMaster::class)->untuk($user, \App\Helpers\RentangTanggalDashboard::dariRequest())
        : null;
    $tabs = [
        'sewa' => ['label' => 'Pengajuan Sewa', 'badge' => 0],
        'vendor' => ['label' => 'Vendor Baru', 'badge' => $daftar['badge']['vendor'] ?? 0],
        'harga' => ['label' => 'Perubahan Harga', 'badge' => $daftar['badge']['harga'] ?? 0],
    ];
@endphp

@if(! $pakaiTab)
    {{ $slot }}
@else
<div x-data="tabDashboard(@js($peran))" class="space-y-4">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="inline-flex max-w-full overflow-x-auto rounded-lg border border-gray-200 bg-white p-0.5 text-sm shadow-sm">
            @foreach($tabs as $kunci => $t)
                <button type="button" @click="pilih(@js($kunci))"
                    class="inline-flex items-center gap-2 whitespace-nowrap rounded-md px-3.5 py-1.5 font-medium transition"
                    :class="tab === @js($kunci) ? 'bg-avian-green-light text-avian-green' : 'text-gray-500 hover:text-gray-700'">
                    {{ $t['label'] }}
                    @if($t['badge'] > 0)
                        <span class="rounded-full bg-avian-green-dark px-1.5 py-0.5 text-[11px] font-semibold leading-none text-white">
                            {{ $t['badge'] }}
                        </span>
                    @endif
                </button>
            @endforeach
        </div>
        @if($filter)
            <x-filter-tanggal-dashboard />
        @endif
    </div>

    <div x-show="tab === 'sewa'" class="space-y-4">
        {{ $slot }}
    </div>

    <div x-show="tab === 'vendor'" x-cloak>
        <x-tabel-persetujuan-master jenis="vendor" :rows="$daftar['vendor']" :peran="$peran" />
    </div>

    <div x-show="tab === 'harga'" x-cloak>
        <x-tabel-persetujuan-master jenis="harga" :rows="$daftar['harga']" :peran="$peran" />
    </div>
</div>
@endif

{{--
    Tab dashboard: Pengajuan Sewa (isi slot) | Vendor Baru | Perubahan Harga dengan badge jumlah
    yang menunggu, dan filter periode di kanan baris tab. Tab hanya untuk KG, WM, WH, DCI;
    role lain cuma melihat isi slot.
    Dipakai di: dashboard KG, WM, WH, DCI.
    Tab menempel langsung ke isi di bawahnya dan tab aktif berwarna sama dengan isi itu.
    Props: filter (true = tampilkan filter periode di baris kepala, berlaku untuk semua tab);
           panel (true = isi tab terdiri dari beberapa kartu, jadi dibungkus satu wadah abu-abu
           tempat tab menempel; false = isi tab satu kartu putih, tab menempel ke kartu itu, mis. KG).
--}}
@props(['filter' => false, 'panel' => false])

@php
    // Warna tab aktif = warna isi tempat ia menempel.
    $warnaIsi = $panel ? 'bg-gray-200/70' : 'bg-white shadow-sm';
    $kelasIsi = $panel ? 'space-y-4 rounded-xl bg-gray-200/70 p-4' : '';
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
<div x-data="tabDashboard(@js($peran))">
    {{-- Tab bergaya tab browser: tab aktif menyatu dengan isi di bawahnya --}}
    <div class="flex flex-wrap items-end gap-x-5 gap-y-2">
        {{-- Tab pertama rata dengan tepi kiri isi; sudut kiri-atas isi tab pertama dibuat siku supaya keduanya menyatu --}}
        <div class="flex max-w-full gap-1 overflow-x-auto text-sm" role="tablist">
            @foreach($tabs as $kunci => $t)
                <button type="button" role="tab" @click="pilih(@js($kunci))" :aria-selected="tab === @js($kunci)"
                    class="inline-flex items-center gap-2 whitespace-nowrap rounded-t-lg border border-b-0 px-4 py-2 font-medium transition"
                    :class="tab === @js($kunci)
                        ? @js('border-transparent border-t-2 border-t-avian-green text-avian-green ' . $warnaIsi)
                        : 'border-transparent text-gray-500 hover:bg-gray-200/70 hover:text-gray-700'">
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
            <div class="mb-2 ml-auto"><x-dashboard.filter-tanggal /></div>
        @endif
    </div>

    <div x-show="tab === 'sewa'" class="{{ $kelasIsi ? $kelasIsi . ' rounded-tl-none' : 'space-y-4' }}">
        {{ $slot }}
    </div>

    <div x-show="tab === 'vendor'" x-cloak class="{{ $kelasIsi }}">
        <x-dashboard.tabel-persetujuan-master jenis="vendor" :rows="$daftar['vendor']" :peran="$peran" />
    </div>

    <div x-show="tab === 'harga'" x-cloak class="{{ $kelasIsi }}">
        <x-dashboard.tabel-persetujuan-master jenis="harga" :rows="$daftar['harga']" :peran="$peran" />
    </div>
</div>
@endif

{{--
    Penanda vendor baru yang belum disetujui; tidak tampil untuk vendor approved.
    Dipakai di: daftar Perusahaan.
    Props: status (status persetujuan vendor), giliran (peran yang sedang ditunggu; kosong =
           WM untuk menunggu_validasi, WH untuk menunggu_approval), id (id vendor, untuk tautan
           ke halaman proses).
--}}
@props(['status' => null, 'giliran' => null, 'id' => null])

@if($status && $status !== 'approved')
    @php
        $label = match ($status) {
            'menunggu_validasi' => 'Vendor baru · menunggu ' . ($giliran ?: 'WM'),
            'menunggu_approval' => 'Vendor baru · menunggu ' . ($giliran ?: 'WH'),
            default => 'Vendor ditolak',
        };
        $warna = $status === 'rejected' ? 'bg-red-50 text-red-600' : 'bg-amber-50 text-amber-700';
        $bolehBuka = $id && in_array(auth()->user()?->userUtility?->role, \App\Services\DaftarPersetujuanMaster::PERAN, true);
    @endphp
    @if($bolehBuka)
        <a href="{{ route('persetujuan.vendor.show', $id) }}" @click.stop
            {{ $attributes->merge(['class' => "inline-block whitespace-nowrap rounded-full px-2 py-0.5 text-[11px] font-medium hover:underline $warna"]) }}>{{ $label }}</a>
    @else
        <span {{ $attributes->merge(['class' => "inline-block whitespace-nowrap rounded-full px-2 py-0.5 text-[11px] font-medium $warna"]) }}>{{ $label }}</span>
    @endif
@endif

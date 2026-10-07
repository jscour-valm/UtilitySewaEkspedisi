@extends('layouts.app')

@section('title', 'Dashboard KA')

@section('content')
<div class="space-y-4">
    <x-filter-tanggal-dashboard />

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <x-stat-card
            label="Pengajuan Disetujui"
            :count="$countApproved"
            subtitle="Cabang {{ implode(', ', $cabangIds) ?: '-' }}"
            color="green"
        />
    </div>

    <div class="rounded-xl bg-white shadow-sm">
        <div class="border-b border-gray-100 px-6 py-4">
            <h2 class="text-sm font-semibold text-gray-700">Pengajuan Sewa Disetujui &middot; Hanya Lihat</h2>
        </div>
        <div class="p-6">
            <x-tabel-pengajuan mode="ka" :limit="0" />
        </div>
    </div>
</div>
@endsection

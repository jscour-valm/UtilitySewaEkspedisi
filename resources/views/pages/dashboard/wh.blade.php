@extends('layouts.app')

@section('title', 'Dashboard WH')

@section('content')
<div class="space-y-4">
    {{-- Summary Cards (Stat Cards) - Clickable filters --}}
    <x-dashboard-stat-filters
        :countTotal="$countTotal"
        :countPending="$countPending"
        :countApproved="$countApproved"
        :countRejected="$countRejected"
        :searchAttrs="['data-cabang', 'data-perusahaan']"
        pendingOverrideAttr="data-wh-ready"
        totalSubtitle="Semua kategori over_threshold"
        pendingSubtitle="Menunggu keputusan Anda"
    />

    {{-- Pengajuan Table Section --}}
    <div class="rounded-xl bg-white shadow-sm">

        <div class="border-b border-gray-100 px-6 py-4">
            <h2 class="text-sm font-semibold text-gray-700">
                Pengajuan Sewa &middot; Tier 2 (Over Threshold)
            </h2>
        </div>

        <div class="p-6">
            {{-- mode=wh: lintas cabang, tanpa kolom KaGud (search sudah di dalam komponen) --}}
            <x-tabel-pengajuan mode="wh" :limit="0" />
        </div>
    </div>

</div>

@endsection

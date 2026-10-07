@extends('layouts.app')

@section('title', 'Dashboard WM')

@section('content')
<div class="space-y-4">
    <x-filter-tanggal-dashboard />

    {{-- Summary Cards (Stat Cards) - Clickable filters --}}
    <x-dashboard-stat-filters
        :countTotal="$countTotal"
        :countPending="$countPending"
        :countApproved="$countApproved"
        :countRejected="$countRejected"
        :searchAttrs="['data-kagud', 'data-perusahaan']"
        pendingOverrideAttr="data-giliran-saya"
        pendingSubtitle="Menunggu validasi Anda"
        approvedLabel="Accepted"
        rejectedLabel="Rejected"
    />

    {{-- Pengajuan Table Section --}}
    <div class="rounded-xl bg-white shadow-sm">

        <div class="border-b border-gray-100 px-6 py-4">
            <h2 class="text-sm font-semibold text-gray-700">
                Pengajuan Sewa
            </h2>
        </div>

        <div class="p-6">
            {{-- Gunakan reusable component tabel-pengajuan dengan mode=wm (search sudah di dalam komponen) --}}
            <x-tabel-pengajuan mode="wm" :limit="0" />
        </div>
    </div>

</div>

@endsection

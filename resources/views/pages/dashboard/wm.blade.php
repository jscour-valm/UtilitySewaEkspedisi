@extends('layouts.app')

@section('title', 'Dashboard WM')
@section('judul_halaman', 'Dashboard')

@section('content')
<div class="space-y-4">
<x-dashboard.tab :filter="true">

    {{-- Summary Cards (Stat Cards) - Clickable filters --}}
    <x-dashboard.stat-filters
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
            {{-- Tabel pengajuan mode WM (kotak cari ada di dalam komponen) --}}
            <x-dashboard.tabel-pengajuan mode="wm" :limit="0" />
        </div>
    </div>

</x-dashboard.tab>
</div>

@endsection

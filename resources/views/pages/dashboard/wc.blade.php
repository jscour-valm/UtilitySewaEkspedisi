@extends('layouts.app')

@section('title', 'Dashboard WC')

@section('content')
<div class="space-y-4">
    <x-filter-tanggal-dashboard />

    <x-dashboard-stat-filters
        :countTotal="$countTotal"
        :countPending="$countPending"
        :countApproved="$countApproved"
        :countRejected="$countRejected"
        :searchAttrs="['data-cabang', 'data-perusahaan']"
        pendingOverrideAttr="data-giliran-saya"
        totalSubtitle="Pengajuan PAC (perlu approval WC)"
        pendingSubtitle="Menunggu approval Anda"
    />

    <div class="rounded-xl bg-white shadow-sm">

        <div class="border-b border-gray-100 px-6 py-4">
            <h2 class="text-sm font-semibold text-gray-700">
                Pengajuan Sewa &middot; Approval WC (PAC)
            </h2>
        </div>

        <div class="p-6">
            <x-tabel-pengajuan mode="wc" :limit="0" />
        </div>
    </div>

</div>

@endsection

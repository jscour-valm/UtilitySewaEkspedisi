@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')

<div class="space-y-2">
    <div class="mb-4"><x-filter-tanggal-dashboard /></div>

    {{-- Card: Pengajuan Sewa --}}
    <div class="rounded-xl">
        <div class="rounded-xl bg-white shadow  mb-4">
            <div class="flex items-center justify-between border-b border-gray-100 px-6 py-4">
                <h2 class="text-sm font-semibold text-gray-700">Pengajuan Sewa</h2>
                <a href="{{ route('pengajuan.kg') }}"
                class="rounded-lg bg-avian-green px-3 py-1.5 text-xs font-medium text-white hover:bg-avian-green-dark">
                    + Pengajuan Baru
                </a>
            </div>
            <div class="p-6">
                <x-tabel-pengajuan mode="dashboard" />
            </div>
        </div>
    </div>

    {{-- Card: Daftar Kendaraan --}}
    <div class="rounded-xl bg-white shadow">
        <div class="flex items-center justify-between border-b border-gray-100 px-6 py-4">
            <h2 class="text-sm font-semibold text-gray-700">Daftar Ekspedisi</h2>
            <a href="{{ route('perusahaan.index') }}"
                class="text-xs text-avian-green hover:underline">
                Lihat semua →
            </a>
        </div>
        <div class="p-6">
            <x-perusahaan-dashboard-preview />
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // ===== PENGAJUAN TABLE SEARCH =====
    const pengajuanSearchInput = document.getElementById('pengajuanSearch');
    const pengajuanRows = document.querySelectorAll('.pengajuan-row');

    if (pengajuanSearchInput) {
        function applyPengajuanFilters() {
            const searchTerm = pengajuanSearchInput.value.toLowerCase();

            pengajuanRows.forEach(row => {
                const perusahaan = row.getAttribute('data-perusahaan') || '';

                // Check apakah match dengan search term
                const matchesSearch = perusahaan.includes(searchTerm);

                // Show row kalau match
                row.style.display = matchesSearch ? '' : 'none';
            });
        }

        // Event: Search input
        pengajuanSearchInput.addEventListener('keyup', function() {
            applyPengajuanFilters();
        });
    }
});
</script>

@endsection
@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')

<div class="space-y-2">
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
            <h2 class="text-sm font-semibold text-gray-700">Daftar Kendaraan</h2>
            <a href="{{ route('kendaraan.idx') }}"
               class="text-xs text-avian-green hover:underline">
                Lihat semua →
            </a>
        </div>
        <div class="p-6">
            <x-tabel-kendaraan mode="dashboard" />
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

    // ===== KENDARAAN TABLE SEARCH =====
    const kendaraanSearchInput = document.getElementById('searchKendaraan');
    const kendaraanRows = document.querySelectorAll('.kendaraan-row');

    console.log('Kendaraan Search Debug:', { kendaraanSearchInput, rowCount: kendaraanRows.length });
    console.log('First row data attributes:', kendaraanRows[0]?.getAttribute('data-nama'));

    if (kendaraanSearchInput && kendaraanRows.length > 0) {
        function applyKendaraanFilters() {
            const searchTerm = kendaraanSearchInput.value.toLowerCase();
            console.log('Kendaraan search term:', searchTerm);

            kendaraanRows.forEach(row => {
                const nama = row.getAttribute('data-nama') || '';
                const badan = row.getAttribute('data-badan') || '';
                const skill = row.getAttribute('data-skill') || '';
                const kendaraan = row.getAttribute('data-kendaraan') || '';

                // Check apakah match dengan search term (ANY field)
                const matchesSearch = nama.includes(searchTerm) ||
                                      badan.includes(searchTerm) ||
                                      skill.includes(searchTerm) ||
                                      kendaraan.includes(searchTerm);

                row.style.display = matchesSearch ? '' : 'none';
            });
        }

        // Event: Search input
        kendaraanSearchInput.addEventListener('keyup', function() {
            applyKendaraanFilters();
        });
    } else {
        console.warn('Kendaraan search not initialized: input or rows missing');
    }
});
</script>

@endsection
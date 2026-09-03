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

    {{-- Card: Daftar Armada --}}
    <div class="rounded-xl bg-white shadow">
        <div class="flex items-center justify-between border-b border-gray-100 px-6 py-4">
            <h2 class="text-sm font-semibold text-gray-700">Daftar Armada</h2>
            <a href="{{ route('armada.idx') }}"
               class="text-xs text-avian-green hover:underline">
                Lihat semua →
            </a>
        </div>
        <div class="p-6">
            <x-tabel-armada mode="dashboard" />
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

    // ===== ARMADA TABLE SEARCH =====
    const armadaSearchInput = document.getElementById('searchArmada');
    const armadaRows = document.querySelectorAll('.armada-row');

    console.log('Armada Search Debug:', { armadaSearchInput, rowCount: armadaRows.length });
    console.log('First row data attributes:', armadaRows[0]?.getAttribute('data-nama'));

    if (armadaSearchInput && armadaRows.length > 0) {
        function applyArmadaFilters() {
            const searchTerm = armadaSearchInput.value.toLowerCase();
            console.log('Armada search term:', searchTerm);

            armadaRows.forEach(row => {
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
        armadaSearchInput.addEventListener('keyup', function() {
            applyArmadaFilters();
        });
    } else {
        console.warn('Armada search not initialized: input or rows missing');
    }
});
</script>

@endsection
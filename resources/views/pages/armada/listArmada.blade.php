@extends('layouts.app')

@section('title', 'Daftar Armada')

@section('content')

<div class="rounded-xl bg-white shadow-sm p-6">
    <x-tabel-armada mode="detail" :limit="null" />
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('searchArmada');
    const rows = document.querySelectorAll('.armada-row');

    if (!searchInput) return; // Guard

    function applyFilters() {
        const searchTerm = searchInput.value.toLowerCase();

        rows.forEach(row => {
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
    searchInput.addEventListener('keyup', function() {
        applyFilters();
    });
});
</script>

@endsection
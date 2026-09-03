@extends('layouts.app')

@section('title', 'Dashboard WM')

@section('content')
<div class="space-y-4">
    {{-- Summary Cards (Stat Cards) - Clickable filters --}}
    <div class="grid grid-cols-4 gap-4">
        <div class="stat-filter-card cursor-pointer rounded-xl bg-white shadow-sm transition ring-2 ring-transparent
            hover:ring-avian-green/40 focus-visible:ring-avian-green"
            data-status-filter="all" role="button" tabindex="0">
            <x-stat-card
                label="Total Pengajuan"
                :count="$countTotal"
                subtitle="Semua status"
                color="gray"
            />
        </div>

        <div class="stat-filter-card cursor-pointer rounded-xl bg-white shadow-sm transition ring-2 ring-transparent
            hover:ring-avian-green/40 focus-visible:ring-avian-green"
            data-status-filter="pending" role="button" tabindex="0">
            <x-stat-card
                label="Pending"
                :count="$countPending"
                subtitle="Menunggu review"
                color="yellow"
            />
        </div>

        <div class="stat-filter-card cursor-pointer rounded-xl bg-white shadow-sm transition ring-2 ring-transparent
            hover:ring-avian-green/40 focus-visible:ring-avian-green"
            data-status-filter="approved" role="button" tabindex="0">
            <x-stat-card
                label="Accepted"
                :count="$countApproved"
                subtitle="Pengajuan disetujui"
                color="green"
            />
        </div>

        <div class="stat-filter-card cursor-pointer rounded-xl bg-white shadow-sm transition ring-2 ring-transparent
            hover:ring-avian-green/40 focus-visible:ring-avian-green"
            data-status-filter="rejected" role="button" tabindex="0">
            <x-stat-card
                label="Rejected"
                :count="$countRejected"
                subtitle="Pengajuan ditolak"
                color="red"
            />
        </div>
    </div>

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

<script>
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('pengajuanSearch');
    const rows = document.querySelectorAll('.pengajuan-row');
    const statFilterCards = document.querySelectorAll('.stat-filter-card');

    let activeStatusFilter = 'all'; // State untuk status filter aktif

    // Function untuk apply both filters (search term + status filter)
    function applyFilters() {
        const searchTerm = searchInput.value.toLowerCase();

        rows.forEach(row => {
            const kagud = row.getAttribute('data-kagud') || '';
            const perusahaan = row.getAttribute('data-perusahaan') || '';
            const status = row.getAttribute('data-status') || '';

            // Check apakah match dengan search term
            const matchesSearch = kagud.includes(searchTerm) || perusahaan.includes(searchTerm);

            // Check apakah match dengan status filter
            const matchesStatus = (activeStatusFilter === 'all') || (status === activeStatusFilter);

            // Show row hanya kalau kedua filter cocok (AND logic)
            row.style.display = (matchesSearch && matchesStatus) ? '' : 'none';
        });
    }

    // Event: Search input
    searchInput.addEventListener('keyup', function() {
        applyFilters();
    });

    // Event: Stat card filter (click & keyboard)
    statFilterCards.forEach(card => {
        card.addEventListener('click', function(e) {
            const filterValue = this.getAttribute('data-status-filter');

            // Toggle: kalau klik card yang sama, reset ke 'all'
            if (activeStatusFilter === filterValue) {
                activeStatusFilter = 'all';
            } else {
                activeStatusFilter = filterValue;
            }

            // Update visual highlight pada card
            updateCardHighlight();

            // Apply filter
            applyFilters();
        });

        // Keyboard accessibility: Enter/Space
        card.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                this.click();
            }
        });
    });

    // Function untuk update highlight visual di stat cards
    function updateCardHighlight() {
        statFilterCards.forEach(card => {
            const filterValue = card.getAttribute('data-status-filter');
            if (filterValue === activeStatusFilter && activeStatusFilter !== 'all') {
                // Active: tampil ring highlight
                card.classList.add('ring-avian-green', 'ring-2');
                card.classList.remove('ring-transparent');
            } else {
                // Inactive: transparent ring
                card.classList.remove('ring-avian-green');
                card.classList.add('ring-transparent', 'ring-2');
            }
        });
    }

    // Initial state: semua card transparent ring
    updateCardHighlight();
});
</script>

@endsection

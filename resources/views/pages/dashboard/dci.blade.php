@extends('layouts.app')

@section('title', 'Dashboard DCI')

@section('content')
<div class="space-y-4">
    <x-filter-tanggal-dashboard />

    {{-- Summary Cards — read-only, klik buat filter tabel di bawah --}}
    <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-5">
        <div class="stat-filter-card grid cursor-pointer rounded-xl transition ring-2 ring-transparent
            hover:ring-avian-green/40 focus-visible:ring-avian-green"
            data-status-filter="all" role="button" tabindex="0">
            <x-stat-card
                label="Total Pengajuan"
                :count="$countTotal"
                subtitle="Semua cabang, semua status"
                color="gray"
            />
        </div>

        <div class="stat-filter-card grid cursor-pointer rounded-xl transition ring-2 ring-transparent
            hover:ring-avian-green/40 focus-visible:ring-avian-green"
            data-status-filter="pending" role="button" tabindex="0">
            <x-stat-card
                label="Pending"
                :count="$countPending"
                subtitle="Menunggu approval"
                color="yellow"
            />
        </div>

        <div class="stat-filter-card grid cursor-pointer rounded-xl transition ring-2 ring-transparent
            hover:ring-avian-green/40 focus-visible:ring-avian-green"
            data-status-filter="approved" role="button" tabindex="0">
            <x-stat-card
                label="Approved"
                :count="$countApproved"
                subtitle="Pengajuan disetujui"
                color="green"
            />
        </div>

        <div class="stat-filter-card grid cursor-pointer rounded-xl transition ring-2 ring-transparent
            hover:ring-avian-green/40 focus-visible:ring-avian-green"
            data-status-filter="rejected" role="button" tabindex="0">
            <x-stat-card
                label="Rejected"
                :count="$countRejected"
                subtitle="Pengajuan ditolak"
                color="red"
            />
        </div>

        <div class="stat-filter-card grid cursor-pointer rounded-xl transition ring-2 ring-transparent
            hover:ring-avian-green/40 focus-visible:ring-avian-green"
            data-kategori-filter="over_threshold" role="button" tabindex="0">
            <x-stat-card
                label="Over Threshold"
                :count="$countOverThreshold"
                subtitle="Rasio sewa / PAC di atas ambang"
                color="orange"
            />
        </div>
    </div>

    {{-- Pengajuan Table Section --}}
    <div class="rounded-xl bg-white shadow-sm">

        <div class="border-b border-gray-100 px-6 py-4">
            <h2 class="text-sm font-semibold text-gray-700">
                Pengajuan Sewa — Semua Cabang
            </h2>
        </div>

        <div class="p-6">
            <x-tabel-pengajuan mode="dashboard" :limit="0" />
        </div>
    </div>

</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('pengajuanSearch');
    const rows = document.querySelectorAll('.pengajuan-row');
    const statFilterCards = document.querySelectorAll('.stat-filter-card');

    let activeStatusFilter = 'all';
    let activeKategoriFilter = null; // null = nggak difilter kategori; 'over_threshold' = aktif

    function applyFilters() {
        const searchTerm = searchInput.value.toLowerCase();

        rows.forEach(row => {
            const perusahaan = row.getAttribute('data-perusahaan') || '';
            const status = row.getAttribute('data-status') || '';
            const kategori = row.getAttribute('data-kategori') || '';

            const matchesSearch = perusahaan.includes(searchTerm);
            const matchesStatus = (activeStatusFilter === 'all') || (status === activeStatusFilter);
            const matchesKategori = !activeKategoriFilter || (kategori === activeKategoriFilter);

            row.style.display = (matchesSearch && matchesStatus && matchesKategori) ? '' : 'none';
        });
    }

    searchInput.addEventListener('keyup', applyFilters);

    statFilterCards.forEach(card => {
        card.addEventListener('click', function() {
            const statusVal = this.getAttribute('data-status-filter');
            const kategoriVal = this.getAttribute('data-kategori-filter');
            if (statusVal !== null) {
                activeStatusFilter = (activeStatusFilter === statusVal) ? 'all' : statusVal;
            } else if (kategoriVal !== null) {
                activeKategoriFilter = (activeKategoriFilter === kategoriVal) ? null : kategoriVal;
            }
            updateCardHighlight();
            applyFilters();
        });

        card.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                this.click();
            }
        });
    });

    function updateCardHighlight() {
        statFilterCards.forEach(card => {
            const statusVal = card.getAttribute('data-status-filter');
            const kategoriVal = card.getAttribute('data-kategori-filter');
            const isActive = (statusVal !== null && statusVal === activeStatusFilter && activeStatusFilter !== 'all')
                || (kategoriVal !== null && kategoriVal === activeKategoriFilter);
            card.classList.toggle('ring-avian-green', isActive);
            card.classList.toggle('ring-transparent', !isActive);
        });
    }

    updateCardHighlight();
});
</script>

@endsection

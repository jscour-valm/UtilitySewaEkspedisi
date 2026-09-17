@extends('layouts.app')

@section('title', 'Dashboard DCI')

@section('content')
<div class="space-y-4">
    {{-- Summary Cards — read-only, klik buat filter tabel di bawah --}}
    <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-5">
        <div class="stat-filter-card cursor-pointer rounded-xl bg-white shadow-sm transition ring-2 ring-transparent
            hover:ring-avian-green/40 focus-visible:ring-avian-green"
            data-status-filter="all" role="button" tabindex="0">
            <x-stat-card
                label="Total Pengajuan"
                :count="$countTotal"
                subtitle="Semua cabang, semua status"
                color="gray"
            />
        </div>

        <div class="stat-filter-card cursor-pointer rounded-xl bg-white shadow-sm transition ring-2 ring-transparent
            hover:ring-avian-green/40 focus-visible:ring-avian-green"
            data-status-filter="pending" role="button" tabindex="0">
            <x-stat-card
                label="Pending"
                :count="$countPending"
                subtitle="Menunggu approval"
                color="yellow"
            />
        </div>

        <div class="stat-filter-card cursor-pointer rounded-xl bg-white shadow-sm transition ring-2 ring-transparent
            hover:ring-avian-green/40 focus-visible:ring-avian-green"
            data-status-filter="approved" role="button" tabindex="0">
            <x-stat-card
                label="Approved"
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

        {{-- Bukan filter status_pengajuan — kartu ini cuma informasional (kategori_approval),
             jadi TIDAK dibungkus .stat-filter-card / data-status-filter. TIDAK dibungkus div
             tambahan juga — <x-stat-card> sudah punya wrapper (bg-white rounded-xl shadow-sm)
             sendiri, dobel-bungkus bikin keliatan "tumpuk 2" (card di dalam card). --}}
        <x-stat-card
            label="Over Threshold"
            :count="$countOverThreshold"
            subtitle="Rasio sewa / PAC di atas ambang"
            color="orange"
        />
    </div>

    {{-- Pengajuan Table Section --}}
    <div class="rounded-xl bg-white shadow-sm">

        <div class="border-b border-gray-100 px-6 py-4">
            <h2 class="text-sm font-semibold text-gray-700">
                Pengajuan Sewa — Semua Cabang
            </h2>
        </div>

        <div class="p-6">
            {{-- mode="dashboard" (default) — view-only, link Action jadi "Detail"
                 (bukan "Review", itu khusus mode="wm"). Query di dalam komponen
                 otomatis lintas cabang krn DCI isGlobalAccess(). --}}
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

    function applyFilters() {
        const searchTerm = searchInput.value.toLowerCase();

        rows.forEach(row => {
            const perusahaan = row.getAttribute('data-perusahaan') || '';
            const status = row.getAttribute('data-status') || '';

            const matchesSearch = perusahaan.includes(searchTerm);
            const matchesStatus = (activeStatusFilter === 'all') || (status === activeStatusFilter);

            row.style.display = (matchesSearch && matchesStatus) ? '' : 'none';
        });
    }

    searchInput.addEventListener('keyup', applyFilters);

    statFilterCards.forEach(card => {
        card.addEventListener('click', function() {
            const filterValue = this.getAttribute('data-status-filter');
            activeStatusFilter = (activeStatusFilter === filterValue) ? 'all' : filterValue;
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
            const filterValue = card.getAttribute('data-status-filter');
            if (filterValue === activeStatusFilter && activeStatusFilter !== 'all') {
                card.classList.add('ring-avian-green', 'ring-2');
                card.classList.remove('ring-transparent');
            } else {
                card.classList.remove('ring-avian-green');
                card.classList.add('ring-transparent', 'ring-2');
            }
        });
    }

    updateCardHighlight();
});
</script>

@endsection

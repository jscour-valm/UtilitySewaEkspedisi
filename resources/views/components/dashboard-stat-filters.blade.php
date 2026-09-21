{{--
  Dashboard Stat-Filter Cards Component
  Used in: Dashboard WM, Dashboard WH

  Bundling grid 4 <x-stat-card> (Total/Pending/Approved/Rejected) + <script> filter
  search+status. Nempel ke #pengajuanSearch & .pengajuan-row yang dirender
  <x-tabel-pengajuan> di bawahnya (loosely coupled by convention, sama kayak
  komponen itu sendiri).

  @props([
    'countTotal', 'countPending', 'countApproved', 'countRejected',
    'searchAttrs' => ['data-perusahaan'],   // atribut data-* yang di-OR buat search
    'pendingOverrideAttr' => null,          // kalau diisi, filter "pending" cek attr ini === '1' bukan data-status
    'totalSubtitle' => 'Semua status',
    'pendingSubtitle' => 'Menunggu review',
    'approvedLabel' => 'Disetujui',
    'approvedSubtitle' => 'Pengajuan disetujui',
    'rejectedLabel' => 'Ditolak',
    'rejectedSubtitle' => 'Pengajuan ditolak',
  ])
  @example
    <x-dashboard-stat-filters
        :countTotal="$countTotal" :countPending="$countPending"
        :countApproved="$countApproved" :countRejected="$countRejected"
        :searchAttrs="['data-kagud', 'data-perusahaan']"
    />
--}}

@props([
    'countTotal',
    'countPending',
    'countApproved',
    'countRejected',
    'searchAttrs' => ['data-perusahaan'],
    'pendingOverrideAttr' => null,
    'totalSubtitle' => 'Semua status',
    'pendingSubtitle' => 'Menunggu review',
    'approvedLabel' => 'Disetujui',
    'approvedSubtitle' => 'Pengajuan disetujui',
    'rejectedLabel' => 'Ditolak',
    'rejectedSubtitle' => 'Pengajuan ditolak',
])

<div id="stat-filter-grid" class="grid grid-cols-4 gap-4"
    data-search-attrs="{{ json_encode($searchAttrs) }}"
    @if($pendingOverrideAttr) data-pending-override-attr="{{ $pendingOverrideAttr }}" @endif>

    <div class="stat-filter-card grid cursor-pointer rounded-xl transition ring-2 ring-transparent
        hover:ring-avian-green/40 focus-visible:ring-avian-green"
        data-status-filter="all" role="button" tabindex="0">
        <x-stat-card
            label="Total Pengajuan"
            :count="$countTotal"
            :subtitle="$totalSubtitle"
            color="gray"
        />
    </div>

    <div class="stat-filter-card grid cursor-pointer rounded-xl transition ring-2 ring-transparent
        hover:ring-avian-green/40 focus-visible:ring-avian-green"
        data-status-filter="pending" role="button" tabindex="0">
        <x-stat-card
            label="Pending"
            :count="$countPending"
            :subtitle="$pendingSubtitle"
            color="yellow"
        />
    </div>

    <div class="stat-filter-card grid cursor-pointer rounded-xl transition ring-2 ring-transparent
        hover:ring-avian-green/40 focus-visible:ring-avian-green"
        data-status-filter="approved" role="button" tabindex="0">
        <x-stat-card
            :label="$approvedLabel"
            :count="$countApproved"
            :subtitle="$approvedSubtitle"
            color="green"
        />
    </div>

    <div class="stat-filter-card grid cursor-pointer rounded-xl transition ring-2 ring-transparent
        hover:ring-avian-green/40 focus-visible:ring-avian-green"
        data-status-filter="rejected" role="button" tabindex="0">
        <x-stat-card
            :label="$rejectedLabel"
            :count="$countRejected"
            :subtitle="$rejectedSubtitle"
            color="red"
        />
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const grid = document.getElementById('stat-filter-grid');
    if (!grid) return;

    const searchAttrs = JSON.parse(grid.dataset.searchAttrs || '["data-perusahaan"]');
    const pendingOverrideAttr = grid.dataset.pendingOverrideAttr || null;

    const searchInput = document.getElementById('pengajuanSearch');
    const rows = document.querySelectorAll('.pengajuan-row');
    const statFilterCards = grid.querySelectorAll('.stat-filter-card');

    let activeStatusFilter = 'all'; // State untuk status filter aktif

    // Function untuk apply both filters (search term + status filter)
    function applyFilters() {
        const searchTerm = searchInput.value.toLowerCase();

        rows.forEach(row => {
            // Check apakah match dengan search term (OR dari semua searchAttrs)
            const matchesSearch = searchAttrs.some(attr => (row.getAttribute(attr) || '').includes(searchTerm));

            // Check apakah match dengan status filter. Kalau filter "pending" punya
            // override attr (mis. wh_ready), pakai itu — bukan sekadar data-status mentah.
            const matchesStatus = (activeStatusFilter === 'all')
                || (activeStatusFilter === 'pending' && pendingOverrideAttr
                    ? row.getAttribute(pendingOverrideAttr) === '1'
                    : (row.getAttribute('data-status') || '') === activeStatusFilter);

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

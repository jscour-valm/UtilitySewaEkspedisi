@extends('layouts.app')

@section('title', 'Daftar Kendaraan')

@section('content')

<div class="rounded-xl bg-white shadow-sm p-6">
    {{-- Filter bar (client-side, di atas search bawaan komponen) --}}
    <div class="mb-4 flex flex-wrap items-center gap-3">
        <button
            type="button"
            id="btnBukaFilter"
            class="relative flex items-center gap-2 rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 4h18M6 10h12M10 16h4" />
            </svg>
            Filter
            <span id="filterBadge" class="hidden rounded-full bg-avian-green px-1.5 py-0.5 text-[10px] font-semibold text-white"></span>
        </button>
    </div>

    <x-tabel-kendaraan mode="detail" :no-limit="true" />
</div>

{{-- Modal Filter --}}
<div id="filterModalOverlay" class="fixed inset-0 z-40 hidden bg-black/40" aria-hidden="true"></div>
<div id="filterModal" class="fixed inset-0 z-50 hidden items-center justify-center p-4">
    <div class="flex max-h-[85vh] w-full max-w-lg flex-col rounded-xl bg-white shadow-xl">
        <div class="flex items-center justify-between border-b border-gray-200 px-5 py-4">
            <h2 class="text-base font-semibold text-gray-800">Filter Kendaraan</h2>
            <button type="button" id="btnTutupFilter" class="rounded-full p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        <div class="flex-1 overflow-y-auto px-5 py-4">
            <div class="mb-5">
                <h3 class="mb-2 text-sm font-semibold text-gray-700">Cabang</h3>
                <div id="pillCabang" class="flex flex-wrap gap-2"></div>
            </div>
            <div class="mb-5">
                <h3 class="mb-2 text-sm font-semibold text-gray-700">Area / Skill</h3>
                <div id="pillSkill" class="flex flex-wrap gap-2"></div>
            </div>
            <div class="mb-5">
                <h3 class="mb-2 text-sm font-semibold text-gray-700">Jenis Kendaraan</h3>
                <div id="pillJenis" class="flex flex-wrap gap-2"></div>
            </div>
            <div>
                <h3 class="mb-2 text-sm font-semibold text-gray-700">Badan Usaha</h3>
                <div id="pillBadan" class="flex flex-wrap gap-2"></div>
            </div>
        </div>

        <div class="flex items-center justify-between border-t border-gray-200 px-5 py-4">
            <button type="button" id="btnResetFilter" class="text-sm font-medium text-gray-500 hover:text-gray-700">
                Reset
            </button>
            <button
                type="button"
                id="btnTerapkanFilter"
                class="rounded-lg bg-avian-green px-4 py-2 text-sm font-medium text-white hover:bg-avian-green-dark">
                Tampilkan (<span id="filterCount">0</span>)
            </button>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('searchKendaraan');
    const rows         = Array.from(document.querySelectorAll('.kendaraan-row'));

    const btnBuka    = document.getElementById('btnBukaFilter');
    const btnTutup   = document.getElementById('btnTutupFilter');
    const btnReset   = document.getElementById('btnResetFilter');
    const btnTerapkan = document.getElementById('btnTerapkanFilter');
    const overlay    = document.getElementById('filterModalOverlay');
    const modal      = document.getElementById('filterModal');
    const filterBadge = document.getElementById('filterBadge');
    const filterCount = document.getElementById('filterCount');

    if (!rows.length) return;

    // --- State filter: 3 kategori, tiap kategori bisa pilih banyak (union di dalam kategori) ---
    const selected = {
        cabang: new Set(),
        skill: new Set(),
        jenis: new Set(),
        badan: new Set(),
    };

    const uniq = (arr) => [...new Set(arr.filter(Boolean))].sort((a, b) => a.localeCompare(b));

    const cabangVals = uniq(
        rows.flatMap(r => (r.dataset.cabang || '').split(', ').map(s => s.trim()).filter(Boolean))
    );
    const skillTokens = uniq(
        rows.flatMap(r => (r.dataset.skill || '').split(', ').map(s => s.trim()).filter(Boolean))
    );
    const jenisVals = uniq(
        rows.flatMap(r => (r.dataset.kendaraan || '').split(', ').map(s => s.trim()).filter(Boolean))
    );
    const badanVals = uniq(rows.map(r => (r.dataset.badan || '').trim()));

    function labelize(v) {
        return v.replace(/\b\w/g, c => c.toUpperCase());
    }

    function pillClass(active) {
        return active
            ? 'rounded-full border border-avian-green bg-avian-green-light px-3 py-1.5 text-xs font-medium text-avian-green transition'
            : 'rounded-full border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-600 transition hover:bg-gray-50';
    }

    function renderPills(containerId, vals, category) {
        const container = document.getElementById(containerId);
        if (!container) return;
        container.innerHTML = '';
        if (!vals.length) {
            container.innerHTML = '<span class="text-xs text-gray-400">Tidak ada data.</span>';
            return;
        }
        vals.forEach(v => {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.textContent = labelize(v);
            btn.className = pillClass(selected[category].has(v));
            btn.addEventListener('click', () => {
                if (selected[category].has(v)) {
                    selected[category].delete(v);
                } else {
                    selected[category].add(v);
                }
                btn.className = pillClass(selected[category].has(v));
                updatePreviewCount();
            });
            container.appendChild(btn);
        });
    }

    function matches(row) {
        const q      = (searchInput?.value || '').toLowerCase();
        const nama   = row.dataset.nama || '';
        const badan  = row.dataset.badan || '';
        const cabang = row.dataset.cabang || '';
        const skill  = row.dataset.skill || '';
        const kend   = row.dataset.kendaraan || '';
        const cabangTok = cabang.split(', ').map(s => s.trim()).filter(Boolean);
        const skillTok = skill.split(', ').map(s => s.trim()).filter(Boolean);
        const kendTok  = kend.split(', ').map(s => s.trim()).filter(Boolean);

        const mSearch = !q || nama.includes(q) || badan.includes(q) || skill.includes(q) || kend.includes(q);
        const mCabang = selected.cabang.size === 0 || cabangTok.some(c => selected.cabang.has(c));
        const mSkill  = selected.skill.size === 0 || skillTok.some(s => selected.skill.has(s));
        const mJenis  = selected.jenis.size === 0 || kendTok.some(k => selected.jenis.has(k));
        const mBadan  = selected.badan.size === 0 || selected.badan.has(badan);

        return mSearch && mCabang && mSkill && mJenis && mBadan;
    }

    function updatePreviewCount() {
        const count = rows.filter(matches).length;
        filterCount.textContent = count;
    }

    function applyFilters() {
        rows.forEach(row => {
            row.style.display = matches(row) ? '' : 'none';
        });
        const totalActive = selected.cabang.size + selected.skill.size + selected.jenis.size + selected.badan.size;
        if (totalActive > 0) {
            filterBadge.textContent = totalActive;
            filterBadge.classList.remove('hidden');
        } else {
            filterBadge.classList.add('hidden');
        }
    }

    function openModal() {
        updatePreviewCount();
        overlay.classList.remove('hidden');
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    }

    function closeModal() {
        overlay.classList.add('hidden');
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }

    renderPills('pillCabang', cabangVals, 'cabang');
    renderPills('pillSkill', skillTokens, 'skill');
    renderPills('pillJenis', jenisVals, 'jenis');
    renderPills('pillBadan', badanVals, 'badan');

    btnBuka?.addEventListener('click', openModal);
    btnTutup?.addEventListener('click', closeModal);
    overlay?.addEventListener('click', closeModal);
    btnTerapkan?.addEventListener('click', () => {
        applyFilters();
        closeModal();
    });
    btnReset?.addEventListener('click', () => {
        selected.cabang.clear();
        selected.skill.clear();
        selected.jenis.clear();
        selected.badan.clear();
        renderPills('pillCabang', cabangVals, 'cabang');
        renderPills('pillSkill', skillTokens, 'skill');
        renderPills('pillJenis', jenisVals, 'jenis');
        renderPills('pillBadan', badanVals, 'badan');
        updatePreviewCount();
    });

    searchInput?.addEventListener('keyup', applyFilters);

    applyFilters();
});
</script>

@endsection

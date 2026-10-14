{{--
    Sidebar menu utama; isi menu mengikuti role user. Lebar mengikuti `sidebarOpen` di x-data <body>.
    Dipakai di: layouts/app.
--}}
<aside
    :class="sidebarOpen ? 'w-56' : 'w-16'"
    class="bg-avian-green-dark text-white transition-all duration-300 flex flex-col overflow-hidden shrink-0">

    {{-- Header Sidebar --}}
    <div class="h-16 border-b border-green-800 flex items-center px-3">

        {{-- Collapsed: hanya tombol, centered --}}
        <div x-show="!sidebarOpen" class="flex w-full justify-center">
            <button
                @click="sidebarOpen = true"
                class="p-2 rounded-lg hover:bg-green-800 transition">
                <i data-lucide="menu" class="w-5 h-5"></i>
            </button>
        </div>

        {{-- Expanded: tombol + logo + nama --}}
        <div x-show="sidebarOpen" x-cloak class="flex items-center gap-3 w-full">
            <button
                @click="sidebarOpen = false"
                class="p-2 rounded-lg hover:bg-green-800 transition shrink-0">
                <i data-lucide="menu" class="w-5 h-5"></i>
            </button>
            <img
                src="{{ asset('assets/logo-avian.webp') }}"
                class="w-7 h-7 shrink-0"
                alt="Logo">
            <div class="min-w-0">
                <p class="font-semibold leading-none truncate">Utility</p>
                <p class="text-xs text-green-200 truncate">Sewa Ekspedisi</p>
            </div>
        </div>

    </div>

    {{-- Sidebar Menu --}}
    <nav class="min-h-0 flex-1 overflow-y-auto py-4 space-y-1">

        @php
            $role = auth()->user()?->userUtility?->role;
            $dashboardRoute = match($role) {
                'KG'  => 'dashboard.kg',
                'WM'  => 'dashboard.wm',
                'WH'  => 'dashboard.wh',
                'WC'  => 'dashboard.wc',
                'DCI' => 'dashboard.dci',
                'KA'  => 'dashboard.ka',
                default => 'dashboard',
            };
            $onDashboard = request()->is('dashboard/*', 'kaadmin/*');
            $iconWrapClass = "flex items-center justify-center shrink-0 h-5";
        @endphp

        {{-- Dashboard — semua role --}}
        <a href="{{ route($dashboardRoute) }}"
            class="flex items-center gap-3 py-3 px-4 rounded-lg mx-2 transition text-sm
            {{ $onDashboard
                ? 'bg-green-800 text-white font-semibold'
                : 'text-green-100 hover:bg-green-800/60' }}">
            <span class="{{ $iconWrapClass }}" :class="sidebarOpen ? 'w-5' : 'w-full'">
                <i data-lucide="layout-dashboard" class="w-5 h-5"></i>
            </span>
            <span x-show="sidebarOpen" x-cloak class="truncate">Dashboard</span>
        </a>

        {{-- Perusahaan (master vendor & tarif) — KG/WM/WH/DCI, WC read-only --}}
        @if(in_array($role, ['KG', 'KA', 'WM', 'WH', 'WC', 'DCI']))
        <a href="{{ route('perusahaan.index') }}"
            class="flex items-center gap-3 py-3 px-4 rounded-lg mx-2 transition text-sm
            {{ request()->is('perusahaan*')
                ? 'bg-green-800 text-white font-semibold'
                : 'text-green-100 hover:bg-green-800/60' }}">
            <span class="{{ $iconWrapClass }}" :class="sidebarOpen ? 'w-5' : 'w-full'">
                <i data-lucide="building-2" class="w-5 h-5"></i>
            </span>
            <span x-show="sidebarOpen" x-cloak class="truncate">Perusahaan</span>
        </a>
        @endif

        {{-- Pengajuan Baru — hanya KG --}}
        @if($role === 'KG')
        <a href="{{ route('pengajuan.kg') }}"
            class="flex items-center gap-3 py-3 px-4 rounded-lg mx-2 transition text-sm
            {{ request()->routeIs('pengajuan.kg', 'pengajuan.edit')
                ? 'bg-green-800 text-white font-semibold'
                : 'text-green-100 hover:bg-green-800/60' }}">
            <span class="{{ $iconWrapClass }}" :class="sidebarOpen ? 'w-5' : 'w-full'">
                <i data-lucide="file-text" class="w-5 h-5"></i>
            </span>
            <span x-show="sidebarOpen" x-cloak class="truncate">Pengajuan Baru</span>
        </a>
        @endif

        {{-- Semua pengajuan sewa sesuai cakupan cabang peran. Untuk KG menunya bernama History. --}}
        <a href="{{ route('pengajuan.daftar') }}"
            class="flex items-center gap-3 py-3 px-4 rounded-lg mx-2 transition text-sm
            {{ request()->routeIs('pengajuan.daftar')
                ? 'bg-green-800 text-white font-semibold'
                : 'text-green-100 hover:bg-green-800/60' }}">
            <span class="{{ $iconWrapClass }}" :class="sidebarOpen ? 'w-5' : 'w-full'">
                <i data-lucide="{{ $role === 'KG' ? 'history' : 'clipboard-list' }}" class="w-5 h-5"></i>
            </span>
            <span x-show="sidebarOpen" x-cloak class="truncate">{{ $role === 'KG' ? 'History' : 'Daftar Pengajuan' }}</span>
        </a>

        {{--
            Menu bergrup (submenu):
            - Master Data — WM, WC, WH, DCI. Jenis Barang / Biaya Tambahan / Kendaraan: CRUD DCI,
              role lain lihat saja. Master Skill: WM (cabang sendiri), WC, WH, DCI kelola; hapus DCI.
            - Pengaturan — hanya DCI: batas rasio, alur approval, WM per cabang, penerima email.
        --}}
        @php
            $grupMenu = [];
            if (in_array($role, ['DCI', 'WH', 'WC', 'WM'], true)) {
                $grupMenu[] = ['label' => 'Master Data', 'ikon' => 'folder-tree', 'match' => 'master*', 'links' => [
                    ['route' => 'master.jenis-barang-kiriman', 'match' => 'master/jenis-barang-kiriman*', 'label' => 'Jenis Barang Kiriman'],
                    ['route' => 'master.jenis-biaya-tambahan', 'match' => 'master/jenis-biaya-tambahan*', 'label' => 'Jenis Biaya Tambahan'],
                    ['route' => 'master.jenis-kendaraan', 'match' => 'master/jenis-kendaraan*', 'label' => 'Jenis Kendaraan'],
                    ['route' => 'master.skill', 'match' => 'master/skill*', 'label' => 'Master Skill'],
                ]];
            }
            if ($role === 'DCI') {
                $grupMenu[] = ['label' => 'Pengaturan', 'ikon' => 'sliders-horizontal', 'match' => ['pengaturan*', 'setting-approver*'], 'links' => [
                    ['route' => 'pengaturan.rasio', 'match' => 'pengaturan/rasio*', 'label' => 'Batas Rasio Sewa'],
                    ['route' => 'pengaturan.alur', 'match' => 'pengaturan/alur*', 'label' => 'Alur Approval'],
                    ['route' => 'setting-approver.index', 'match' => 'setting-approver*', 'label' => 'WM per Cabang'],
                    ['route' => 'pengaturan.email', 'match' => 'pengaturan/email*', 'label' => 'Penerima Email'],
                ]];
            }
        @endphp
        @foreach($grupMenu as $grup)
        @php $diGrup = request()->is($grup['match']) ? 'true' : 'false'; @endphp
        {{--
            Sidebar terbuka: submenu menempel di bawah tombol (klik). Sidebar ciut: submenu jadi
            flyout saat hover. Flyout memakai `position: fixed` dengan koordinat dari
            getBoundingClientRect() tombol, karena <aside> `overflow-hidden` memotong elemen absolute.
        --}}
        <div class="relative" x-data="{ open: {{ $diGrup }}, hovering: false, flyoutStyle: {} }"
            @mouseenter="hovering = true; $nextTick(() => { const r = $refs.tombolGrup.getBoundingClientRect(); flyoutStyle = { top: r.top + 'px', left: (r.right + 8) + 'px' } })"
            @mouseleave="hovering = false">
            <button
                x-ref="tombolGrup"
                type="button"
                @click="open = !open"
                class="flex items-center gap-3 py-3 px-4 rounded-lg mx-2 transition text-sm"
                :class="((open && sidebarOpen) || {{ $diGrup }}) ? 'bg-green-800 text-white font-semibold' : 'text-green-100 hover:bg-green-800/60'">
                <span class="{{ $iconWrapClass }}" :class="sidebarOpen ? 'w-5' : 'w-full'">
                    <i data-lucide="{{ $grup['ikon'] }}" class="w-5 h-5"></i>
                </span>
                <span x-show="sidebarOpen" x-cloak class="truncate flex-1 text-left">{{ $grup['label'] }}</span>
                <i x-show="sidebarOpen" x-cloak data-lucide="chevron-down" class="w-4 h-4 shrink-0 transition-transform" :class="open ? 'rotate-180' : ''"></i>
            </button>

            {{-- Dropdown inline — sidebar expanded, dipicu klik (`open`) --}}
            <div x-show="open && sidebarOpen" x-cloak class="mt-1 ml-6 pl-4 border-l border-green-700/60 space-y-1">
                @foreach($grup['links'] as $link)
                <a href="{{ route($link['route']) }}"
                    class="block py-2 px-3 mr-2 rounded-lg text-sm transition
                    {{ request()->is($link['match'])
                        ? 'bg-green-800 text-white font-semibold'
                        : 'text-green-200 hover:bg-green-800/60' }}">
                    <span class="truncate">{{ $link['label'] }}</span>
                </a>
                @endforeach
            </div>

            {{-- Flyout — sidebar collapsed, dipicu hover, position: fixed (lihat catatan di atas) --}}
            <div x-show="hovering && !sidebarOpen" x-cloak :style="flyoutStyle"
                class="fixed w-56 rounded-lg bg-avian-green-dark shadow-lg border border-green-800 py-2 z-50">
                <p class="px-4 py-1.5 text-xs font-semibold text-green-300 uppercase tracking-wide">{{ $grup['label'] }}</p>
                @foreach($grup['links'] as $link)
                <a href="{{ route($link['route']) }}"
                    class="block py-2 px-4 text-sm transition
                    {{ request()->is($link['match'])
                        ? 'bg-green-800 text-white font-semibold'
                        : 'text-green-100 hover:bg-green-800/60' }}">
                    <span class="truncate">{{ $link['label'] }}</span>
                </a>
                @endforeach
            </div>
        </div>
        @endforeach

    </nav>

    @include('components.layout.profil-sidebar')
</aside>

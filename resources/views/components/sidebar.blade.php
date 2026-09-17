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
    <nav class="flex-1 py-4 space-y-1">

        @php
            $role = auth()->user()?->userUtility?->role;
            $dashboardRoute = match($role) {
                'KG'  => 'dashboard.kg',
                'WM'  => 'dashboard.wm',
                'WH'  => 'dashboard.wh',
                'DCI' => 'dashboard.dci',
                default => 'dashboard',
            };
            $onDashboard = request()->is('dashboard/*');
            $iconWrapClass = "flex items-center justify-center shrink-0 h-5";
        @endphp

        {{-- Dashboard — semua role kecuali KA --}}
        @if($role !== 'KA')
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
        @endif

        {{-- Kendaraan — hanya KG (16 Sept, round 16: WM/WH/DCI dipindah ke jalur lain) --}}
        @if($role === 'KG')
        <a href="{{ route('kendaraan.idx') }}"
            class="flex items-center gap-3 py-3 px-4 rounded-lg mx-2 transition text-sm
            {{ request()->is('kendaraan*')
                ? 'bg-green-800 text-white font-semibold'
                : 'text-green-100 hover:bg-green-800/60' }}">
            <span class="{{ $iconWrapClass }}" :class="sidebarOpen ? 'w-5' : 'w-full'">
                <i data-lucide="truck" class="w-5 h-5"></i>
            </span>
            <span x-show="sidebarOpen" x-cloak class="truncate">Kendaraan</span>
        </a>
        @endif

        {{--
            Kendaraan (WM/WH) — round 16: WM/WH nggak pakai halaman /kendaraan lama
            lagi, digantikan akses read-only ke Master Tabel Sewa Truk & Kiriman
            Rutin (yang sebelumnya DCI-only). Pola dropdown+flyout SAMA PERSIS
            kayak "Master Data" di bawah, cuma isinya 2 link & role-nya WM/WH.
        --}}
        @if(in_array($role, ['WM', 'WH']))
        @php
            $onKendaraanTarif = request()->is('kelola-tarif*');
            $kendaraanTarifLinks = [
                ['route' => 'kelola-tarif.sewa-truk', 'match' => 'kelola-tarif/sewa-truk*', 'label' => 'Sewa Truk'],
                ['route' => 'kelola-tarif.kiriman-rutin', 'match' => 'kelola-tarif/kiriman-rutin*', 'label' => 'Kiriman Rutin'],
            ];
        @endphp
        <div class="relative" x-data="{ open: {{ $onKendaraanTarif ? 'true' : 'false' }}, hovering: false, flyoutStyle: {} }"
            @mouseenter="hovering = true; $nextTick(() => { const r = $refs.kendaraanTarifBtn.getBoundingClientRect(); flyoutStyle = { top: r.top + 'px', left: (r.right + 8) + 'px' } })"
            @mouseleave="hovering = false">
            <button
                x-ref="kendaraanTarifBtn"
                type="button"
                @click="open = !open"
                class="flex items-center gap-3 py-3 px-4 rounded-lg mx-2 transition text-sm"
                :class="((open && sidebarOpen) || {{ $onKendaraanTarif ? 'true' : 'false' }}) ? 'bg-green-800 text-white font-semibold' : 'text-green-100 hover:bg-green-800/60'">
                <span class="{{ $iconWrapClass }}" :class="sidebarOpen ? 'w-5' : 'w-full'">
                    <i data-lucide="truck" class="w-5 h-5"></i>
                </span>
                <span x-show="sidebarOpen" x-cloak class="truncate flex-1 text-left">Kendaraan</span>
                <i x-show="sidebarOpen" x-cloak data-lucide="chevron-down" class="w-4 h-4 shrink-0 transition-transform" :class="open ? 'rotate-180' : ''"></i>
            </button>

            {{-- Dropdown inline — sidebar expanded, dipicu klik (`open`) --}}
            <div x-show="open && sidebarOpen" x-cloak class="mt-1 ml-6 pl-4 border-l border-green-700/60 space-y-1">
                @foreach($kendaraanTarifLinks as $link)
                <a href="{{ route($link['route']) }}"
                    class="block py-2 px-3 mr-2 rounded-lg text-sm transition
                    {{ request()->is($link['match'])
                        ? 'bg-green-800 text-white font-semibold'
                        : 'text-green-200 hover:bg-green-800/60' }}">
                    <span class="truncate">{{ $link['label'] }}</span>
                </a>
                @endforeach
            </div>

            {{-- Flyout — sidebar collapsed, dipicu hover, position: fixed --}}
            <div x-show="hovering && !sidebarOpen" x-cloak :style="flyoutStyle"
                class="fixed w-56 rounded-lg bg-avian-green-dark shadow-lg border border-green-800 py-2 z-50">
                <p class="px-4 py-1.5 text-xs font-semibold text-green-300 uppercase tracking-wide">Kendaraan</p>
                @foreach($kendaraanTarifLinks as $link)
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
        @endif

        {{-- Pengajuan Baru — hanya KG --}}
        @if($role === 'KG')
        <a href="{{ route('pengajuan.kg') }}"
            class="flex items-center gap-3 py-3 px-4 rounded-lg mx-2 transition text-sm
            {{ request()->is('pengajuan/baru*')
                ? 'bg-green-800 text-white font-semibold'
                : 'text-green-100 hover:bg-green-800/60' }}">
            <span class="{{ $iconWrapClass }}" :class="sidebarOpen ? 'w-5' : 'w-full'">
                <i data-lucide="file-text" class="w-5 h-5"></i>
            </span>
            <span x-show="sidebarOpen" x-cloak class="truncate">Pengajuan Baru</span>
        </a>
        @endif

        {{-- History — hanya KG --}}
        @if($role === 'KG')
        <a href="#"
            class="flex items-center gap-3 py-3 px-4 rounded-lg mx-2 transition text-sm
            {{ request()->is('history*')
                ? 'bg-green-800 text-white font-semibold'
                : 'text-green-100 hover:bg-green-800/60' }}">
            <span class="{{ $iconWrapClass }}" :class="sidebarOpen ? 'w-5' : 'w-full'">
                <i data-lucide="history" class="w-5 h-5"></i>
            </span>
            <span x-show="sidebarOpen" x-cloak class="truncate">History</span>
        </a>
        @endif

        {{--
            Master Data — hanya DCI — parent expandable, child: Jenis Barang
            Kiriman, Jenis Biaya Tambahan, Sewa Truk, Kiriman Rutin. Sewa
            Truk & Kiriman Rutin dipindah ke sini dari menu "Kelola Tarif"
            (dihapus) — route name & URL (kelola-tarif.*) TETAP, cuma
            posisi link di sidebar yang pindah.
        --}}
        @php
            $onMasterOrTarif = request()->is('master*') || request()->is('kelola-tarif*');
            $masterLinks = [
                ['route' => 'master.jenis-barang-kiriman', 'match' => 'master/jenis-barang-kiriman*', 'label' => 'Jenis Barang Kiriman'],
                ['route' => 'master.jenis-biaya-tambahan', 'match' => 'master/jenis-biaya-tambahan*', 'label' => 'Jenis Biaya Tambahan'],
                ['route' => 'kelola-tarif.sewa-truk', 'match' => 'kelola-tarif/sewa-truk*', 'label' => 'Sewa Truk'],
                ['route' => 'kelola-tarif.kiriman-rutin', 'match' => 'kelola-tarif/kiriman-rutin*', 'label' => 'Kiriman Rutin'],
            ];
        @endphp
        @if($role === 'DCI')
        {{--
            relative + @mouseenter/@mouseleave: pas sidebar collapsed, hover ke
            icon munculin flyout melayang ala referensi Taurus — beda dari mode
            expanded yang dropdown-nya nempel di bawah tombol (posisi statis,
            dipicu klik/`open`, bukan hover).

            Flyout-nya pakai `position: fixed` (bukan `absolute`) + koordinat
            dihitung manual dari getBoundingClientRect() tombol pemicu, BUKAN
            `left-full`/`ml-2` biasa — soalnya <aside> pembungkus sidebar punya
            `overflow-hidden` (buat nyembunyiin overflow pas transisi lebar
            collapse/expand), dan elemen `absolute` TETAP ke-clip sama
            `overflow-hidden` ancestor manapun walau posisinya "keluar" secara
            visual. `position: fixed` nggak ke-clip overflow ancestor manapun
            (selama nggak ada ancestor yang punya transform/filter/perspective
            — <aside> di sini aman, cuma transition-all biasa), jadi ini
            fix root cause-nya, bukan cuma ganti tampilan.
        --}}
        <div class="relative" x-data="{ open: {{ $onMasterOrTarif ? 'true' : 'false' }}, hovering: false, flyoutStyle: {} }"
            @mouseenter="hovering = true; $nextTick(() => { const r = $refs.masterDataBtn.getBoundingClientRect(); flyoutStyle = { top: r.top + 'px', left: (r.right + 8) + 'px' } })"
            @mouseleave="hovering = false">
            <button
                x-ref="masterDataBtn"
                type="button"
                @click="open = !open"
                class="flex items-center gap-3 py-3 px-4 rounded-lg mx-2 transition text-sm"
                :class="((open && sidebarOpen) || {{ $onMasterOrTarif ? 'true' : 'false' }}) ? 'bg-green-800 text-white font-semibold' : 'text-green-100 hover:bg-green-800/60'">
                <span class="{{ $iconWrapClass }}" :class="sidebarOpen ? 'w-5' : 'w-full'">
                    <i data-lucide="folder-tree" class="w-5 h-5"></i>
                </span>
                <span x-show="sidebarOpen" x-cloak class="truncate flex-1 text-left">Master Data</span>
                <i x-show="sidebarOpen" x-cloak data-lucide="chevron-down" class="w-4 h-4 shrink-0 transition-transform" :class="open ? 'rotate-180' : ''"></i>
            </button>

            {{-- Dropdown inline — sidebar expanded, dipicu klik (`open`) --}}
            <div x-show="open && sidebarOpen" x-cloak class="mt-1 ml-6 pl-4 border-l border-green-700/60 space-y-1">
                @foreach($masterLinks as $link)
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
                <p class="px-4 py-1.5 text-xs font-semibold text-green-300 uppercase tracking-wide">Master Data</p>
                @foreach($masterLinks as $link)
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

        {{-- Setting Approver — hanya DCI --}}
        <a href="{{ route('setting-approver.index') }}"
            class="flex items-center gap-3 py-3 px-4 rounded-lg mx-2 transition text-sm
            {{ request()->is('setting-approver*')
                ? 'bg-green-800 text-white font-semibold'
                : 'text-green-100 hover:bg-green-800/60' }}">
            <span class="{{ $iconWrapClass }}" :class="sidebarOpen ? 'w-5' : 'w-full'">
                <i data-lucide="settings" class="w-5 h-5"></i>
            </span>
            <span x-show="sidebarOpen" x-cloak class="truncate">Setting Approver</span>
        </a>
        @endif

    </nav>
</aside>

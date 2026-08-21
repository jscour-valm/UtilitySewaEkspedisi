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
        @endphp

        {{-- Dashboard — semua role kecuali KA --}}
        @if($role !== 'KA')
        <a href="{{ route($dashboardRoute) }}"
            class="flex items-center gap-3 py-3 rounded-lg mx-2 transition text-sm
            {{ $onDashboard
                ? 'bg-green-800 text-white font-semibold'
                : 'text-green-100 hover:bg-green-800/60' }}"
            :class="sidebarOpen ? 'px-4' : 'px-0 justify-center'">
            <i data-lucide="layout-dashboard" class="w-5 h-5 shrink-0"></i>
            <span x-show="sidebarOpen" x-cloak class="truncate">Dashboard</span>
        </a>
        @endif

        {{-- Armada — semua role kecuali KA --}}
        @if($role !== 'KA')
        <a href="{{ route('armada.idx') }}"
            class="flex items-center gap-3 py-3 rounded-lg mx-2 transition text-sm
            {{ request()->is('armada*')
                ? 'bg-green-800 text-white font-semibold'
                : 'text-green-100 hover:bg-green-800/60' }}"
            :class="sidebarOpen ? 'px-4' : 'px-0 justify-center'">
            <i data-lucide="truck" class="w-5 h-5 shrink-0"></i>
            <span x-show="sidebarOpen" x-cloak class="truncate">Armada</span>
        </a>
        @endif

        {{-- Pengajuan Baru — hanya KG --}}
        @if($role === 'KG')
        <a href="{{ route('pengajuan.kg') }}"
            class="flex items-center gap-3 py-3 rounded-lg mx-2 transition text-sm
            {{ request()->is('pengajuan/baru*')
                ? 'bg-green-800 text-white font-semibold'
                : 'text-green-100 hover:bg-green-800/60' }}"
            :class="sidebarOpen ? 'px-4' : 'px-0 justify-center'">
            <i data-lucide="file-text" class="w-5 h-5 shrink-0"></i>
            <span x-show="sidebarOpen" x-cloak class="truncate">Pengajuan Baru</span>
        </a>
        @endif

        {{-- History — semua role --}}
        <a href="#"
            class="flex items-center gap-3 py-3 rounded-lg mx-2 transition text-sm
            {{ request()->is('history*')
                ? 'bg-green-800 text-white font-semibold'
                : 'text-green-100 hover:bg-green-800/60' }}"
            :class="sidebarOpen ? 'px-4' : 'px-0 justify-center'">
            <i data-lucide="history" class="w-5 h-5 shrink-0"></i>
            <span x-show="sidebarOpen" x-cloak class="truncate">History</span>
        </a>

        {{-- Master Data — hanya DCI --}}
        @if($role === 'DCI')
        <a href="#"
            class="flex items-center gap-3 py-3 rounded-lg mx-2 transition text-sm
            {{ request()->is('master*')
                ? 'bg-green-800 text-white font-semibold'
                : 'text-green-100 hover:bg-green-800/60' }}"
            :class="sidebarOpen ? 'px-4' : 'px-0 justify-center'">
            <i data-lucide="database" class="w-5 h-5 shrink-0"></i>
            <span x-show="sidebarOpen" x-cloak class="truncate">Master Data</span>
        </a>

        {{-- Setting Approver — hanya DCI --}}
        <a href="#"
            class="flex items-center gap-3 py-3 rounded-lg mx-2 transition text-sm
            {{ request()->is('approver*')
                ? 'bg-green-800 text-white font-semibold'
                : 'text-green-100 hover:bg-green-800/60' }}"
            :class="sidebarOpen ? 'px-4' : 'px-0 justify-center'">
            <i data-lucide="settings" class="w-5 h-5 shrink-0"></i>
            <span x-show="sidebarOpen" x-cloak class="truncate">Setting Approver</span>
        </a>
        @endif

    </nav>
</aside>
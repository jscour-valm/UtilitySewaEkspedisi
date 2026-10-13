{{--
    Profil user di dasar sidebar: avatar + nama + role; klik → panel info cabang & tombol Keluar.
    Panel memakai `position: fixed` karena <aside> sidebar `overflow-hidden` (elemen absolute di
    dalamnya terpotong); posisi kirinya mengikuti lebar sidebar (w-56 terbuka / w-16 ciut).
    Dipakai di: <aside> sidebar.
--}}
@php
    $user = auth()->user();
    $isWM = $user?->userUtility?->role === 'WM';
    $inisial = strtoupper(substr($user->name ?? 'G', 0, 1));
@endphp

<div class="border-t border-green-800 p-2" x-data="{ open: false }" @click.outside="open = false" @keydown.escape.window="open = false">
    <button type="button" @click="open = !open" title="{{ $user->name ?? 'Guest' }}"
        class="flex w-full items-center gap-3 rounded-lg px-2 py-2 text-left transition hover:bg-green-800/60"
        :class="[open && 'bg-green-800/60', !sidebarOpen && 'justify-center']">
        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-white/15 text-sm font-semibold text-white">{{ $inisial }}</span>
        <span x-show="sidebarOpen" x-cloak class="min-w-0 flex-1">
            <span class="block truncate text-sm font-medium text-white">{{ $user->name ?? 'Guest' }}</span>
            <span class="block truncate text-xs text-green-200">{{ $user->getRoleLabel() }}</span>
        </span>
        <span x-show="sidebarOpen" x-cloak class="shrink-0 text-green-200 transition-transform duration-200" :class="open && 'rotate-180'">
            <i data-lucide="chevron-up" class="h-4 w-4"></i>
        </span>
    </button>

    <div x-show="open" x-cloak x-transition.opacity
        class="fixed bottom-3 z-50 w-60 rounded-xl border border-gray-100 bg-white shadow-lg"
        :style="{ left: (sidebarOpen ? 232 : 72) + 'px' }">
        <div class="space-y-2 border-b border-gray-100 px-4 py-3">
            <div>
                <p class="text-sm font-medium text-gray-900">{{ $user->name ?? 'Guest' }}</p>
                <p class="mt-0.5 text-xs text-gray-500">{{ $user->getRoleLabel() }}</p>
            </div>
            <div class="border-t border-gray-100 pt-1">
                @if($isWM)
                    {{-- WM: area + daftar cabang yang dipegang --}}
                    @php
                        $area = $user->getArea();
                        $cabangDetails = $user->getCabangDetails();
                    @endphp
                    @if($area)
                        <p class="mb-2 text-xs font-medium text-gray-700">Area: {{ $area }}</p>
                    @endif
                    @if(!empty($cabangDetails))
                        <div class="max-h-48 space-y-1 overflow-y-auto">
                            @foreach($cabangDetails as $code => $name)
                                <p class="text-xs text-gray-600">
                                    <span class="font-medium text-gray-700">{{ $code }}</span>
                                    <span class="text-gray-400">—</span>
                                    {{ $name }}
                                </p>
                            @endforeach
                        </div>
                    @else
                        <p class="text-xs text-gray-600">Tidak Memiliki Cabang</p>
                    @endif
                @else
                    {{-- Role lain: satu cabang dari session --}}
                    <p class="text-xs text-gray-600">
                        <span class="font-medium text-gray-700">{{ session('cabang_code') ?? 'Tidak Memiliki Cabang' }}</span>
                        @if(session('cabang_name'))
                            <span class="text-gray-400">—</span>
                            {{ session('cabang_name') }}
                        @endif
                    </p>
                @endif
            </div>
        </div>
        <div class="p-1">
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-sm text-red-600 transition hover:bg-red-50">
                    <i data-lucide="log-out" class="h-4 w-4"></i>
                    Keluar
                </button>
            </form>
        </div>
    </div>
</div>

{{-- Left: Breadcrumb or Title --}}
<div class="flex items-center gap-3">
    @if(isset($breadcrumb))
        {{-- Breadcrumb Navigation --}}
        <a href="{{ $breadcrumb['back_url'] }}" class="flex items-center gap-2 px-4 py-2 rounded-lg border border-gray-200 hover:bg-gray-50 transition">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
            </svg>
            <span class="text-sm font-medium text-gray-700">{{ $breadcrumb['back_label'] }}</span>
        </a>

        <span class="text-gray-300">/</span>

        <h1 class="text-lg font-semibold text-gray-900">
            {{ $breadcrumb['title'] }}
        </h1>
    @else
        {{-- Regular Title --}}
        <h1 class="text-xl font-semibold text-gray-900">
            @yield('title')
        </h1>
    @endif
</div>

{{-- Right Side --}}
<div class="flex items-center gap-4">

    {{-- Notification --}}
    <button
        type="button"
        class="p-2 rounded-lg hover:bg-gray-100">
        <i data-lucide="bell" class="w-6 h-6"></i>
    </button>

    {{-- User Dropdown --}}
    <div class="relative" x-data="{ open: false }">

        <button
            type="button"
            @click="open = !open"
            class="flex items-center gap-2 rounded-lg px-2 py-1 hover:bg-gray-100 transition">

            <div class="w-8 h-8 rounded-full bg-avian-green text-white
                        flex items-center justify-center text-sm font-semibold">
                {{ strtoupper(substr(auth()->user()->name ?? 'G', 0, 1)) }}
            </div>

            <span class="text-sm font-medium text-gray-900">
                {{ auth()->user()->name ?? 'Guest' }}
            </span>

            <i data-lucide="chevron-down"
               class="w-4 h-4 text-gray-500 transition-transform duration-200"
               :class="open ? 'rotate-180' : ''">
            </i>
        </button>

        {{-- Dropdown --}}
        <div
            x-show="open"
            @click.outside="open = false"
            x-transition:enter="transition ease-out duration-100"
            x-transition:enter-start="opacity-0 scale-95"
            x-transition:enter-end="opacity-100 scale-100"
            x-transition:leave="transition ease-in duration-75"
            x-transition:leave-start="opacity-100 scale-100"
            x-transition:leave-end="opacity-0 scale-95"
            class="absolute right-0 mt-2 w-48 rounded-xl border border-gray-100 bg-white shadow-lg z-50"
            style="display: none;">

            {{-- Info user --}}
            <div class="px-4 py-3 border-b border-gray-100 space-y-2">
                <div>
                    <p class="text-sm font-medium text-gray-900">{{ auth()->user()->name ?? '-' }}</p>
                    <p class="text-xs text-gray-500 mt-0.5">{{ auth()->user()->getRoleLabel() }}</p>
                </div>
                @php
                    $userRole = auth()->user()->userUtility?->role;
                    $isWM = $userRole === 'WM';
                @endphp
                <div class="pt-1 border-t border-gray-100">
                    @if($isWM)
                        {{-- WM: Tampilkan Area + Daftar Cabang --}}
                        @php
                            $area = auth()->user()->getArea();
                            $cabangDetails = auth()->user()->getCabangDetails();
                        @endphp
                        @if($area)
                            <p class="text-xs font-medium text-gray-700 mb-2">Area: {{ $area }}</p>
                        @endif
                        @if(!empty($cabangDetails))
                            <div class="space-y-1">
                                @foreach($cabangDetails as $code => $name)
                                    <p class="text-xs text-gray-600">
                                        <span class="font-medium text-gray-700">{{ $code }}</span>
                                        <span class="text-gray-400">—</span>
                                        <span class="text-gray-600">{{ $name }}</span>
                                    </p>
                                @endforeach
                            </div>
                        @else
                            <p class="text-xs text-gray-600">-</p>
                        @endif
                    @else
                        {{-- Role lain: Single cabang dari session --}}
                        @php
                            $cabangCode = session('cabang_code');
                            $cabangName = session('cabang_name');
                        @endphp
                        <p class="text-xs text-gray-600">
                            <span class="font-medium text-gray-700">{{ $cabangCode ?? '-' }}</span>
                            @if($cabangName)
                                <span class="text-gray-400">—</span>
                                <span class="text-gray-600">{{ $cabangName }}</span>
                            @endif
                        </p>
                    @endif
                </div>
            </div>

            {{-- Logout --}}
            <div class="p-1">
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button
                        type="submit"
                        class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-sm text-red-600 hover:bg-red-50 transition">
                        <i data-lucide="log-out" class="w-4 h-4"></i>
                        Keluar
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
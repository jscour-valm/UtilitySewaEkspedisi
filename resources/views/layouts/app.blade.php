<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>@yield('title', 'Utility Sewa Ekspedisi')</title>

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>

    <body class="bg-gray-100"
        x-data="{
            sidebarOpen: localStorage.getItem('sidebarOpen') !== 'false',
        }"
        x-init="$watch('sidebarOpen', val => localStorage.setItem('sidebarOpen', val))">
        <div class="flex h-screen overflow-hidden">
            @include('components.sidebar')
            {{-- min-w-0: tanpa ini, flex item defaultnya nggak mau menyusut di bawah
                 lebar konten aslinya (min-width:auto bawaan flexbox) — akibatnya
                 halaman dengan tabel lebar (mis. Kelola Tarif) bikin SELURUH layout
                 ikut melebar, lalu overflow-nya kepotong diam-diam sama
                 overflow-hidden di div terluar, bukan discroll sama overflow-x-auto
                 yang dipasang di tabelnya sendiri. --}}
            <div class="flex flex-1 flex-col min-w-0">
                <header class="bg-white border-b border-gray-200">
                    {{-- Status-aware strip (shows only on pengajuan detail pages with status) --}}
                    @if(isset($breadcrumb['status']))
                        @php
                            $stripColor = match($breadcrumb['status']) {
                                'pending' => 'bg-amber-400',
                                'approved' => 'bg-green-400',
                                'rejected' => 'bg-red-400',
                                default => 'bg-gray-300',
                            };
                        @endphp
                        <div class="h-1 {{ $stripColor }}"></div>
                    @endif
                    <div class="h-16 flex items-center justify-between px-6">
                        @include('components.navbar')
                    </div>
                </header>
                <main class="flex-1 overflow-y-auto p-4">
                    @yield('content')
                </main>
            </div>
        </div>
    </body>
</html>
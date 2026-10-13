<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="sesi-lifetime" content="{{ config('session.lifetime') }}">
        <meta name="sesi-pengguna" content="{{ auth()->user()?->username }}">
        <title>@yield('title', 'Utility Sewa Ekspedisi')</title>

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>

    <body class="bg-gray-100"
        x-data="{
            sidebarOpen: localStorage.getItem('sidebarOpen') !== 'false',
            previewPhotos: [],
            previewIndex: null,
            zoomed: false,
            openLightbox(photos, i) { this.previewPhotos = photos; this.previewIndex = i; this.zoomed = false; },
            closeLightbox() { this.previewIndex = null; this.zoomed = false; },
            prevLightbox() {
                if (this.previewIndex === null) return;
                this.zoomed = false;
                this.previewIndex = (this.previewIndex - 1 + this.previewPhotos.length) % this.previewPhotos.length;
            },
            nextLightbox() {
                if (this.previewIndex === null) return;
                this.zoomed = false;
                this.previewIndex = (this.previewIndex + 1) % this.previewPhotos.length;
            },
            toggleZoom() { this.zoomed = !this.zoomed; },
        }"
        x-init="$watch('sidebarOpen', val => localStorage.setItem('sidebarOpen', val))">
        <div class="flex h-screen overflow-hidden">
            @include('components.layout.sidebar')
            <div class="flex flex-1 flex-col min-w-0">
                {{-- Bar atas hanya di halaman detail (controller mengirim $breadcrumb): garis warna status + tombol kembali, judul, badge --}}
                @isset($breadcrumb)
                <header class="bg-white border-b border-gray-200">
                    @if(isset($breadcrumb['status']))
                        @php
                            $stripColor = match($breadcrumb['status']) {
                                'pending' => 'bg-amber-400',
                                'approved' => 'bg-green-400',
                                'rejected' => 'bg-red-400',
                                'cancelled' => 'bg-gray-400',
                                default => 'bg-gray-300',
                            };
                        @endphp
                        <div class="h-1 {{ $stripColor }}"></div>
                    @endif
                    <div class="flex h-14 items-center gap-3 px-6">
                        @include('components.layout.bar-kembali')
                    </div>
                </header>
                @endisset
                <main class="flex-1 overflow-y-auto p-4">
                    {{-- Judul kecil untuk halaman yang kontennya tidak punya judul sendiri (mis. dashboard) --}}
                    @hasSection('judul_halaman')
                        <h1 class="mb-3 px-1 text-2xl font-bold text-gray-900">@yield('judul_halaman')</h1>
                    @endif
                    @yield('content')
                </main>
            </div>
        </div>

        <x-layout.photo-lightbox />

        {{-- Dialihkan dari halaman yang tidak boleh dibuka (lihat bootstrap/app.php) --}}
        @if(session('akses_ditolak'))
            <script>
                document.addEventListener('DOMContentLoaded', () => window.notify(@js(session('akses_ditolak')), 'warning', 'Tidak bisa dibuka'));
            </script>
        @endif
    </body>
</html>
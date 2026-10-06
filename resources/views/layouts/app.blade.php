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
            @include('components.sidebar')
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

        <x-photo-lightbox />
    </body>
</html>
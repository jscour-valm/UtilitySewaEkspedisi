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
            <div class="flex flex-1 flex-col">
                <header class="h-16 bg-white border-b border-gray-200 flex items-center justify-between px-6">
                    @include('components.navbar')
                </header>
                <main class="flex-1 overflow-y-auto p-4">
                    @yield('content')
                </main>
            </div>
        </div>
    </body>
</html>
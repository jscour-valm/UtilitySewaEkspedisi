<!DOCTYPE html>
<html lang="id">
    <head>
        <meta charset="UTF-8">
        <title>Login - Utility Sewa Ekspedisi</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="bg-white">

        <div class="flex items-center justify-center min-h-screen">
            <div class="w-full max-w-sm rounded-lg border border-gray-200 shadow-sm p-6">

                <div class="text-center mb-6">
                    <h1 class="text-lg font-semibold text-gray-900">Utility Sewa Ekspedisi</h1>
                    <p class="text-sm text-gray-500">Sistem Monitoring dan Approval Sewa Kendaraan Ekspedisi</p>
                </div>

                @if ($errors->any())
                <div class="mb-4 rounded-md bg-red-50 px-3 py-2 text-sm text-red-700">
                    {{ $errors->first() }}
                </div>
                @endif

                <form method="POST" action="{{ route('login.submit') }}" class="space-y-4">
                @csrf
                    <div>
                        <label for="username" class="block text-sm text-gray-500 mb-1">Username <span class="text-red-500">*</span></label>
                        <input type="text" name="username" id="username" required autofocus
                            class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm
                                    focus:outline-none focus:ring-2 focus:ring-avian-green focus:border-avian-green">
                    </div>
                    <div>
                        <label for="password" class="block text-sm text-gray-500 mb-1">Password <span class="text-red-500">*</span></label>
                        <input type="password" name="password" id="password" required
                            class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm
                                    focus:outline-none focus:ring-2 focus:ring-avian-green focus:border-avian-green">
                    </div>
                    <label class="flex items-center gap-2 text-sm text-gray-600">
                        <input type="checkbox" name="remember" class="rounded border-gray-300 text-avian-green focus:ring-avian-green">
                        Ingat saya
                    </label>
                    <button type="submit"
                            class="w-full rounded-md bg-avian-green px-3 py-2 text-sm font-medium text-white
                                    hover:bg-avian-green-dark transition-colors">
                        Masuk
                    </button>
                </form>
            </div>
        </div>
    </body>
</html>
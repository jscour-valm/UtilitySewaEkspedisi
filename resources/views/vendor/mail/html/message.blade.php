<x-mail::layout>
    {{-- Header --}}
    <x-slot:header>
        <x-mail::header :url="config('app.url')">
            Utility Sewa Ekspedisi
        </x-mail::header>
    </x-slot:header>

    {{-- Body --}}
    {!! $slot !!}

    {{-- Subcopy --}}
    @isset($subcopy)
    <x-slot:subcopy>
        <x-mail::subcopy>
            {!! $subcopy !!}
        </x-mail::subcopy>
    </x-slot:subcopy>
    @endisset

    {{-- Footer --}}
    <x-slot:footer>
        <x-mail::footer>
            Ini notifikasi otomatis dari Utility Sewa Ekspedisi. Buka aplikasi untuk detail dan tindak lanjut — mohon tidak membalas email ini.
        </x-mail::footer>
    </x-slot:footer>
</x-mail::layout>

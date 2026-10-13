{{--
    Bar atas halaman detail: tombol kembali, judul, dan badge jenis tarif.
    Dipakai di: layouts/app (hanya kalau controller mengirim $breadcrumb).
    Data: $breadcrumb = ['back_url', 'back_label', 'title', 'jenis' (opsional), 'status' (opsional, dipakai layout)].
--}}
<a href="{{ $breadcrumb['back_url'] }}" class="flex items-center gap-2 rounded-lg border border-gray-200 px-4 py-2 transition hover:bg-gray-50">
    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
        <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
    </svg>
    <span class="text-sm font-medium text-gray-700">{{ $breadcrumb['back_label'] }}</span>
</a>

<span class="text-gray-300">/</span>

<h1 class="text-lg font-semibold text-gray-900">{{ $breadcrumb['title'] }}</h1>

@if(isset($breadcrumb['jenis']))
    {{-- Jenis tarif pengajuan; warna sama dengan label di halaman Perusahaan --}}
    <span @class([
        'rounded-full px-3 py-1 text-xs font-semibold',
        'bg-blue-50 text-blue-600' => $breadcrumb['jenis'] === 'pengiriman_rutin',
        'bg-avian-green-light text-avian-green' => $breadcrumb['jenis'] !== 'pengiriman_rutin',
    ])>
        {{ \App\Models\PengajuanSewa::JENIS[$breadcrumb['jenis']] ?? 'Sewa Truk' }}
    </span>
@endif

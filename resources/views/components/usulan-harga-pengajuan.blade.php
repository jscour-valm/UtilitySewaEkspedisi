{{--
    Usulan perubahan harga master yang ikut diajukan bersama pengajuan ini. Read-only: diputuskan
    terpisah (validasi WM → approval WH) di halaman proses usulan, tidak menahan pengajuan.
--}}
@props(['pengajuan'])

@php
    use App\Helpers\FormatHelper;

    $baris = collect();
    if ($pengajuan->usulanHarga) {
        $baris->push(['label' => 'Harga sewa', 'usulan' => $pengajuan->usulanHarga]);
    }
    foreach ($pengajuan->detailKirimanRutin as $d) {
        if ($d->usulanHarga) {
            $baris->push(['label' => $d->jenisBarang?->nama_barang ?? '—', 'usulan' => $d->usulanHarga]);
        }
    }
    $bolehBuka = in_array(auth()->user()?->userUtility?->role, ['KG', 'WM', 'WH', 'DCI'], true);
@endphp

@if($baris->isNotEmpty())
<div {{ $attributes->merge(['class' => 'rounded-xl border border-gray-100 bg-white p-6 shadow-sm']) }}>
    <p class="text-[11px] font-semibold tracking-[0.09em] text-gray-500">USULAN PERUBAHAN HARGA MASTER</p>
    <p class="mb-4 mt-1 text-[13px] text-gray-400">Diproses terpisah (validasi WM → approval WH) dan tidak menahan pengajuan ini.</p>

    @foreach($baris as $b)
        @php
            $u = $b['usulan'];
            $warna = match ($u->status) {
                'approved' => 'bg-avian-green-light text-avian-green',
                'rejected' => 'bg-red-50 text-red-600',
                default => 'bg-amber-50 text-amber-700',
            };
        @endphp
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-50 py-2.5 last:border-0">
            <div class="text-sm">
                <span class="font-medium text-gray-700">{{ $b['label'] }}</span>
                <span class="mx-1 text-gray-300">·</span>
                <span class="text-gray-400">master</span>
                <span class="font-semibold text-gray-700">{{ $u->harga_lama !== null ? FormatHelper::rupiah($u->harga_lama) : 'belum ada' }}</span>
                <span class="text-gray-400">→ diusulkan</span>
                <span class="font-semibold text-avian-green">{{ FormatHelper::rupiah($u->harga_usulan) }}</span>
            </div>
            <div class="flex shrink-0 items-center gap-2">
                <span class="rounded-full px-2 py-0.5 text-[11px] font-medium {{ $warna }}">{{ $u->labelStatusPersetujuan() }}</span>
                @if($bolehBuka)
                    <a href="{{ route('persetujuan.harga.show', $u->id_usulan_harga) }}" class="text-xs font-medium text-avian-green hover:underline">Lihat →</a>
                @endif
            </div>
        </div>
    @endforeach
</div>
@endif

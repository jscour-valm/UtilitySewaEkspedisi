{{--
    Harga di Rincian Biaya + penanda vs harga master: ikon bulat di samping harga, keterangan
    di bawahnya dengan warna yang sama. Naik = merah ▲, turun = hijau ▼, sama / belum ada
    master (null) = abu-abu "–".
    $tampil = nominal yang ditampilkan (mis. subtotal), $harga = harga yang dibandingkan
    dengan $master (mis. harga per unit), $satuan opsional (mis. "/unit").
--}}
@props(['tampil', 'harga', 'master' => null, 'satuan' => ''])

@php
    $harga = (float) $harga;
    $selisih = $master !== null ? $harga - (float) $master : null;
    $arah = match (true) {
        $selisih === null || abs($selisih) < 0.005 => 'tetap',
        $selisih > 0 => 'naik',
        default => 'turun',
    };
    $warna = [
        'naik' => 'bg-red-50 text-red-600',
        'turun' => 'bg-avian-green-light text-avian-green',
        'tetap' => 'bg-gray-100 text-gray-500',
    ][$arah];
    $persen = $arah !== 'tetap' && (float) $master > 0
        ? rtrim(rtrim(number_format(abs($selisih) / (float) $master * 100, 1, ',', '.'), '0'), ',').'% · '
        : '';
    $keterangan = match (true) {
        $master === null => 'Pengajuan harga baru',
        $arah === 'tetap' => 'Sesuai harga master',
        default => $persen.($arah === 'naik' ? '+' : '−').\App\Helpers\FormatHelper::rupiah(abs($selisih)).$satuan,
    };
@endphp

<div class="shrink-0 text-right">
    <div class="flex items-center justify-end gap-2">
        <span @class(['flex h-5 w-5 items-center justify-center rounded-full', $warna])
            @if($master !== null) title="Harga master: {{ \App\Helpers\FormatHelper::rupiah($master) }}{{ $satuan }}" @endif>
            <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                @if($arah === 'naik')
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 19V5m-7 7l7-7 7 7" />
                @elseif($arah === 'turun')
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 5v14m7-7l-7 7-7-7" />
                @else
                    <path stroke-linecap="round" d="M6 12h12" />
                @endif
            </svg>
        </span>
        <span class="text-sm font-semibold text-gray-900 tabular-nums">{{ \App\Helpers\FormatHelper::rupiah($tampil) }}</span>
    </div>
    <span @class(['mt-1 inline-block whitespace-nowrap rounded px-1.5 py-0.5 text-[11px] font-medium tabular-nums', $warna])>{{ $keterangan }}</span>
</div>

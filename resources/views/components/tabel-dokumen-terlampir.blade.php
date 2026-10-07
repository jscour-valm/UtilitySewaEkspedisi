{{--
    Daftar SJ / TO-ACB terlampir di pengajuan (detail & review approval).
    $dokumen: hasil SnapshotDokumenService::ambil() — {tipe, items[], total_berat_kg, total_nilai} atau null.
--}}
@props(['dokumen' => null])

@php
    use App\Helpers\FormatHelper as F;

    $items = $dokumen['items'] ?? [];
    $isAcb = ($dokumen['tipe'] ?? null) === 'TO-ACB';
    $kolom = $isAcb ? 4 : 5;
@endphp

<div class="overflow-x-auto rounded-lg border border-gray-100 mt-4">
    <table class="w-full text-sm">
        <thead>
            <tr class="border-b border-gray-100 bg-gray-50 text-[11px] font-semibold tracking-wide text-gray-500">
                @if ($isAcb)
                    <th class="px-3.5 py-2.5 text-left">NO TO-ACB</th>
                    <th class="px-3.5 py-2.5 text-left">NO TS</th>
                @else
                    <th class="px-3.5 py-2.5 text-left">NO SJ</th>
                    <th class="px-3.5 py-2.5 text-left">CUSTOMER</th>
                    <th class="px-3.5 py-2.5 text-left">KOTA</th>
                @endif
                <th class="px-3.5 py-2.5 text-right">BERAT</th>
                <th class="px-3.5 py-2.5 text-right">NILAI</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($items as $d)
                <tr class="border-b border-gray-50 hover:bg-gray-50">
                    <td class="px-3.5 py-3 font-medium text-gray-800 whitespace-nowrap">{{ $d['nomor'] }}</td>
                    @if ($isAcb)
                        <td class="px-3.5 py-3 text-gray-600 whitespace-nowrap">{{ $d['last_shipment_no'] ?? '-' }}</td>
                    @else
                        <td class="px-3.5 py-3 text-gray-700">{{ $d['customer'] ?? '-' }}</td>
                        <td class="px-3.5 py-3 text-gray-700">{{ $d['kota'] ?? '-' }}</td>
                    @endif
                    <td class="px-3.5 py-3 text-right tabular-nums text-gray-700 whitespace-nowrap">{{ F::kg($d['berat'] ?? 0) }}</td>
                    <td class="px-3.5 py-3 text-right tabular-nums text-gray-700 whitespace-nowrap">{{ F::rupiah($d['nilai'] ?? 0) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="{{ $kolom }}" class="px-3.5 py-4 text-center text-[13px] text-gray-400">
                        Belum ada dokumen SJ/TO-ACB terlampir.
                    </td>
                </tr>
            @endforelse
        </tbody>
        @if ($items)
            <tfoot>
                <tr class="bg-gray-50 font-semibold text-gray-800">
                    <td colspan="{{ $kolom - 2 }}" class="px-3.5 py-2.5">Total ({{ count($items) }} dokumen)</td>
                    <td class="px-3.5 py-2.5 text-right tabular-nums whitespace-nowrap">{{ F::kg($dokumen['total_berat_kg'] ?? 0) }}</td>
                    <td class="px-3.5 py-2.5 text-right tabular-nums whitespace-nowrap">{{ F::rupiah($dokumen['total_nilai'] ?? 0) }}</td>
                </tr>
            </tfoot>
        @endif
    </table>
</div>

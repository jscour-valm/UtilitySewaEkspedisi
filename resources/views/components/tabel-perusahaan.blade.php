@php
use App\Models\PerusahaanEkspedisi;

// Query perusahaan yang flag=true
$perusahaanList = PerusahaanEkspedisi::where('flag', true)
    ->orderBy('nama_perusahaan')
    ->get();
@endphp

<div x-data="{ cariPerusahaan: '' }">
    {{-- Search Box --}}
    <input
        type="text"
        x-model="cariPerusahaan"
        placeholder="Cari nama perusahaan, badan usaha, telepon..."
        class="mb-4 w-full rounded-lg border border-gray-300 px-4 py-2 text-sm
        focus:border-avian-green focus:outline-none">

    {{-- Tabel Perusahaan untuk pilih --}}
    <div class="overflow-hidden rounded-xl border border-gray-200">
    <table class="w-full text-sm">
        <colgroup>
            <col class="w-[24%]">
            <col class="w-[13%]">
            <col class="w-[17%]">
            <col class="w-[30%]">
            <col class="w-[16%]">
        </colgroup>

        <thead>
            <tr class="border-b border-gray-300 bg-gray-50 text-xs font-medium uppercase tracking-wide text-gray-500">
                <th class="px-4 py-3 text-left">Nama Perusahaan</th>
                <th class="px-4 py-3 text-left">Badan Usaha</th>
                <th class="px-4 py-3 text-left">No. Telepon</th>
                <th class="px-4 py-3 text-left">Alamat Kantor</th>
                <th class="px-4 py-3 text-right">Aksi</th>
            </tr>
        </thead>

        <tbody class="divide-y divide-gray-200">
            @forelse($perusahaanList as $p)
            @php
                $searchableText = strtolower($p->nama_perusahaan . ' ' . $p->badan_usaha . ' ' . $p->no_telepon . ' ' . $p->alamat_kantor);
            @endphp
            <tr
                class="cursor-pointer transition hover:bg-gray-50"
                :class="armadaBaru.perusahaan_id === {{ $p->id_perusahaan }} ? 'bg-avian-green-light' : 'hover:bg-gray-50'"
                @click="armadaBaru.perusahaan_id = {{ $p->id_perusahaan }}"
                x-show="'{{ $searchableText }}'.includes(cariPerusahaan.toLowerCase())">
                <td class="px-4 py-3 font-medium text-gray-800">{{ $p->nama_perusahaan }}</td>
                <td class="px-4 py-3 text-gray-600">{{ $p->badan_usaha }}</td>
                <td class="px-4 py-3 text-gray-600">{{ $p->no_telepon }}</td>
                <td class="px-4 py-3 text-gray-600 truncate" :title="{{ json_encode($p->alamat_kantor) }}">{{ $p->alamat_kantor }}</td>
                <td class="px-4 py-3 text-right">
                    <button
                        type="button"
                        @click.stop="armadaBaru.perusahaan_id = {{ $p->id_perusahaan }}"
                        :class="armadaBaru.perusahaan_id === {{ $p->id_perusahaan }}
                            ? 'bg-avian-green text-white'
                            : 'border border-avian-green text-avian-green hover:bg-avian-green-light'"
                        class="rounded-lg px-3 py-1.5 text-xs font-medium transition whitespace-nowrap">
                        <span x-show="armadaBaru.perusahaan_id !== {{ $p->id_perusahaan }}">Pilih</span>
                        <span x-show="armadaBaru.perusahaan_id === {{ $p->id_perusahaan }}">✓ Dipilih</span>
                    </button>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="5" class="py-12 text-center text-sm text-gray-400">
                    Tidak ada perusahaan yang tersedia. <br>
                    <span class="text-xs">Perusahaan akan muncul setelah ditambahkan.</span>
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>
    </div>
</div>

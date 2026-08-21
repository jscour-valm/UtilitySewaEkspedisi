@props([
    'mode' => 'pilih',
    'limit' => 5,
])

@php
use App\Helpers\DbHelper;
use App\Helpers\FormatHelper;

// Get sort params dari query string
$sortBy = request('sort_armada', null);
$sortOrder = request('order_armada', 'asc');

$result = DbHelper::safeQuery(function () use ($limit, $sortBy, $sortOrder) {
    $query = DB::connection('sqlsrv')->table('sesi_armada')
        ->join('sesi_perusahaan_ekspedisi', 'sesi_armada.id_perusahaan', '=', 'sesi_perusahaan_ekspedisi.id_perusahaan')
        ->select(
            'sesi_armada.id_armada as id',
            'sesi_perusahaan_ekspedisi.badan_usaha as badan_usaha',
            'sesi_perusahaan_ekspedisi.nama_perusahaan as nama',
            'sesi_armada.id_skill as skill',
            'sesi_armada.nama_kendaraan as kendaraan',
            'sesi_armada.muatan_maksimal as muatan_raw',
            DB::raw("'Rp 0' as harga"),
            DB::raw("FORMAT(sesi_armada.updated_at, 'dd MMM yyyy') as updated")
        )
        ->where('sesi_armada.flag', true);

    // Jika ada sort eksplisit dari user, gunakan itu; jika tidak, gunakan default (updated_at desc, nanti diurutkan lagi by skill priority)
    if ($sortBy) {
        $sortMap = [
            'nama' => 'sesi_perusahaan_ekspedisi.nama_perusahaan',
            'badan_usaha' => 'sesi_perusahaan_ekspedisi.badan_usaha',
            'skill' => 'sesi_armada.id_skill',
            'kendaraan' => 'sesi_armada.nama_kendaraan',
            'muatan' => 'sesi_armada.muatan_maksimal',
            'updated' => 'sesi_armada.updated_at',
        ];
        $sortCol = $sortMap[$sortBy] ?? 'sesi_armada.updated_at';
        $query->orderBy($sortCol, strtoupper($sortOrder) === 'ASC' ? 'asc' : 'desc');
    } else {
        $query->orderByDesc('sesi_armada.updated_at');
    }

    $allArmada = $query->get()
        ->map(fn($a) => (array)$a)
        ->toArray();

    // Format muatan dengan trim decimal (2.00 -> "2", 2.75 -> "2.75")
    $allArmada = array_map(fn($a) => [...$a, 'muatan' => FormatHelper::ton($a['muatan_raw'])], $allArmada);

    // Jika tidak ada sort eksplisit, terapkan prioritas skill cabang user
    if (!$sortBy) {
        // Prioritaskan armada dengan skill yang cocok ke cabang user (SORT, bukan filter)
    $cabangCode = auth()->user()?->getCabangId();
    $cabangSkills = [];
    if ($cabangCode) {
        $cabangSkills = DB::connection('sqlsrv')->table('sesi_cabang_skill as cs')
            ->join('sesi_master_skill as ms', 'ms.nama_skill', '=', 'cs.id_skill')
            ->where('cs.cabang_code', $cabangCode)
            ->where('cs.flag', true)
            ->where('ms.flag', true)
            ->pluck('ms.nama_skill')
            ->map(fn($s) => strtoupper(trim($s)))
            ->toArray();
    }

        if (!empty($cabangSkills)) {
            usort($allArmada, function ($a, $b) use ($cabangSkills) {
                $armadaSkillsA = array_map(
                    fn($s) => strtoupper(trim($s)),
                    array_filter(explode(',', $a['skill']), fn($s) => $s !== '')
                );
                $armadaSkillsB = array_map(
                    fn($s) => strtoupper(trim($s)),
                    array_filter(explode(',', $b['skill']), fn($s) => $s !== '')
                );
                $matchA = count(array_intersect($armadaSkillsA, $cabangSkills)) > 0;
                $matchB = count(array_intersect($armadaSkillsB, $cabangSkills)) > 0;
                if ($matchA === $matchB) return 0;
                return $matchA ? -1 : 1;
            });
        }
    }

    return $limit === null ? $allArmada : array_slice($allArmada, 0, $limit);
});

$displayArmada = $result['data'];
$error = $result['error'];
@endphp

{{-- Search --}}
<input
    type="text"
    placeholder="Cari perusahaan..."
    class="mb-4 w-full rounded-lg border border-gray-300 px-4 py-2 text-sm 
    focus:border-avian-green focus:outline-none"
    @if($mode === 'pilih') x-model="searchArmada" @endif>

{{-- Tabel --}}
<div class="overflow-hidden rounded-xl border border-gray-200">
    <table class="w-full table-fixed text-sm">
        <colgroup>
            <col class="w-[12%]"> {{-- Nama --}}
            <col class="w-[6%]"> {{-- badan usaha --}}
            <col class="w-[10%]"> {{-- skill / area --}}
            <col class="w-[8%]"> {{-- jenis kendaraan --}}
            <col class="w-[8%]"> {{-- Muatan --}}
            <col class="w-[10%]"> {{-- Harga --}}
            <col class="w-[8%]"> {{-- Update_at --}}
            <col class="w-[5%]"> {{-- Action --}}
        </colgroup>

        <thead>
            <tr class="border-b border-gray-300 text-xs font-medium uppercase tracking-wide text-gray-500">
                <x-sortable-th col="nama" label="Nama Perusahaan" :sortBy="$sortBy" :sortOrder="$sortOrder" sortParam="sort_armada" orderParam="order_armada" />
                <x-sortable-th col="badan_usaha" label="Badan Usaha" :sortBy="$sortBy" :sortOrder="$sortOrder" sortParam="sort_armada" orderParam="order_armada" />
                <x-sortable-th col="skill" label="Skill / Area Pengantaran" :sortBy="$sortBy" :sortOrder="$sortOrder" sortParam="sort_armada" orderParam="order_armada" />
                <x-sortable-th col="kendaraan" label="Jenis Kendaraan" :sortBy="$sortBy" :sortOrder="$sortOrder" sortParam="sort_armada" orderParam="order_armada" />
                <x-sortable-th col="muatan" label="Maksimal Muatan" :sortBy="$sortBy" :sortOrder="$sortOrder" sortParam="sort_armada" orderParam="order_armada" />
                <th class="px-3 py-3 text-left whitespace-nowrap">Harga Sewa</th>
                <x-sortable-th col="updated" label="Diperbarui" :sortBy="$sortBy" :sortOrder="$sortOrder" sortParam="sort_armada" orderParam="order_armada" />
                <th class="px-3 py-3 text-right">Action</th>
            </tr>
        </thead>

        <tbody class="divide-y divide-gray-200 text-sm">
            @if($error)
                <tr>
                    <td colspan="7" class="py-6 px-4 text-center text-sm text-red-600">
                        ⚠️ Error: {{ $error }}
                    </td>
                </tr>
            @elseif(empty($displayArmada))
                <tr>
                    <td colspan="7" class="py-12 text-center text-sm text-gray-400">
                        Tidak ada armada yang tersedia. <br>
                        <span class="text-xs">Data armada akan muncul setelah ditambahkan.</span>
                    </td>
                </tr>
            @else
                @foreach ($displayArmada as $a)
                <tr
                    class="cursor-pointer transition hover:bg-gray-50"
                    @if($mode === 'pilih')
                        :class="armadaTerpilih?.id === {{ $a['id'] }} ? 'bg-avian-green-light' : 'hover:bg-gray-50'"
                        @click="armadaTerpilih = {{ json_encode($a) }}"
                    @else
                        @click="void 0"
                    @endif>
                    <td class="px-3 py-3 font-medium text-gray-800 whitespace-nowrap">{{ $a['nama'] }}</td>
                    <td class="px-3 py-3 text-gray-600 whitespace-nowrap">{{ $a['badan_usaha'] }}</td>
                    <td class="px-3 py-3 text-gray-600 whitespace-nowrap">{{ $a['skill'] }}</td>
                    <td class="px-3 py-3 text-gray-600 whitespace-nowrap">{{ $a['kendaraan'] }}</td>
                    <td class="px-3 py-3 text-gray-600 whitespace-nowrap">{{ $a['muatan'] }}</td>
                    <td class="px-3 py-3 text-gray-600 whitespace-nowrap">{{ $a['harga'] }}</td>
                    <td class="px-3 py-3 text-gray-400 text-xs whitespace-nowrap">{{ $a['updated'] }}</td>
                    <td class="px-3 py-3 text-right">
                        @if($mode === 'pilih')
                            <button
                                type="button"
                                @click.stop="armadaTerpilih = {{ json_encode($a) }}"
                                :class="armadaTerpilih?.id === {{ $a['id'] }}
                                    ? 'bg-avian-green text-white'
                                    : 'border border-avian-green text-avian-green hover:bg-avian-green-light'"
                                class="rounded-lg px-3 py-1.5 text-xs font-medium transition whitespace-nowrap">
                                <span x-show="armadaTerpilih?.id !== {{ $a['id'] }}">Pilih</span>
                                <span x-show="armadaTerpilih?.id === {{ $a['id'] }}">✓ Dipilih</span>
                            </button>
                        @elseif($mode === 'detail')
                            <a href="{{ route('armada.show', $a['id']) }}"
                                class="rounded-lg border border-avian-green px-3 py-1.5 text-xs font-medium text-avian-green hover:bg-avian-green-light transition">
                                Detail
                            </a>
                        @else
                            <a href="{{ route('pengajuan.kg', ['id' => $a['id'], 'nama' => $a['nama'], 'kendaraan' => $a['kendaraan'], 'muatan' => $a['muatan'], 'muatan_raw' => $a['muatan_raw'], 'harga' => $a['harga'], 'skill' => $a['skill']]) }}"
                                class="rounded-lg bg-avian-green px-3 py-1.5 text-xs font-medium text-white hover:bg-avian-green-dark transition">
                                Pilih
                            </a>
                        @endif
                    </td>
                </tr>
                @endforeach
            @endif
        </tbody>
    </table>
</div>
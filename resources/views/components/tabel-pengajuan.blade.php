@props([
    'mode' => 'dashboard',
    'limit' => 5,
])

@php
use App\Helpers\DbHelper;

// Get query params
$sortBy = request('sort_pengajuan', 'submitted_at');
$sortOrder = request('order_pengajuan', 'desc');

$result = DbHelper::safeQuery(function () use ($limit, $sortBy, $sortOrder) {
    $query = DB::connection('sqlsrv')->table('sesi_pengajuan_sewa')
        ->join('sesi_armada', 'sesi_pengajuan_sewa.id_armada', '=', 'sesi_armada.id_armada')
        ->join('sesi_perusahaan_ekspedisi', 'sesi_armada.id_perusahaan', '=', 'sesi_perusahaan_ekspedisi.id_perusahaan')
        ->select(
            'sesi_pengajuan_sewa.id_pengajuan_sewa as id',
            'sesi_perusahaan_ekspedisi.badan_usaha as badan_usaha',
            'sesi_perusahaan_ekspedisi.nama_perusahaan as nama',
            'sesi_pengajuan_sewa.tanggal_pengiriman as tanggal',
            'sesi_pengajuan_sewa.submitted_at as submitted_at',
            'sesi_pengajuan_sewa.harga_sewa as harga',
            'sesi_pengajuan_sewa.rasio_sewa as rasio',
            'sesi_pengajuan_sewa.status_pengajuan as status'
        )
        ->where('sesi_pengajuan_sewa.flag', true);

    $user = auth()->user();
    if (!$user->isGlobalAccess()) {
        $query->where('sesi_pengajuan_sewa.id_cabang', $user->getCabangId());
    }

    // Sort
    $sortMap = [
        'submitted_at' => 'sesi_pengajuan_sewa.submitted_at',
        'tanggal' => 'sesi_pengajuan_sewa.tanggal_pengiriman',
        'nama' => 'sesi_perusahaan_ekspedisi.nama_perusahaan',
        'harga' => 'sesi_pengajuan_sewa.harga_sewa',
        'rasio' => 'sesi_pengajuan_sewa.rasio_sewa',
        'status' => 'sesi_pengajuan_sewa.status_pengajuan',
    ];
    $sortCol = $sortMap[$sortBy] ?? 'sesi_pengajuan_sewa.submitted_at';
    $query->orderBy($sortCol, strtoupper($sortOrder) === 'ASC' ? 'asc' : 'desc');

    $allPengajuan = $query->get()
        ->map(fn($p) => (array) $p)
        ->toArray();

    return $limit === null ? $allPengajuan : array_slice($allPengajuan, 0, $limit);
});

$displayPengajuan = $result['data'];
$error = $result['error'];

$statusBadges = [
    'pending' => ['bg' => 'bg-blue-100', 'text' => 'text-blue-700', 'label' => 'Pending'],
    'approved' => ['bg' => 'bg-avian-green-light', 'text' => 'text-avian-green', 'label' => 'Disetujui'],
    'rejected' => ['bg' => 'bg-red-100', 'text' => 'text-red-600', 'label' => 'Ditolak'],
];
@endphp

<div>

{{-- Tabel --}}
<div class="overflow-hidden rounded-xl border border-gray-200">
    <table class="w-full table-fixed text-sm">
        <colgroup>
            <col class="w-[12%]"> {{-- Tanggal Pengajuan --}}
            <col class="w-[12%]"> {{-- Tanggal Pengiriman --}}
            <col class="w-[18%]"> {{-- Nama Perusahaan --}}
            <col class="w-[14%]"> {{-- Harga Sewa --}}
            <col class="w-[12%]"> {{-- Rasio Sewa --}}
            <col class="w-[15%]"> {{-- Status --}}
            <col class="w-[17%]"> {{-- Action --}}
        </colgroup>

        <thead>
            <tr class="border-b border-gray-300 text-xs font-medium uppercase tracking-wide text-gray-500">
                <x-sortable-th col="submitted_at" label="Tanggal Pengajuan" :sortBy="$sortBy" :sortOrder="$sortOrder" sortParam="sort_pengajuan" orderParam="order_pengajuan" />
                <x-sortable-th col="tanggal" label="Tanggal Pengiriman" :sortBy="$sortBy" :sortOrder="$sortOrder" sortParam="sort_pengajuan" orderParam="order_pengajuan" />
                <x-sortable-th col="nama" label="Nama Perusahaan" :sortBy="$sortBy" :sortOrder="$sortOrder" sortParam="sort_pengajuan" orderParam="order_pengajuan" />
                <x-sortable-th col="harga" label="Harga Sewa" :sortBy="$sortBy" :sortOrder="$sortOrder" sortParam="sort_pengajuan" orderParam="order_pengajuan" />
                <x-sortable-th col="rasio" label="Rasio Sewa" :sortBy="$sortBy" :sortOrder="$sortOrder" sortParam="sort_pengajuan" orderParam="order_pengajuan" />
                <x-sortable-th col="status" label="Status" :sortBy="$sortBy" :sortOrder="$sortOrder" sortParam="sort_pengajuan" orderParam="order_pengajuan" />
                <th class="px-4 py-3 text-right">Action</th>
            </tr>
        </thead>

        <tbody class="divide-y divide-gray-200 text-sm">
            @if($error)
                <tr>
                    <td colspan="7" class="py-6 px-4 text-center text-sm text-red-600">
                        ⚠️ Error: {{ $error }}
                    </td>
                </tr>
            @elseif(empty($displayPengajuan))
                <tr>
                    <td colspan="7" class="py-12 text-center text-sm text-gray-400">
                        Tidak ada pengajuan sewa. <br>
                        <span class="text-xs">Pengajuan akan muncul setelah dibuat.</span>
                    </td>
                </tr>
            @else
                @foreach($displayPengajuan as $p)
                @php
                    $statusKey = strtolower($p['status'] ?? '');
                    $badgeConfig = $statusBadges[$statusKey] ?? ['bg' => 'bg-gray-100', 'text' => 'text-gray-600', 'label' => $p['status']];
                @endphp
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 text-gray-600 whitespace-nowrap">
                        {{ \Carbon\Carbon::parse($p['submitted_at'])->translatedFormat('d M Y') }}
                    </td>

                    <td class="px-4 py-3 text-gray-600 whitespace-nowrap">
                        {{ \Carbon\Carbon::parse($p['tanggal'])->translatedFormat('d M Y') }}
                    </td>
                    <td class="px-4 py-3 text-gray-800 whitespace-nowrap">{{ $p['badan_usaha'] . ' ' . $p['nama'] }}</td>
                    <td class="px-4 py-3 text-gray-600 whitespace-nowrap">
                        Rp {{ number_format($p['harga'], 0, ',', '.') }}
                    </td>
                    <td class="px-4 py-3 text-gray-600 whitespace-nowrap">
                        {{ number_format($p['rasio'], 2, ',', '.') }}%
                    </td>
                    <td class="px-4 py-3">
                        <span class="rounded-full px-2 py-0.5 text-xs font-medium {{ $badgeConfig['bg'] }} {{ $badgeConfig['text'] }}">
                            {{ $badgeConfig['label'] }}
                        </span>
                    </td>
                    <td class="px-4 py-3 text-right">
                        <a href="{{ route('pengajuan.show', $p['id']) }}"
                            class="rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-600 hover:bg-gray-50">
                            Detail
                        </a>
                    </td>
                </tr>
                @endforeach
            @endif
        </tbody>
    </table>
</div>

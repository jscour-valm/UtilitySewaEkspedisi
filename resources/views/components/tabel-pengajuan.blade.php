@props([
    'mode' => 'dashboard',
    'limit' => 5,
])

@php
use App\Helpers\DbHelper;
use App\Models\RasioSewa;

// Get ambang rasio sewa dari database
$rasioSewaSetting = RasioSewa::aktif();
$ambangRasio = $rasioSewaSetting ? $rasioSewaSetting->persentase_maksimal : 2.5;

// Get query params
$sortBy = request('sort_pengajuan', 'submitted_at');
$sortOrder = request('order_pengajuan', 'desc');

$result = DbHelper::safeQuery(function () use ($limit, $sortBy, $sortOrder, $mode) {
    // leftJoin + tanpa filter flag di tabel lookup: vendor/kendaraan yang sudah
    // soft-deleted tidak boleh menyembunyikan pengajuan yang masih flag=1.
    $query = DB::connection('sqlsrv')->table('sesi_pengajuan_sewa')
        ->leftJoin('sesi_unit_kendaraan', 'sesi_pengajuan_sewa.id_kendaraan', '=', 'sesi_unit_kendaraan.id_kendaraan')
        ->leftJoin('sesi_perusahaan_ekspedisi', 'sesi_unit_kendaraan.id_perusahaan', '=', 'sesi_perusahaan_ekspedisi.id_perusahaan');

    // Tambah join ke lntrn_users kalau mode=wm (untuk nama pengaju)
    if ($mode === 'wm') {
        $query->leftJoin('lntrn_users', 'sesi_pengajuan_sewa.submitted_by', '=', 'lntrn_users.id');
    }

    $query->select(
        'sesi_pengajuan_sewa.id_pengajuan_sewa as id',
        'sesi_perusahaan_ekspedisi.badan_usaha as badan_usaha',
        'sesi_perusahaan_ekspedisi.nama_perusahaan as nama',
        'sesi_pengajuan_sewa.tanggal_pengiriman as tanggal',
        'sesi_pengajuan_sewa.submitted_at as submitted_at',
        'sesi_pengajuan_sewa.harga_sewa as harga',
        'sesi_pengajuan_sewa.rasio_sewa as rasio',
        'sesi_pengajuan_sewa.status_pengajuan as status',
        'sesi_pengajuan_sewa.id_cabang as cabang'
    );

    // Kalau mode=wm, tambah nama pengaju
    if ($mode === 'wm') {
        $query->addSelect('lntrn_users.name as pengaju_name');
    }

    $query->where('sesi_pengajuan_sewa.flag', true);

    $user = auth()->user();
    if (!$user->isGlobalAccess()) {
        $query->whereIn('sesi_pengajuan_sewa.id_cabang', $user->getCabangIds());
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

    return ($limit === null || (int) $limit === 0) ? $allPengajuan : array_slice($allPengajuan, 0, $limit);
});

$displayPengajuan = $result['data'];
$error = $result['error'];

$statusBadges = [
    'pending' => ['bg' => 'bg-blue-100', 'text' => 'text-blue-700', 'label' => 'Pending'],
    'approved' => ['bg' => 'bg-avian-green-light', 'text' => 'text-avian-green', 'label' => 'Disetujui'],
    'rejected' => ['bg' => 'bg-red-100', 'text' => 'text-red-600', 'label' => 'Ditolak'],
];

// Role-aware action button
$userRole = auth()->user()->userUtility?->role;
$isReviewer = $userRole === 'WM';
@endphp

{{-- Search Box --}}
<input
    type="text"
    id="pengajuanSearch"
    placeholder="{{ $mode === 'wm' ? 'Cari nama pengaju atau perusahaan...' : 'Cari nama perusahaan...' }}"
    class="mb-4 w-full rounded-lg border border-gray-300 px-4 py-2 text-sm
    focus:border-avian-green focus:outline-none">

{{-- Tabel --}}
<div class="overflow-hidden rounded-xl border border-gray-200">
    <table class="w-full table-fixed text-sm">
        <colgroup>
            @if($mode === 'wm')
                <col style="width: 20%;"> {{-- KaGud --}}
                <col style="width: 26%;"> {{-- Nama Perusahaan --}}
                <col style="width: 18%;"> {{-- Harga Sewa --}}
                <col style="width: 18%;"> {{-- Rasio Sewa --}}
                <col style="width: 11%;"> {{-- Status --}}
                <col style="width: 7%;"> {{-- Action --}}
            @else
                <col style="width: 16%;"> {{-- Tanggal Pengajuan --}}
                <col style="width: 16%;"> {{-- Tanggal Pengiriman --}}
                <col style="width: 20%;"> {{-- Nama Perusahaan --}}
                <col style="width: 14%;"> {{-- Harga Sewa --}}
                <col style="width: 12%;"> {{-- Rasio Sewa --}}
                <col style="width: 10%;"> {{-- Status --}}
                <col style="width: 7%;"> {{-- Action --}}
            @endif
        </colgroup>

        <thead>
            <tr class="border-b border-gray-300 text-xs font-medium uppercase tracking-wide text-gray-500">
                @if($mode === 'wm')
                    <th class="px-4 py-3 text-left">KaGud</th>
                    <th class="px-4 py-3 text-left">Nama Perusahaan</th>
                    <x-sortable-th col="harga" label="Harga Sewa" :sortBy="$sortBy" :sortOrder="$sortOrder" sortParam="sort_pengajuan" orderParam="order_pengajuan" />
                    <x-sortable-th col="rasio" label="Rasio Sewa" :sortBy="$sortBy" :sortOrder="$sortOrder" sortParam="sort_pengajuan" orderParam="order_pengajuan" />
                    <th class="px-4 py-3 text-left">Status</th>
                    <th class="px-4 py-3 text-right">Action</th>
                @else
                    <x-sortable-th col="submitted_at" label="Tanggal Pengajuan" :sortBy="$sortBy" :sortOrder="$sortOrder" sortParam="sort_pengajuan" orderParam="order_pengajuan" />
                    <x-sortable-th col="tanggal" label="Tanggal Pengiriman" :sortBy="$sortBy" :sortOrder="$sortOrder" sortParam="sort_pengajuan" orderParam="order_pengajuan" />
                    <x-sortable-th col="nama" label="Nama Perusahaan" :sortBy="$sortBy" :sortOrder="$sortOrder" sortParam="sort_pengajuan" orderParam="order_pengajuan" />
                    <x-sortable-th col="harga" label="Harga Sewa" :sortBy="$sortBy" :sortOrder="$sortOrder" sortParam="sort_pengajuan" orderParam="order_pengajuan" />
                    <x-sortable-th col="rasio" label="Rasio Sewa" :sortBy="$sortBy" :sortOrder="$sortOrder" sortParam="sort_pengajuan" orderParam="order_pengajuan" />
                    <x-sortable-th col="status" label="Status" :sortBy="$sortBy" :sortOrder="$sortOrder" sortParam="sort_pengajuan" orderParam="order_pengajuan" />
                    <th class="px-4 py-3 text-right">Action</th>
                @endif
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
                <tr class="hover:bg-gray-50 pengajuan-row"
                    @if($mode === 'wm')
                        data-kagud="{{ strtolower($p['pengaju_name'] ?? '') }}"
                    @endif
                    data-perusahaan="{{ strtolower(trim(($p['badan_usaha'] ?? '') . ' ' . ($p['nama'] ?? ''))) }}"
                    data-status="{{ $statusKey }}">

                    @if($mode === 'wm')
                        {{-- Mode WM: KaGud + Cabang --}}
                        <td class="px-4 py-3 font-medium text-gray-800">
                            <span>{{ $p['pengaju_name'] ?? 'Unknown' }}</span>
                            <span class="block text-xs font-normal text-gray-400">Cab. {{ $p['cabang'] }}</span>
                        </td>
                    @else
                        {{-- Mode Default: Tanggal Pengajuan --}}
                        <td class="px-4 py-3 text-gray-600 whitespace-nowrap">
                            {{ \Carbon\Carbon::parse($p['submitted_at'])->translatedFormat('d M Y') }}
                        </td>

                        {{-- Tanggal Pengiriman --}}
                        <td class="px-4 py-3 text-gray-600 whitespace-nowrap">
                            {{ \Carbon\Carbon::parse($p['tanggal'])->translatedFormat('d M Y') }}
                        </td>
                    @endif

                    {{-- Nama Perusahaan --}}
                    <td class="px-4 py-3 text-gray-800 whitespace-nowrap">{{ trim(($p['badan_usaha'] ?? '') . ' ' . ($p['nama'] ?? '')) ?: '—' }}</td>

                    {{-- Harga Sewa --}}
                    <td class="px-4 py-3 text-gray-600 whitespace-nowrap">
                        Rp {{ number_format($p['harga'], 0, ',', '.') }}
                    </td>

                    {{-- Rasio Sewa --}}
                    <td class="px-4 py-3 whitespace-nowrap">
                        @if($mode === 'wm')
                            {{-- Mode WM: styling merah + threshold (dari DB) --}}
                            <span @class([
                                'font-semibold text-red-600' => $p['rasio'] > $ambangRasio,
                                'font-medium text-gray-800' => $p['rasio'] <= $ambangRasio,
                            ])>
                                {{ number_format($p['rasio'], 2, ',', '.') }}%
                            </span>
                            <span class="ml-1 text-xs text-gray-400">/ {{ number_format($ambangRasio, 2, ',', '.') }}%</span>
                        @else
                            {{-- Mode Default: plain --}}
                            {{ number_format($p['rasio'], 2, ',', '.') }}%
                        @endif
                    </td>

                    {{-- Status --}}
                    <td class="px-4 py-3">
                        <span class="rounded-full px-2 py-0.5 text-xs font-medium {{ $badgeConfig['bg'] }} {{ $badgeConfig['text'] }}">
                            {{ $badgeConfig['label'] }}
                        </span>
                    </td>

                    {{-- Action --}}
                    <td class="px-4 py-3 text-right">
                        @if($isReviewer)
                            <a href="{{ route('approval.show', $p['id']) }}"
                                class="rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-600 hover:bg-gray-50">
                                Review
                            </a>
                        @else
                            <a href="{{ route('pengajuan.show', $p['id']) }}"
                                class="rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-600 hover:bg-gray-50">
                                Detail
                            </a>
                        @endif
                    </td>
                </tr>
                @endforeach
            @endif
        </tbody>
    </table>
</div>

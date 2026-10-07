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
        ->leftJoin('sesi_perusahaan_ekspedisi', DB::raw('COALESCE(sesi_unit_kendaraan.id_perusahaan, sesi_pengajuan_sewa.id_perusahaan_ekspedisi)'), '=', 'sesi_perusahaan_ekspedisi.id_perusahaan');

    // Tambah join ke lntrn_users kalau mode=wm (untuk nama pengaju)
    if (in_array($mode, ['wm', 'ka'])) {
        $query->leftJoin('lntrn_users', 'sesi_pengajuan_sewa.submitted_by', '=', 'lntrn_users.id');
    }

    $query->select(
        'sesi_pengajuan_sewa.alur_approval as alur_approval',
        'sesi_pengajuan_sewa.id_pengajuan_sewa as id',
        'sesi_perusahaan_ekspedisi.badan_usaha as badan_usaha',
        'sesi_perusahaan_ekspedisi.nama_perusahaan as nama',
        'sesi_pengajuan_sewa.tanggal_pengiriman as tanggal',
        'sesi_pengajuan_sewa.submitted_at as submitted_at',
        'sesi_pengajuan_sewa.created_at as created_at',
        'sesi_pengajuan_sewa.harga_sewa as harga',
        'sesi_pengajuan_sewa.rasio_sewa as rasio',
        'sesi_pengajuan_sewa.jenis_pengajuan as jenis_pengajuan',
        'sesi_pengajuan_sewa.tujuan_penyewaan as tujuan_penyewaan',
        'sesi_pengajuan_sewa.status_pengajuan as status',
        'sesi_pengajuan_sewa.id_cabang as cabang',
        'sesi_pengajuan_sewa.kategori_approval as kategori_approval'
    );

    // Kalau mode=wm, tambah nama pengaju
    if (in_array($mode, ['wm', 'ka'])) {
        $query->addSelect('lntrn_users.name as pengaju_name');
    }

    // Mode WH/WC: cuma pengajuan yang alur_approval-nya memuat peran ini.
    // Pengajuan lama tanpa alur_approval diturunkan dari kategori (over_threshold = WM,WH).
    if (in_array($mode, ['wh', 'wc'])) {
        $peranMode = strtoupper($mode);
        $query->where(function ($q) use ($peranMode) {
            $q->where('sesi_pengajuan_sewa.alur_approval', 'like', "%{$peranMode}%");
            if ($peranMode === 'WH') {
                $q->orWhere(fn ($qq) => $qq->whereNull('sesi_pengajuan_sewa.alur_approval')
                    ->where('sesi_pengajuan_sewa.kategori_approval', 'over_threshold'));
            }
        });
    }

    // Mode KA (KaAdmin): view-only, cuma pengajuan yang sudah approved
    if ($mode === 'ka') {
        $query->where('sesi_pengajuan_sewa.status_pengajuan', 'approved');
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

    $rows = $query->get();

    // "Giliran" = peran berikutnya di alur_approval (logika sama dgn PengajuanSewa::approverBerikutnya()).
    // Log approved dihitung hanya yang setelah submitted_at (edit = mulai ulang dari WM).
    $logApproved = $rows->isEmpty() ? collect() : DB::connection('sqlsrv')->table('sesi_approval_log as al')
        ->leftJoin('sesi_approval as ar', 'ar.id_approval_rule', '=', 'al.id_approval_rule')
        ->whereIn('al.id_pengajuan_sewa', $rows->pluck('id'))
        ->where('al.flag', true)
        ->where('al.status', 'approved')
        ->get(['al.id_pengajuan_sewa', 'al.decided_at', DB::raw('COALESCE(al.role_approver, ar.role_berwenang) as peran')])
        ->groupBy('id_pengajuan_sewa');

    $rentang = \App\Helpers\RentangTanggalDashboard::dariRequest();
    $role = auth()->user()->userUtility?->role;

    $allPengajuan = $rows->map(function ($p) use ($logApproved) {
        $sudah = $logApproved->get($p->id, collect())
            ->filter(fn ($l) => !$p->submitted_at || $l->decided_at >= $p->submitted_at)
            ->pluck('peran')->filter()->unique()->values()->all();
        $alur = \App\Models\PengajuanSewa::parseAlur($p->alur_approval, $p->kategori_approval);
        $p->alur = $alur;
        $p->giliran = \App\Models\PengajuanSewa::approverBerikutnyaDari($p->status, $alur, $sudah);
        return (array) $p;
    })
        ->filter(fn ($p) => $rentang->tampil($role, $p['submitted_at'] ?? $p['created_at'], $p['status'], $p['giliran']))
        ->values()
        ->toArray();

    return ($limit === null || (int) $limit === 0) ? $allPengajuan : array_slice($allPengajuan, 0, $limit);
});

$displayPengajuan = $result['data'];
$error = $result['error'];

$statusBadges = [
    'pending' => ['bg' => 'bg-blue-100', 'text' => 'text-blue-700', 'label' => 'Pending'],
    'approved' => ['bg' => 'bg-avian-green-light', 'text' => 'text-avian-green', 'label' => 'Disetujui'],
    'rejected' => ['bg' => 'bg-red-100', 'text' => 'text-red-600', 'label' => 'Ditolak'],
    'cancelled' => ['bg' => 'bg-gray-100', 'text' => 'text-gray-600', 'label' => 'Dibatalkan'],
];

// Role-aware action button
$userRole = auth()->user()->userUtility?->role;
$isReviewer = in_array($userRole, ['WM', 'WC', 'WH']);
$isModeApprover = in_array($mode, ['wh', 'wc']);

$searchPlaceholder = match ($mode) {
    'wm', 'ka' => 'Cari nama pengaju atau perusahaan...',
    'wh', 'wc' => 'Cari nama perusahaan atau cabang...',
    default => 'Cari nama perusahaan...',
};
@endphp

{{-- Search Box --}}
<input
    type="text"
    id="pengajuanSearch"
    placeholder="{{ $searchPlaceholder }}"
    class="mb-4 w-full rounded-lg border border-gray-300 px-4 py-2 text-sm
    focus:border-avian-green focus:outline-none">

{{-- Tabel --}}
<div class="overflow-hidden rounded-xl border border-gray-200">
    <table class="w-full table-fixed text-sm">
        <colgroup>
            @if(in_array($mode, ['wm', 'ka']))
                <col style="width: 18%;"> {{-- KaGud --}}
                <col style="width: 18%;"> {{-- Nama Perusahaan --}}
                <col style="width: 18%;"> {{-- Harga Sewa --}}
                <col style="width: 18%;"> {{-- Rasio Sewa --}}
                <col style="width: 21%;"> {{-- Status --}}
                <col style="width: 7%;"> {{-- Action --}}
            @elseif($isModeApprover)
                <col style="width: 16%;"> {{-- Cabang --}}
                <col style="width: 30%;"> {{-- Nama Perusahaan --}}
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
                @if(in_array($mode, ['wm', 'ka']))
                    <th class="px-4 py-3 text-left">KaGud</th>
                    <th class="px-4 py-3 text-left">Nama Perusahaan</th>
                    <x-sortable-th col="harga" label="Harga Sewa" :sortBy="$sortBy" :sortOrder="$sortOrder" sortParam="sort_pengajuan" orderParam="order_pengajuan" />
                    <x-sortable-th col="rasio" label="Rasio Sewa" :sortBy="$sortBy" :sortOrder="$sortOrder" sortParam="sort_pengajuan" orderParam="order_pengajuan" />
                    <th class="px-4 py-3 text-left">Status</th>
                    <th class="px-4 py-3 text-right">Action</th>
                @elseif($isModeApprover)
                    <th class="px-4 py-3 text-left">Cabang</th>
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
                    if ($statusKey === 'pending' && !empty($p['giliran'])) {
                        $badgeConfig['label'] = $p['giliran'] === 'WM' ? 'Menunggu Validasi WM' : 'Menunggu Approval ' . $p['giliran'];
                    }
                @endphp
                <tr class="hover:bg-gray-50 pengajuan-row"
                    @if(in_array($mode, ['wm', 'ka']))
                        data-kagud="{{ strtolower($p['pengaju_name'] ?? '') }}"
                    @endif
                    @if($isModeApprover)
                        data-cabang="{{ strtolower($p['cabang'] ?? '') }}"
                    @endif
                    data-giliran-saya="{{ (int) (($p['giliran'] ?? null) === $userRole) }}"
                    data-perusahaan="{{ strtolower(trim(($p['badan_usaha'] ?? '') . ' ' . ($p['nama'] ?? ''))) }}"
                    data-status="{{ $statusKey }}"
                    data-kategori="{{ strtolower($p['kategori_approval'] ?? '') }}">

                    @if(in_array($mode, ['wm', 'ka']))
                        {{-- Mode WM: KaGud + Cabang --}}
                        <td class="px-4 py-3 font-medium text-gray-800">
                            <span>{{ $p['pengaju_name'] ?? 'Unknown' }}</span>
                            <span class="block text-xs font-normal text-gray-400">Cab. {{ $p['cabang'] }}</span>
                        </td>
                    @elseif($isModeApprover)
                        {{-- Mode WH/WC: Cabang (lintas cabang, global access) --}}
                        <td class="px-4 py-3 font-medium text-gray-800">
                            Cab. {{ $p['cabang'] }}
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
                        {{-- Kiriman Rutin tujuan Toko tidak memakai rasio --}}
                        @if(! \App\Models\PengajuanSewa::pakaiRasioUntuk($p['jenis_pengajuan'], $p['tujuan_penyewaan']))
                        @elseif(in_array($mode, ['wm', 'wh', 'wc', 'ka']))
                            {{-- Mode WM/WH/WC: styling merah + threshold (dari DB) --}}
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

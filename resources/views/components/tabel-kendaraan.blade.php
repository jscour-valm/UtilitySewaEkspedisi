{{--
    Catatan soal prop "limit": JANGAN pakai `:limit="null"` buat maksud "tanpa
    limit" — Blade `@props` diam-diam JATUH KE DEFAULT kalau value yang di-bind
    persis `null` (dianggap "nggak di-pass" walau attribute-nya eksplisit
    ada), jadi `:limit="null"` sebelumnya kebaca sebagai limit=5 tetap
    (baru ketauan pas halaman Kendaraan cuma nampilin 5 dari 322 perusahaan
    padahal maksudnya semua). Pakai `:no-limit="true"` buat "tampilkan semua".
--}}
@props([
    'mode' => 'pilih',
    'limit' => 5,
    'noLimit' => false,
])

@php
use App\Helpers\DbHelper;
use App\Helpers\FormatHelper;
use App\Models\TarifKirimanRutin;

// Get sort params dari query string
$sortBy = request('sort_kendaraan', null);
$sortOrder = request('order_kendaraan', 'asc');

$result = DbHelper::safeQuery(function () use ($limit, $noLimit, $sortBy, $sortOrder, $mode) {
    // leftJoin ke sesi_perusahaan_ekspedisi (bukan inner join) supaya kendaraan
    // tanpa vendor (id_perusahaan null) tetap ikut. Tapi kalau vendor-nya ADA
    // dan flag=0 (soft-deleted), kendaraan itu tetap harus disembunyikan dari
    // daftar - makanya where flag di bawah izinkan NULL (leftJoin miss) lolos,
    // tapi flag=0 (vendor match tapi soft-deleted) tetap ke-filter.
    $query = DB::connection('sqlsrv')->table('sesi_unit_kendaraan')
        ->leftJoin('sesi_perusahaan_ekspedisi', 'sesi_unit_kendaraan.id_perusahaan', '=', 'sesi_perusahaan_ekspedisi.id_perusahaan')
        ->select(
            'sesi_unit_kendaraan.id_kendaraan as id',
            'sesi_unit_kendaraan.id_perusahaan as id_perusahaan',
            'sesi_perusahaan_ekspedisi.badan_usaha as badan_usaha',
            'sesi_perusahaan_ekspedisi.nama_perusahaan as nama',
            'sesi_unit_kendaraan.id_skill as skill',
            'sesi_unit_kendaraan.jenis_kendaraan as kendaraan',
            'sesi_unit_kendaraan.muatan_maksimal as muatan_raw',
            'sesi_unit_kendaraan.id_cabang as id_cabang',
            DB::raw("(SELECT TOP 1 p.harga_sewa FROM sesi_pengajuan_sewa p WHERE p.id_kendaraan = sesi_unit_kendaraan.id_kendaraan AND p.status_pengajuan = 'approved' AND p.flag = 1 ORDER BY p.submitted_at DESC) as harga_sewa_raw"),
            DB::raw("FORMAT(sesi_unit_kendaraan.updated_at, 'dd MMM yyyy') as updated")
        )
        ->where('sesi_unit_kendaraan.flag', true)
        ->where(function ($q) {
            $q->whereNull('sesi_perusahaan_ekspedisi.id_perusahaan')
              ->orWhere('sesi_perusahaan_ekspedisi.flag', true);
        });

    // Scope ke cabang user (WH/DCI global access lihat semua)
    $user = auth()->user();
    if ($user && !$user->isGlobalAccess()) {
        $cabangIds = $user->getCabangIds() ?: array_values(array_filter([$user->getCabangId()]));
        $query->whereIn('sesi_unit_kendaraan.id_cabang', $cabangIds ?: ['__none__']);
    }
    // Kendaraan tanpa plat_nomor_truk (rate-card placeholder, belum ditempel plat
    // fisik) tetap ditampilkan di semua mode - plat dianggap info opsional, bukan
    // syarat tampil (lihat Batch Fix 4).

    $query->orderByDesc('sesi_unit_kendaraan.updated_at');

    $allKendaraan = $query->get()
        ->map(fn($a) => (array)$a)
        ->toArray();

    // Format muatan dan harga
    $allKendaraan = array_map(function($a) {
        return [
            ...$a,
            'muatan' => FormatHelper::ton($a['muatan_raw']),
            'harga'  => $a['harga_sewa_raw'] ? 'Rp ' . number_format($a['harga_sewa_raw'], 0, ',', '.') : 'Rp 0',
        ];
    }, $allKendaraan);

    // Prioritaskan kendaraan dengan skill yang cocok ke cabang user (SORT, bukan filter)
    $cabangCode = auth()->user()?->getCabangId();
    $cabangSkills = [];
    if ($cabangCode) {
        $cabangSkills = DB::connection('sqlsrv')->table('sesi_cabang_skill as cs')
            ->join('sesi_master_skill as ms', 'ms.id_skill', '=', 'cs.id_skill')
            ->where('cs.cabang_code', $cabangCode)
            ->where('cs.flag', true)
            ->where('ms.flag', true)
            ->pluck('ms.id_skill')
            ->map(fn($s) => strtoupper(trim($s)))
            ->toArray();
    }

    if (!$sortBy && !empty($cabangSkills)) {
        usort($allKendaraan, function ($a, $b) use ($cabangSkills) {
            $kendaraanSkillsA = array_map(
                fn($s) => strtoupper(trim($s)),
                array_filter(explode(',', $a['skill']), fn($s) => $s !== '')
            );
            $kendaraanSkillsB = array_map(
                fn($s) => strtoupper(trim($s)),
                array_filter(explode(',', $b['skill']), fn($s) => $s !== '')
            );
            $matchA = count(array_intersect($kendaraanSkillsA, $cabangSkills)) > 0;
            $matchB = count(array_intersect($kendaraanSkillsB, $cabangSkills)) > 0;
            if ($matchA === $matchB) return 0;
            return $matchA ? -1 : 1;
        });
    }

    // 'skill' TETAP raw numeric CSV (dipakai apa adanya di atas buat sort/matching
    // krn cabangSkills juga numeric, dan dikirim ke query param ?skill= yg dibaca
    // app.js — HARUS numeric biar konsisten sama endpoint edit()). 'skill_label'
    // (nama, buat ditampilkan/dicari) ditambah terpisah.
    $allKendaraan = array_map(function ($a) {
        return [
            ...$a,
            'skill_label' => implode(', ', FormatHelper::skillNames($a['skill'])),
        ];
    }, $allKendaraan);

    // Badge "area cabang lain": skill/area kadang namanya kebetulan sama kayak nama kota
    // cabang LAIN (mis. "MEULABOH" = nama kota cabang 01C), padahal itu cuma area TUJUAN
    // pengantaran vendor cabang 01A - bukan berarti datanya nyasar. Tandai biar gak
    // disalahartikan (lihat Batch Fix 12).
    $cabangMaster = DB::connection('sqlsrv')->table('sesi_master_cabang')
        ->get(['Code', 'Name'])
        ->filter(fn ($c) => !empty($c->Name));
    $allKendaraan = array_map(function ($a) use ($cabangMaster) {
        $areaLain = null;
        if ($a['skill_label'] !== '') {
            foreach ($cabangMaster as $c) {
                if ($c->Code === $a['id_cabang']) continue; // skip nama kota cabang sendiri
                if (stripos($a['skill_label'], $c->Name) !== false) {
                    $areaLain = "{$c->Name} ({$c->Code})";
                    break;
                }
            }
        }
        return [...$a, 'area_lain' => $areaLain];
    }, $allKendaraan);

    // Ambil tarif kiriman rutin (kalau ada) buat tiap baris kendaraan/rate-card yang
    // ke-load. PENTING: sesi_tarif_kiriman_rutin NGGAK punya kolom id_kendaraan —
    // tarif itu nempel ke id_vendor_skill (kombinasi perusahaan+cabang+skill di
    // sesi_perusahaan_skill), bukan ke unit kendaraan fisik langsung (bug lama:
    // dulu query whereIn('id_kendaraan', ...) ke tabel ini, selalu error kalau
    // ada data kendaraan asli — nggak ketauan karena dev DB kosong). Jadi perlu
    // cari dulu baris sesi_perusahaan_skill yang cocok (perusahaan+cabang+salah
    // satu skill kendaraan itu), baru ambil tarifnya dari situ.
    $vendorSkills = DB::connection('sqlsrv')->table('sesi_perusahaan_skill')
        ->where('flag', true)
        ->whereIn('id_perusahaan', collect($allKendaraan)->pluck('id_perusahaan')->filter()->unique()->values())
        ->get(['id_vendor_skill', 'id_perusahaan', 'cabang_code', 'id_skill']);

    $tarifByVendorSkill = $vendorSkills->isEmpty() ? collect() : TarifKirimanRutin::with('jenisBarang')
        ->whereIn('id_vendor_skill', $vendorSkills->pluck('id_vendor_skill'))
        ->where('flag', true)
        ->get()
        ->groupBy('id_vendor_skill');

    // Group jadi 1 baris per perusahaan. Tiap item = 1 baris sesi_unit_kendaraan asli
    // (kendaraan fisik ATAU rate card), field lengkapnya dipertahankan supaya leaf-pick
    // di panel expand tetap jalan persis kayak row-click yang lama (kendaraanTerpilih dsb).
    $grouped = collect($allKendaraan)
        ->map(function ($a) use ($vendorSkills, $tarifByVendorSkill) {
            $kendaraanSkillIds = array_values(array_filter(explode(',', (string) $a['skill']), fn ($s) => $s !== '' && is_numeric($s)));
            $matchingVendorSkills = $vendorSkills->filter(function ($vs) use ($a, $kendaraanSkillIds) {
                return $vs->id_perusahaan == $a['id_perusahaan']
                    && $vs->cabang_code === $a['id_cabang']
                    && in_array((string) $vs->id_skill, $kendaraanSkillIds, true);
            });
            $tarifBreakdown = $matchingVendorSkills
                ->flatMap(fn ($vs) => $tarifByVendorSkill->get($vs->id_vendor_skill, collect()))
                ->unique('id_jenis_barang')
                ->map(fn ($t) => [
                    'nama_barang' => $t->jenisBarang->nama_barang ?? '—',
                    'harga' => (float) $t->biaya_per_unit,
                ])
                ->values()->all();
            return [
                ...$a,
                'tarif_breakdown' => $tarifBreakdown,
            ];
        })
        ->groupBy('nama')
        ->map(function ($items, $nama) {
            $skillLabels = $items->pluck('skill_label')
                ->flatMap(fn ($s) => explode(', ', $s))
                ->filter()
                ->unique()
                ->values();
            $jenisKendaraanList = $items->pluck('kendaraan')
                ->filter()
                ->unique()
                ->values();
            $cabangLabel = $items->pluck('id_cabang')
                ->filter()
                ->unique()
                ->values();
            return [
                'nama' => $nama,
                'badan_usaha' => $items->first()['badan_usaha'] ?? null,
                'skill_label' => $skillLabels->implode(', '),
                'kendaraan_label' => $jenisKendaraanList->implode(', '),
                'cabang_label' => $cabangLabel->implode(', '),
                'updated' => $items->pluck('updated')->sort()->last(),
                'items' => $items->values()->all(),
            ];
        })
        ->values();

    // Sort di level grup (nama perusahaan tanpa sort eksplisit = urutan hasil query di atas)
    if ($sortBy) {
        $sortKeyMap = ['nama' => 'nama', 'badan_usaha' => 'badan_usaha', 'cabang' => 'cabang_label', 'skill' => 'skill_label', 'updated' => 'updated'];
        $sortKey = $sortKeyMap[$sortBy] ?? null;
        if ($sortKey) {
            $grouped = strtoupper($sortOrder) === 'DESC'
                ? $grouped->sortByDesc($sortKey)->values()
                : $grouped->sortBy($sortKey)->values();
        }
    }

    // Perusahaan TANPA kendaraan sama sekali otomatis nggak pernah muncul di atas
    // (query-nya di-drive dari sesi_unit_kendaraan, di-groupBy nama) — tambahkan
    // di sini sebagai grup kosong (placeholder) biar halaman ini nggak keliatan
    // kosong total pas belum ada kendaraan yang ke-input sama sekali. Catatan:
    // perusahaan itu entity GLOBAL (nggak ada kolom cabang di
    // sesi_perusahaan_ekspedisi), jadi placeholder ini nggak ke-scope cabang
    // kayak baris kendaraan di atas — muncul ke semua role/cabang.
    $namaSudahAda = collect($grouped)->pluck('nama')->filter()->map(fn ($n) => mb_strtolower($n))->all();
    $semuaPerusahaan = DB::connection('sqlsrv')->table('sesi_perusahaan_ekspedisi')
        ->where('flag', true)
        ->get(['nama_perusahaan', 'badan_usaha']);
    foreach ($semuaPerusahaan as $p) {
        if ($p->nama_perusahaan === null || in_array(mb_strtolower($p->nama_perusahaan), $namaSudahAda, true)) {
            continue;
        }
        $grouped->push([
            'nama' => $p->nama_perusahaan,
            'badan_usaha' => $p->badan_usaha,
            'skill_label' => '',
            'kendaraan_label' => '',
            'cabang_label' => '',
            'updated' => null,
            'items' => [],
        ]);
    }

    $grouped = $grouped->values()->all();

    return $noLimit ? $grouped : array_slice($grouped, 0, $limit);
});

$displayKendaraan = $result['data'];
$error = $result['error'];
@endphp

{{-- Search --}}
<input
    type="text"
    @if($mode === 'pilih') id="searchKendaraanPilih" @else id="searchKendaraan" @endif
    placeholder="Cari nama, badan usaha, jenis kendaraan, atau skill..."
    class="mb-4 w-full rounded-lg border border-gray-300 px-4 py-2 text-sm
    focus:border-avian-green focus:outline-none"
    @if($mode === 'pilih') x-model="searchKendaraan" @endif>

{{-- Tabel --}}
<div class="overflow-x-auto rounded-xl border border-gray-200">
    <table class="w-full min-w-[720px] table-fixed text-sm">
        <colgroup>
            <col style="width: 20%;"> {{-- Nama --}}
            <col style="width: 14%;"> {{-- badan usaha --}}
            <col style="width: 14%;"> {{-- cabang --}}
            <col style="width: 28%;"> {{-- skill / area --}}
            <col style="width: 12%;"> {{-- Update_at --}}
            <col style="width: 12%;"> {{-- Action --}}
        </colgroup>

        <thead>
            <tr class="border-b border-gray-300 text-xs font-medium uppercase tracking-wide text-gray-500">
                <x-sortable-th col="nama" label="Nama Perusahaan" :sortBy="$sortBy" :sortOrder="$sortOrder" sortParam="sort_kendaraan" orderParam="order_kendaraan" />
                <x-sortable-th col="badan_usaha" label="Badan Usaha" :sortBy="$sortBy" :sortOrder="$sortOrder" sortParam="sort_kendaraan" orderParam="order_kendaraan" />
                <x-sortable-th col="cabang" label="Cabang" :sortBy="$sortBy" :sortOrder="$sortOrder" sortParam="sort_kendaraan" orderParam="order_kendaraan" />
                <x-sortable-th col="skill" label="Skill / Area Pengantaran" :sortBy="$sortBy" :sortOrder="$sortOrder" sortParam="sort_kendaraan" orderParam="order_kendaraan" />
                <x-sortable-th col="updated" label="Diperbarui" :sortBy="$sortBy" :sortOrder="$sortOrder" sortParam="sort_kendaraan" orderParam="order_kendaraan" />
                <th class="px-3 py-3 text-right">Action</th>
            </tr>
        </thead>

        <tbody class="divide-y divide-gray-200 text-sm">
            @if($error)
                <tr>
                    <td colspan="6" class="py-6 px-4 text-center text-sm text-red-600">
                        ⚠️ Error: {{ $error }}
                    </td>
                </tr>
            @elseif(empty($displayKendaraan))
                <tr>
                    <td colspan="6" class="py-12 text-center text-sm text-gray-400">
                        Tidak ada kendaraan yang tersedia. <br>
                        <span class="text-xs">Data kendaraan akan muncul setelah ditambahkan.</span>
                    </td>
                </tr>
            @else
                @foreach ($displayKendaraan as $g)
                <tr
                    class="transition hover:bg-gray-50 kendaraan-row"
                    @if($mode === 'pilih')
                        x-show="'{{ strtolower(($g['nama'] ?? '') . ' ' . ($g['badan_usaha'] ?? '') . ' ' . $g['kendaraan_label'] . ' ' . $g['skill_label']) }}'.includes(searchKendaraan.toLowerCase())"
                    @endif
                    @if($mode !== 'pilih')
                        data-nama="{{ strtolower($g['nama'] ?? '') }}"
                        data-badan="{{ strtolower($g['badan_usaha'] ?? '') }}"
                        data-cabang="{{ strtolower($g['cabang_label']) }}"
                        data-skill="{{ strtolower($g['skill_label']) }}"
                        data-kendaraan="{{ strtolower($g['kendaraan_label']) }}"
                    @endif>
                    <td class="px-3 py-3 font-medium text-gray-800 truncate">{{ $g['nama'] ?? '—' }}</td>
                    <td class="px-3 py-3 text-gray-600 truncate">{{ $g['badan_usaha'] ?? '—' }}</td>
                    <td class="px-3 py-3 text-gray-600 truncate">{{ $g['cabang_label'] !== '' ? $g['cabang_label'] : '—' }}</td>
                    <td class="relative group px-3 py-3 text-gray-600">
                        <span class="block max-w-full truncate">{{ $g['skill_label'] !== '' ? $g['skill_label'] : '—' }}</span>
                        @if($g['skill_label'] !== '')
                        @php $isBanyak = count($g['items']) > 4; @endphp
                        <div class="hidden absolute left-0 top-0 z-20 max-h-80
                            {{ $isBanyak ? 'w-[28rem] grid grid-cols-2 gap-x-4 gap-y-1' : 'w-80' }}
                            overflow-y-auto rounded-lg border border-gray-200 bg-white p-3 text-xs
                            opacity-0 shadow-lg transition-all duration-150 transition-discrete
                            group-hover:block group-hover:opacity-100 group-hover:starting:opacity-0">
                            @foreach ($g['items'] as $item)
                                @continue($item['skill_label'] === '' && empty($item['tarif_breakdown']))
                                <div class="{{ $isBanyak ? '' : 'mb-1.5 last:mb-0' }}">
                                    <div class="font-medium text-gray-800">
                                        {{ $item['skill_label'] !== '' ? $item['skill_label'] : '—' }}
                                        @if ($item['id_cabang'])
                                            <span class="ml-1 rounded bg-gray-100 px-1 py-0.5 text-[10px] font-normal text-gray-500">Cabang {{ $item['id_cabang'] }}</span>
                                        @endif
                                        @if ($item['area_lain'])
                                            <span title="Area ini namanya sama kayak kota cabang {{ $item['area_lain'] }} — bukan berarti data nyasar, ini cuma tujuan pengantaran"
                                                class="ml-1 rounded bg-gray-100 px-1 py-0.5 text-[10px] font-normal text-gray-500">
                                                📍 area cabang lain
                                            </span>
                                        @endif
                                    </div>
                                    @if (!empty($item['tarif_breakdown']))
                                        @foreach ($item['tarif_breakdown'] as $t)
                                            <div class="flex justify-between text-gray-500">
                                                <span>{{ $t['nama_barang'] }}</span>
                                                <span>Rp {{ number_format($t['harga'], 0, ',', '.') }}</span>
                                            </div>
                                        @endforeach
                                    @endif
                                </div>
                            @endforeach
                            @if (collect($g['items'])->every(fn ($i) => $i['skill_label'] === '' && empty($i['tarif_breakdown'])))
                                <div class="text-gray-400">Belum ada skill/area terdaftar.</div>
                            @endif
                        </div>
                        @endif
                    </td>
                    <td class="px-3 py-3 text-gray-400 text-xs whitespace-nowrap">{{ $g['updated'] }}</td>
                    <td class="px-3 py-3 text-right">
                        <details class="kendaraan-action-details relative inline-block text-left">
                            <summary class="cursor-pointer list-none rounded-lg border border-avian-green px-3 py-1.5
                                text-xs font-medium text-avian-green transition hover:bg-avian-green-light">
                                Pilih
                            </summary>
                            @php $isBanyakPilih = count($g['items']) > 4; @endphp
                            <div class="absolute right-0 z-30 mt-1 max-h-80
                                {{ $isBanyakPilih ? 'w-[32rem] grid grid-cols-2 gap-1' : 'w-80' }}
                                overflow-y-auto rounded-lg border border-gray-200 bg-white p-2 shadow-lg">
                                @foreach ($g['items'] as $item)
                                    @continue($item['skill_label'] === '' && empty($item['tarif_breakdown']))
                                    <div class="flex min-w-0 items-center justify-between gap-2 rounded-lg px-2 py-2 hover:bg-gray-50">
                                        <div class="min-w-0 text-xs">
                                            <div class="truncate font-medium text-gray-800">
                                                {{ $item['skill_label'] !== '' ? $item['skill_label'] : '—' }}
                                                @if ($item['id_cabang'])
                                                    <span class="ml-1 rounded bg-gray-100 px-1 py-0.5 text-[10px] font-normal text-gray-500">Cabang {{ $item['id_cabang'] }}</span>
                                                @endif
                                                @if ($item['area_lain'])
                                                    <span title="Area ini namanya sama kayak kota cabang {{ $item['area_lain'] }} — bukan berarti data nyasar, ini cuma tujuan pengantaran"
                                                        class="ml-1 rounded bg-gray-100 px-1 py-0.5 text-[10px] font-normal text-gray-500">📍</span>
                                                @endif
                                            </div>
                                            <div class="text-gray-500">{{ $item['muatan'] }} · {{ $item['harga'] }}</div>
                                        </div>
                                        @if($mode === 'pilih')
                                            <button
                                                type="button"
                                                @click="kendaraanTerpilih = {{ json_encode($item) }}"
                                                :class="kendaraanTerpilih?.id === {{ $item['id'] }}
                                                    ? 'bg-avian-green text-white'
                                                    : 'border border-avian-green text-avian-green hover:bg-avian-green-light'"
                                                class="shrink-0 rounded-lg px-2 py-1 text-xs font-medium transition">
                                                <span x-show="kendaraanTerpilih?.id !== {{ $item['id'] }}">Pilih</span>
                                                <span x-show="kendaraanTerpilih?.id === {{ $item['id'] }}">✓ Dipilih</span>
                                            </button>
                                        @elseif($mode === 'detail')
                                            <div class="flex shrink-0 items-center gap-1.5">
                                                <a href="{{ route('kendaraan.show', $item['id']) }}"
                                                    class="rounded-lg border border-avian-green px-2 py-1 text-xs font-medium text-avian-green hover:bg-avian-green-light">
                                                    Detail
                                                </a>
                                                @if(auth()->user()?->userUtility?->role === 'DCI')
                                                <a href="{{ route('kelola-tarif.sewa-truk', ['search' => $g['nama']]) }}"
                                                    class="rounded-lg border border-gray-300 px-2 py-1 text-xs font-medium text-gray-600 hover:bg-gray-50"
                                                    title="Kelola perusahaan ini di Kelola Tarif — Sewa Truk">
                                                    Kelola Sewa Truk
                                                </a>
                                                <a href="{{ route('kelola-tarif.kiriman-rutin', ['search' => $g['nama']]) }}"
                                                    class="rounded-lg border border-gray-300 px-2 py-1 text-xs font-medium text-gray-600 hover:bg-gray-50"
                                                    title="Kelola perusahaan ini di Kelola Tarif — Kiriman Rutin">
                                                    Kelola Kiriman Rutin
                                                </a>
                                                @endif
                                            </div>
                                        @else
                                            <a href="{{ route('pengajuan.kg', ['id' => $item['id'], 'nama' => $item['nama'], 'kendaraan' => $item['kendaraan'], 'muatan' => $item['muatan'], 'muatan_raw' => $item['muatan_raw'], 'harga' => $item['harga'], 'skill' => $item['skill']]) }}"
                                                class="shrink-0 rounded-lg bg-avian-green px-2 py-1 text-xs font-medium text-white hover:bg-avian-green-dark">
                                                Pilih
                                            </a>
                                        @endif
                                    </div>
                                @endforeach
                                @if (collect($g['items'])->every(fn ($i) => $i['skill_label'] === '' && empty($i['tarif_breakdown'])))
                                    <div class="px-2 py-2 text-xs text-gray-400">Belum ada skill/area terdaftar.</div>
                                @endif
                            </div>
                        </details>
                    </td>
                </tr>
                @endforeach
            @endif
        </tbody>
    </table>
</div>

<script>
// Tutup panel <details> "Pilih" lain yang lagi kebuka pas salah satunya dibuka -
// biar ga numpuk banyak panel sekaligus. Aman dipanggil berkali-kali (listener
// di-scope ke document, idempotent lewat flag di window).
if (!window.__kendaraanActionDetailsWired) {
    window.__kendaraanActionDetailsWired = true;
    document.addEventListener('toggle', function (e) {
        if (!e.target.matches('.kendaraan-action-details')) return;
        if (!e.target.open) return;
        document.querySelectorAll('.kendaraan-action-details[open]').forEach(function (d) {
            if (d !== e.target) d.open = false;
        });
    }, true);
    document.addEventListener('click', function (e) {
        document.querySelectorAll('.kendaraan-action-details[open]').forEach(function (d) {
            if (!d.contains(e.target)) d.open = false;
        });
    });
}
</script>

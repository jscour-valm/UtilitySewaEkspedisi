<?php

namespace App\Http\Controllers;

use App\Helpers\FormatHelper;
use App\Http\Controllers\Concerns\BuildsPerusahaanSummary;
use App\Models\JenisBarangKiriman;
use App\Models\MasterJenisKendaraan;
use App\Models\PerusahaanEkspedisi;
use App\Models\PerusahaanSkill;
use App\Models\RiwayatHargaSewaTruk;
use App\Models\RiwayatTarifKirimanRutin;
use App\Models\TarifKirimanRutin;
use App\Models\UsulanHarga;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Halaman master perusahaan (vendor, kendaraan, tarif Sewa Truk & Kiriman Rutin).
 * Form edit tarif ada di TarifKirimanRutinController (route perusahaan.*).
 *
 * - index(): `tab` = semua | sewa-truk | kiriman-rutin.
 *   Tab "semua" → 1 baris per perusahaan (ringkasan). Tab lain → 1 baris per vendor_skill
 *   (perusahaan + cabang + area).
 * - show(): detail perusahaan (profil, dokumen, kendaraan, tarif, riwayat harga).
 *
 * KG hanya melihat vendor & baris tarif/kendaraan cabangnya sendiri, tanpa tab. Role lain melihat
 * semua cabang; untuk WM, vendor "milik" cabangnya (punya vendor_skill / unit kendaraan / diajukan
 * dari cabang itu) naik ke atas + badge "Cabang Anda". sesi_perusahaan_ekspedisi tidak punya kolom
 * cabang (entity global). User global (WH, WC, DCI) tanpa prioritas/badge.
 */
class PerusahaanController extends Controller
{
    use BuildsPerusahaanSummary;

    private const TABS = ['semua', 'sewa-truk', 'kiriman-rutin'];

    private const PER_PAGE = 25;

    // Enum kolom `badan_usaha` di migration sesi_perusahaan_ekspedisi, minus '-' (placeholder
    // "belum diisi" dari impor CSV) — facet filter selalu nunjukin semua nilai ini, bukan cuma
    // yang kebetulan ada di data sekarang.
    private const BADAN_USAHA_OPTIONS = ['PT', 'CV', 'UD', 'Perseorangan'];

    /** Role yang boleh menambah vendor (langsung aktif), edit profil vendor, tambah/edit unit kendaraan. */
    public const ROLE_KELOLA_MASTER = ['WM', 'WC', 'WH', 'DCI'];

    // ------------------------------------------------------------------
    // INDEX
    // ------------------------------------------------------------------

    public function index(Request $request)
    {
        $isKg = auth()->user()?->userUtility?->role === 'KG';

        $tab = $request->get('tab');
        $tab = in_array($tab, self::TABS, true) ? $tab : 'semua';
        // KG: 1 tab saja (data sudah disempitkan ke cabang sendiri). Role lain 3 tab.
        if ($isKg) {
            $tab = 'semua';
        }
        $search = trim((string) $request->get('search', ''));
        $filters = [
            'cabang' => $this->cleanList($request->input('cabang')),
            'skill' => array_map('intval', $this->cleanList($request->input('skill'))),
            'badan_usaha' => $this->cleanList($request->input('badan_usaha')),
        ];
        // Range harga: khusus tab sewa-truk (harga_min/max) & kiriman-rutin (barang_id[] + barang_min/max).
        $filters['harga_min'] = $this->cleanNumber($request->get('harga_min'));
        $filters['harga_max'] = $this->cleanNumber($request->get('harga_max'));
        $filters['barang_id'] = array_map('intval', $this->cleanList($request->input('barang_id')));
        $filters['barang_min'] = $this->cleanNumber($request->get('barang_min'));
        $filters['barang_max'] = $this->cleanNumber($request->get('barang_max'));

        $own = $this->ownCabang();
        $sortOrder = strtolower((string) $request->get('order', 'asc')) === 'desc' ? 'desc' : 'asc';
        $requestedSort = (string) $request->get('sort', '');

        $data = match ($tab) {
            'sewa-truk' => $this->indexSewaTruk($search, $filters, $own, $requestedSort, $sortOrder),
            'kiriman-rutin' => $this->indexKirimanRutin($search, $filters, $own, $requestedSort, $sortOrder),
            default => $this->indexSemua($search, $filters, $own, $requestedSort, $sortOrder),
        };

        if ($isKg) {
            // Filter KG: cabang dikunci ke cabang sendiri (dipaksa di indexSemua(), tidak
            // ditampilkan), tanpa Area Kirim. Isinya Badan Usaha, range Harga Sewa, Jenis Barang
            // + range Harga per Unit — batas slider dihitung dari cabang sendiri.
            $badanUsahaOptions = collect(self::BADAN_USAHA_OPTIONS)->map(fn ($b) => ['value' => $b, 'label' => $b])->all();
            $jenisBarangOptions = JenisBarangKiriman::where('flag', true)->orderBy('nama_barang')->get(['id_jenis_barang', 'nama_barang']);
            $barangOptions = $jenisBarangOptions->map(fn ($jb) => ['value' => (string) $jb->id_jenis_barang, 'label' => $jb->nama_barang])->all();

            $facets = [
                ['key' => 'badan_usaha', 'label' => 'Badan Usaha', 'options' => $badanUsahaOptions, 'count' => count($badanUsahaOptions)],
                ['key' => 'barang_id', 'label' => 'Jenis Barang', 'options' => $barangOptions, 'count' => count($barangOptions)],
            ];
            $ranges = [
                [
                    'key' => 'harga', 'label' => 'Harga Sewa', 'prefix' => 'Rp',
                    'bounds' => $this->hargaSewaBounds($own), 'min' => $filters['harga_min'], 'max' => $filters['harga_max'],
                ],
                [
                    'key' => 'barang', 'label' => 'Harga per Unit', 'prefix' => 'Rp',
                    'bounds' => $this->barangHargaBounds($own), 'min' => $filters['barang_min'], 'max' => $filters['barang_max'],
                ],
            ];
        } else {
            $facets = $this->facetOptions($own, $filters['cabang']);
            $ranges = [];
            if ($tab === 'sewa-truk') {
                $ranges[] = [
                    'key' => 'harga', 'label' => 'Harga Sewa', 'prefix' => 'Rp',
                    'bounds' => $this->hargaSewaBounds(), 'min' => $filters['harga_min'], 'max' => $filters['harga_max'],
                ];
            } elseif ($tab === 'kiriman-rutin') {
                $jenisBarangOptions = JenisBarangKiriman::where('flag', true)->orderBy('nama_barang')->get(['id_jenis_barang', 'nama_barang']);
                $barangOptions = $jenisBarangOptions->map(fn ($jb) => ['value' => (string) $jb->id_jenis_barang, 'label' => $jb->nama_barang])->all();
                $facets[] = ['key' => 'barang_id', 'label' => 'Jenis Barang', 'options' => $barangOptions, 'count' => count($barangOptions)];
                $ranges[] = [
                    'key' => 'barang', 'label' => 'Harga per Unit', 'prefix' => 'Rp',
                    'bounds' => $this->barangHargaBounds(), 'min' => $filters['barang_min'], 'max' => $filters['barang_max'],
                ];
            }
        }

        return view('pages.perusahaan.index', array_merge($data, [
            'tab' => $tab,
            'search' => $search,
            'filters' => $filters,
            'facets' => $facets,
            'ranges' => $ranges,
            'sortOrder' => $sortOrder,
            'ownCabang' => $own,
            'isKg' => $isKg,
            'bolehKelola' => in_array(auth()->user()?->userUtility?->role, self::ROLE_KELOLA_MASTER, true),
        ]));
    }

    /**
     * Fragment HTML pill Area/Skill (dipakai ulang di render awal modal DAN endpoint AJAX
     * `perusahaan.facet.skill-options`), supaya opsi selalu ikut cabang yang lagi dicentang
     * user TANPA nunggu klik "Terapkan" — lihat script di pill-filter-modal.blade.php.
     */
    public function skillOptions(Request $request)
    {
        $cabang = $this->cleanList($request->input('cabang'));
        $selected = array_map('strval', $this->cleanList($request->input('skill')));

        return view('partials.perusahaan-skill-options', [
            'options' => $this->skillFacetOptions($cabang),
            'selected' => $selected,
        ]);
    }

    /**
     * Proyeksi "dashboard" dari tabel Perusahaan (tab Semua) — 5 data tercocok by
     * search, tanpa facet/pagination. Dipakai dashboard KG lewat `<x-perusahaan-dashboard-preview>`.
     */
    public function preview(Request $request)
    {
        $own = $this->ownCabang();
        // Sama seperti wizard Pengajuan — cuma perusahaan yang terhubung ke
        // cabang user atau belum terhubung ke cabang manapun; TIDAK ikut cabang lain.
        $rows = $this->summaryRows($own, $request->get('search'), 5, $this->ownOrUnclaimedScope($own));

        return response()->json(['data' => $rows->values()]);
    }

    /** Batas Harga Sewa asli di data (buat slider filter) — [0,0] kalau belum ada data sama sekali. */
    private function hargaSewaBounds(?array $own = null): array
    {
        $query = DB::connection('sqlsrv')->table('sesi_perusahaan_skill')
            ->whereNotNull('harga_sewa')->where('flag', true);
        if ($own) {
            $query->whereIn('cabang_code', $own);
        }
        $row = $query->selectRaw('MIN(harga_sewa) as mn, MAX(harga_sewa) as mx')->first();

        return ['min' => (int) ($row->mn ?? 0), 'max' => (int) ($row->mx ?? 0)];
    }

    /** Batas harga per unit tarif kiriman rutin asli di data (buat slider filter). */
    private function barangHargaBounds(?array $own = null): array
    {
        $query = DB::connection('sqlsrv')->table('sesi_tarif_kiriman_rutin as t')
            ->where('t.flag', true);
        if ($own) {
            $query->join('sesi_perusahaan_skill as ps', 'ps.id_vendor_skill', '=', 't.id_vendor_skill')
                ->where('ps.flag', true)
                ->whereIn('ps.cabang_code', $own);
        }
        $row = $query->selectRaw('MIN(t.biaya_per_unit) as mn, MAX(t.biaya_per_unit) as mx')->first();

        return ['min' => (int) ($row->mn ?? 0), 'max' => (int) ($row->mx ?? 0)];
    }

    private function indexSemua(string $search, array $f, array $own, string $requestedSort, string $sortOrder): array
    {
        $db = DB::connection('sqlsrv');
        $isKg = auth()->user()?->userUtility?->role === 'KG';

        $query = $db->table('sesi_perusahaan_ekspedisi as pe')->where('pe.flag', true);
        $this->applySemuaSearch($query, $search);
        $this->hanyaVendorTerlihat($query, $own);

        if ($f['cabang']) {
            $query->where(function ($w) use ($f) {
                $w->whereExists(function ($s) use ($f) {
                    $s->select(DB::raw(1))->from('sesi_perusahaan_skill as fc')
                        ->whereColumn('fc.id_perusahaan', 'pe.id_perusahaan')
                        ->where('fc.flag', true)
                        ->whereIn('fc.cabang_code', $f['cabang']);
                })->orWhereExists(function ($s) use ($f) {
                    $s->select(DB::raw(1))->from('sesi_unit_kendaraan as fu')
                        ->whereColumn('fu.id_perusahaan', 'pe.id_perusahaan')
                        ->where('fu.flag', true)
                        ->whereIn('fu.id_cabang', $f['cabang']);
                });
            });
        }

        if ($f['skill']) {
            $query->whereExists(function ($s) use ($f) {
                $s->select(DB::raw(1))->from('sesi_perusahaan_skill as fs')
                    ->whereColumn('fs.id_perusahaan', 'pe.id_perusahaan')
                    ->where('fs.flag', true)
                    ->whereIn('fs.id_skill', $f['skill']);
            });
        }

        if ($f['badan_usaha']) {
            $query->whereIn('pe.badan_usaha', $f['badan_usaha']);
        }

        // Filter harga Sewa Truk / Kiriman Rutin di tab Semua (dipakai modal filter KG).
        if ($f['harga_min'] !== null || $f['harga_max'] !== null) {
            $query->whereExists(function ($s) use ($f, $own, $isKg) {
                $s->select(DB::raw(1))->from('sesi_perusahaan_skill as fh')
                    ->whereColumn('fh.id_perusahaan', 'pe.id_perusahaan')
                    ->where('fh.flag', true)
                    ->whereNotNull('fh.harga_sewa')
                    ->when($isKg && $own, fn ($q) => $q->whereIn('fh.cabang_code', $own))
                    ->when($f['harga_min'] !== null, fn ($q) => $q->where('fh.harga_sewa', '>=', $f['harga_min']))
                    ->when($f['harga_max'] !== null, fn ($q) => $q->where('fh.harga_sewa', '<=', $f['harga_max']));
            });
        }

        if ($f['barang_id'] || $f['barang_min'] !== null || $f['barang_max'] !== null) {
            $query->whereExists(function ($s) use ($f, $own, $isKg) {
                $s->select(DB::raw(1))->from('sesi_tarif_kiriman_rutin as ft')
                    ->join('sesi_perusahaan_skill as fps', 'fps.id_vendor_skill', '=', 'ft.id_vendor_skill')
                    ->whereColumn('fps.id_perusahaan', 'pe.id_perusahaan')
                    ->where('ft.flag', true)->where('fps.flag', true)
                    ->when($isKg && $own, fn ($q) => $q->whereIn('fps.cabang_code', $own))
                    ->when($f['barang_id'], fn ($q) => $q->whereIn('ft.id_jenis_barang', $f['barang_id']))
                    ->when($f['barang_min'] !== null, fn ($q) => $q->where('ft.biaya_per_unit', '>=', $f['barang_min']))
                    ->when($f['barang_max'] !== null, fn ($q) => $q->where('ft.biaya_per_unit', '<=', $f['barang_max']));
            });
        }

        // KG hanya melihat vendor yang terhubung ke cabangnya (WHERE). Role lain melihat semua
        // vendor; `$own` di tempat lain hanya untuk prioritas urutan.
        if ($isKg && $own) {
            [$mineOnlySql, $mineOnlyBindings] = $this->mineCompanySql($own);
            $query->whereRaw("{$mineOnlySql} = 1", $mineOnlyBindings);
        }

        // "Diperbarui" = paling baru antara profil perusahaan, tarif (vendor_skill), dan
        // unit kendaraannya — biar tarif/kendaraan yang baru diedit ikut ngangkat baris.
        $lastUpdate = '(SELECT MAX(d) FROM (VALUES
            (pe.updated_at),
            ((SELECT MAX(x.updated_at) FROM sesi_perusahaan_skill x WHERE x.id_perusahaan = pe.id_perusahaan AND x.flag = 1)),
            ((SELECT MAX(k.updated_at) FROM sesi_unit_kendaraan k WHERE k.id_perusahaan = pe.id_perusahaan AND k.flag = 1))
        ) AS t(d))';

        // is_mine = perusahaan ter-link ke cabang user (vendor_skill ATAU unit kendaraan).
        [$mineSql, $mineBindings] = $this->mineCompanySql($own);
        // Subquery buat sort kolom "Cakupan" (jumlah cabang) & "Kendaraan" (jumlah unit) — nilai
        // yang beneran ditampilkan (cabang_count/area_count/kendaraan list) dihitung di PHP di
        // transform bawah, subquery ini cuma dipakai kalau kolomnya di-sort.
        $cabangCountSql = '(SELECT COUNT(DISTINCT cb) FROM (
            SELECT cabang_code AS cb FROM sesi_perusahaan_skill WHERE id_perusahaan = pe.id_perusahaan AND flag = 1
            UNION
            SELECT id_cabang AS cb FROM sesi_unit_kendaraan WHERE id_perusahaan = pe.id_perusahaan AND flag = 1
        ) t)';
        $kendaraanCountSql = '(SELECT COUNT(*) FROM sesi_unit_kendaraan WHERE id_perusahaan = pe.id_perusahaan AND flag = 1)';

        $query->select('pe.id_perusahaan', 'pe.nama_perusahaan', 'pe.badan_usaha', 'pe.identitas_owner', 'pe.status_approval')
            ->selectRaw("{$lastUpdate} as last_update")
            ->selectRaw("{$mineSql} as is_mine", $mineBindings)
            ->selectRaw("{$cabangCountSql} as cakupan_sort")
            ->selectRaw("{$kendaraanCountSql} as kendaraan_sort");

        $sortMap = [
            'nama' => 'pe.nama_perusahaan',
            'badan_usaha' => 'pe.badan_usaha',
            'cakupan' => 'cakupan_sort',
            'kendaraan' => 'kendaraan_sort',
            'diperbarui' => 'last_update',
        ];
        $sortBy = isset($sortMap[$requestedSort]) ? $requestedSort : null;
        if ($sortBy) {
            $query->orderBy($sortMap[$sortBy], $sortOrder);
        } elseif ($own) {
            // Prioritas cabang user cuma di urutan default; sort pilihan user menang.
            $query->orderByDesc('is_mine');
        }
        $this->orderDefaults($query, ['pe.nama_perusahaan', 'pe.id_perusahaan'], $sortBy ? $sortMap[$sortBy] : null);

        $rows = $query->paginate(self::PER_PAGE)->withQueryString();

        $ids = collect($rows->items())->pluck('id_perusahaan')->all();
        $rel = $this->loadRelations($ids, $own);

        // KG: buang data cabang lain (kolom harga per jenis barang harus cabang-scoped).
        if ($isKg) {
            $rel['vs'] = $rel['vs']->whereIn('cabang_code', $own)->values();
            $rel['units'] = $rel['units']->whereIn('id_cabang', $own)->values();
        }

        $vsBy = $rel['vs']->groupBy('id_perusahaan');
        $unitsBy = $rel['units']->groupBy('id_perusahaan');
        $cabangNames = DB::connection('sqlsrv')->table('sesi_master_cabang')->pluck('Name', 'Code');

        // KG: harga per jenis barang (Kiriman Rutin) buat kolom baru — 1 query gabungan
        // buat semua baris di halaman ini (hindari N+1), keyed by id_perusahaan.
        $tarifByPerusahaan = [];
        if ($isKg) {
            $idVendorSkillList = $rel['vs']->pluck('id_vendor_skill')->all();
            $vsToPerusahaan = $rel['vs']->pluck('id_perusahaan', 'id_vendor_skill');
            $vsToSkill = $rel['vs']->pluck('id_skill', 'id_vendor_skill');
            if (! empty($idVendorSkillList)) {
                $tarifRows = TarifKirimanRutin::with('jenisBarang')
                    ->whereIn('id_vendor_skill', $idVendorSkillList)
                    ->where('flag', true)
                    ->get();
                foreach ($tarifRows as $t) {
                    $idPerusahaan = $vsToPerusahaan->get($t->id_vendor_skill);
                    if ($idPerusahaan === null) {
                        continue;
                    }
                    $idJenisBarang = (int) $t->id_jenis_barang;
                    $tarifByPerusahaan[$idPerusahaan][$idJenisBarang]['nama_barang'] = $t->jenisBarang->nama_barang ?? '—';
                    $tarifByPerusahaan[$idPerusahaan][$idJenisBarang]['items'][] = [
                        'area' => $rel['skillNames']->get((int) $vsToSkill->get($t->id_vendor_skill)),
                        'harga' => (float) $t->biaya_per_unit,
                    ];
                }
            }
        }

        $rows->getCollection()->transform(function ($row) use ($rel, $vsBy, $unitsBy, $cabangNames, $isKg, $tarifByPerusahaan) {
            $vs = $vsBy->get($row->id_perusahaan, collect());
            $units = $unitsBy->get($row->id_perusahaan, collect());

            $areas = $vs->pluck('id_skill')->map(fn ($v) => (int) $v);
            foreach ($units as $u) {
                foreach (explode(',', (string) $u->id_skill) as $token) {
                    $token = trim($token);
                    if (ctype_digit($token)) {
                        $areas->push((int) $token);
                    }
                }
            }
            $areas = $areas->unique();

            $cabangCodes = $vs->pluck('cabang_code')->merge($units->pluck('id_cabang'))->filter()->unique();

            $row->cabang_count = $cabangCodes->count();
            $row->area_count = $areas->count();
            $row->has_sewa = $vs->contains(fn ($v) => $v->harga_sewa !== null);
            $row->has_kiriman = $vs->contains(fn ($v) => isset($rel['tarifVsIds'][$v->id_vendor_skill]));
            $row->kendaraan = $this->kendaraanItems($units, $vs, $rel['skillNames'], $row->nama_perusahaan);
            // Detail buat popover hover kolom "Cakupan" — daftar lengkap cabang (kode + nama) & area.
            $row->cabang_list = $cabangCodes->sort()->values()->map(fn ($c) => ['code' => $c, 'nama' => $cabangNames->get($c)])->all();
            $row->area_list = $areas->map(fn ($id) => $rel['skillNames']->get($id))->filter()->sort()->values()->all();

            if ($isKg) {
                // Dokumen identitas (thumbnail + lightbox) — kolom pengganti "Cakupan"/"Kendaraan".
                $row->identitas_owner_src = collect(json_decode($row->identitas_owner ?? '[]', true) ?: [])
                    ->map(fn ($p) => FormatHelper::identitasOwnerSrc($p))
                    ->values()->all();
                // id_jenis_barang => array of ['area'=>.., 'harga'=>..] (dari $tarifByPerusahaan,
                // udah cabang-scoped di atas) — bisa >1 kalau beda area beda harga.
                $row->kiriman_items = collect($tarifByPerusahaan[$row->id_perusahaan] ?? [])
                    ->map(fn ($t) => $t['items'])->all();
                // Harga Sewa per area di cabang sendiri (bisa >1 kalau beda area beda harga).
                $row->sewa_items = $vs->filter(fn ($v) => $v->harga_sewa !== null)
                    ->map(fn ($v) => ['area' => $rel['skillNames']->get((int) $v->id_skill), 'harga' => (float) $v->harga_sewa])
                    ->values()->all();
            }

            return $row;
        });

        $jenisBarangCols = [];
        if ($isKg) {
            // Kolom = jenis barang yang muncul di halaman ini, urut dari yang paling banyak
            // vendornya (kiri) ke yang paling jarang (kanan).
            $counts = [];
            $names = [];
            foreach ($tarifByPerusahaan as $items) {
                foreach ($items as $idJenisBarang => $t) {
                    $counts[$idJenisBarang] = ($counts[$idJenisBarang] ?? 0) + 1;
                    $names[$idJenisBarang] = $t['nama_barang'];
                }
            }
            $jenisBarangCols = collect($counts)
                ->map(fn ($count, $id) => ['id_jenis_barang' => (int) $id, 'nama_barang' => $names[$id], 'count' => $count])
                ->sort(fn ($a, $b) => $b['count'] <=> $a['count'] ?: strcasecmp($a['nama_barang'], $b['nama_barang']))
                ->values()->all();
        }

        return ['rows' => $rows, 'sortBy' => $sortBy, 'jenisBarangCols' => $jenisBarangCols];
    }

    private function indexSewaTruk(string $search, array $f, array $own, string $requestedSort, string $sortOrder): array
    {
        $query = $this->vendorSkillBase($search, $f)->whereNotNull('ps.harga_sewa');
        if ($f['harga_min'] !== null) {
            $query->where('ps.harga_sewa', '>=', $f['harga_min']);
        }
        if ($f['harga_max'] !== null) {
            $query->where('ps.harga_sewa', '<=', $f['harga_max']);
        }

        $sortMap = [
            'kode_area' => 'mc.Area',
            'cabang' => 'ps.cabang_code',
            'nama' => 'pe.nama_perusahaan',
            'badan_usaha' => 'pe.badan_usaha',
            'area_kirim' => 'ms.nama_skill',
            'harga' => 'ps.harga_sewa',
            'dibuat' => 'ps.created_at',
        ];
        $sortBy = null;
        if ($requestedSort === 'diupdate') {
            $sortBy = 'diupdate';
            $query->orderByRaw('COALESCE(ps.update_date_source, ps.updated_at) '.$sortOrder);
        } elseif (isset($sortMap[$requestedSort])) {
            $sortBy = $requestedSort;
            $query->orderBy($sortMap[$sortBy], $sortOrder);
        } else {
            $this->orderMineFirst($query, $own);
        }
        $this->orderDefaults($query, ['pe.nama_perusahaan', 'ps.cabang_code', 'ps.id_vendor_skill'], $sortBy ? ($sortMap[$sortBy] ?? null) : null);

        $rows = $query->select(
            'ps.id_vendor_skill',
            'pe.id_perusahaan',
            'pe.nama_perusahaan',
            'pe.badan_usaha',
            'pe.identitas_owner',
            'ps.cabang_code',
            'mc.Name as nama_cabang',
            'mc.Area as kode_area',
            'ms.nama_skill as area_kirim',
            'ps.harga_sewa',
            'ps.update_date_source',
            'ps.updated_at',
            'ps.created_at'
        )
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        // Query builder mentah nggak kena cast 'array' model — decode manual.
        // Diupdate: pakai "Update Date" asli dari Excel kalau ada, fallback updated_at.
        $rows->getCollection()->transform(function ($row) {
            $row->identitas_owner = json_decode($row->identitas_owner ?? '[]', true) ?: [];
            $row->diupdate = $row->update_date_source ?? $row->updated_at;

            return $row;
        });

        return ['rows' => $rows, 'sortBy' => $sortBy];
    }

    private function indexKirimanRutin(string $search, array $f, array $own, string $requestedSort, string $sortOrder): array
    {
        $jenisBarangList = JenisBarangKiriman::where('flag', true)->orderBy('nama_barang')->get(['id_jenis_barang', 'nama_barang']);

        $query = $this->vendorSkillBase($search, $f)->whereExists(function ($q) {
            $q->select(DB::raw(1))
                ->from('sesi_tarif_kiriman_rutin as t')
                ->whereColumn('t.id_vendor_skill', 'ps.id_vendor_skill')
                ->where('t.flag', true);
        });
        // Filter jenis barang (multi-select) + range harga — cuma aktif kalau ada barang_id dipilih;
        // baris masuk kalau ADA tarif utk salah satu barang terpilih DENGAN harga di rentang slider.
        if ($f['barang_id']) {
            $query->whereExists(function ($q) use ($f) {
                $q->select(DB::raw(1))->from('sesi_tarif_kiriman_rutin as tf')
                    ->whereColumn('tf.id_vendor_skill', 'ps.id_vendor_skill')
                    ->where('tf.flag', true)
                    ->whereIn('tf.id_jenis_barang', $f['barang_id'])
                    ->when($f['barang_min'] !== null, fn ($q2) => $q2->where('tf.biaya_per_unit', '>=', $f['barang_min']))
                    ->when($f['barang_max'] !== null, fn ($q2) => $q2->where('tf.biaya_per_unit', '<=', $f['barang_max']));
            });
        }

        $sortMap = [
            'kode_area' => 'mc.Area',
            'cabang' => 'ps.cabang_code',
            'nama' => 'pe.nama_perusahaan',
            'area_kirim' => 'ms.nama_skill',
            'diupdate' => 'ps.updated_at',
            'dibuat' => 'ps.created_at',
        ];
        $sortBy = null;
        if (isset($sortMap[$requestedSort])) {
            $sortBy = $requestedSort;
            $query->orderBy($sortMap[$sortBy], $sortOrder);
        } elseif (preg_match('/^barang_(\d+)$/', $requestedSort, $m) && $jenisBarangList->contains('id_jenis_barang', (int) $m[1])) {
            // Sort by harga 1 jenis barang tertentu (kolom pivot). Baris yang nggak punya
            // tarif utk barang itu SELALU di paling bawah (CASE), baik asc maupun desc.
            $sortBy = $requestedSort;
            $query->orderByRaw(
                'CASE WHEN (SELECT tt.biaya_per_unit FROM sesi_tarif_kiriman_rutin tt WHERE tt.id_vendor_skill = ps.id_vendor_skill AND tt.id_jenis_barang = ? AND tt.flag = 1) IS NULL THEN 1 ELSE 0 END, '
                .'(SELECT tt.biaya_per_unit FROM sesi_tarif_kiriman_rutin tt WHERE tt.id_vendor_skill = ps.id_vendor_skill AND tt.id_jenis_barang = ? AND tt.flag = 1) '.$sortOrder,
                [(int) $m[1], (int) $m[1]]
            );
        } else {
            $this->orderMineFirst($query, $own);
        }
        $this->orderDefaults($query, ['pe.nama_perusahaan', 'ps.cabang_code', 'ps.id_vendor_skill'], $sortBy ? ($sortMap[$sortBy] ?? null) : null);

        $rows = $query->select(
            'ps.id_vendor_skill',
            'pe.id_perusahaan',
            'pe.nama_perusahaan',
            'ps.cabang_code',
            'mc.Name as nama_cabang',
            'mc.Area as kode_area',
            'ms.nama_skill as area_kirim',
            'ps.updated_at',
            'ps.created_at'
        )
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        // Tarif semua vendor_skill di halaman ini sekaligus (hindari N+1), lalu pivot per jenis barang.
        $vendorSkillIds = collect($rows->items())->pluck('id_vendor_skill');
        $tarifByVendorSkill = $vendorSkillIds->isEmpty() ? collect() : DB::connection('sqlsrv')
            ->table('sesi_tarif_kiriman_rutin')
            ->whereIn('id_vendor_skill', $vendorSkillIds)
            ->where('flag', true)
            ->get()
            ->groupBy('id_vendor_skill');

        $rows->getCollection()->transform(function ($row) use ($tarifByVendorSkill) {
            $row->harga = [];
            foreach ($tarifByVendorSkill->get($row->id_vendor_skill, collect()) as $t) {
                $row->harga[$t->id_jenis_barang] = (float) $t->biaya_per_unit;
            }

            return $row;
        });

        return ['rows' => $rows, 'sortBy' => $sortBy, 'jenisBarangList' => $jenisBarangList];
    }

    /**
     * Query dasar tab Sewa Truk / Kiriman Rutin: 1 baris = 1 sesi_perusahaan_skill,
     * plus search dan facet filter (server-side biar tetap
     * benar dikombinasi sama pagination).
     */
    private function vendorSkillBase(string $search, array $f)
    {
        $query = DB::connection('sqlsrv')->table('sesi_perusahaan_skill as ps')
            ->join('sesi_perusahaan_ekspedisi as pe', 'ps.id_perusahaan', '=', 'pe.id_perusahaan')
            ->join('sesi_master_skill as ms', 'ps.id_skill', '=', 'ms.id_skill')
            ->leftJoin('sesi_master_cabang as mc', function ($join) {
                $join->on('ps.cabang_code', '=', DB::raw('mc.Code COLLATE SQL_Latin1_General_CP1_CI_AS'));
            })
            ->where('ps.flag', true)
            ->where('pe.flag', true);

        // KG hanya melihat baris tarif cabangnya. Role lain semua (orderMineFirst() cuma sortir).
        if (auth()->user()?->userUtility?->role === 'KG') {
            $own = $this->ownCabang();
            $own ? $query->whereIn('ps.cabang_code', $own) : $query->whereRaw('1 = 0');
        }

        if ($search !== '') {
            $like = "%{$search}%";
            $query->where(function ($q) use ($like) {
                $q->where('pe.nama_perusahaan', 'like', $like)
                    ->orWhere('ps.cabang_code', 'like', $like)
                    ->orWhere('ms.nama_skill', 'like', $like);
            });
        }

        if ($f['cabang']) {
            $query->whereIn('ps.cabang_code', $f['cabang']);
        }
        if ($f['skill']) {
            $query->whereIn('ps.id_skill', $f['skill']);
        }
        if ($f['badan_usaha']) {
            $query->whereIn('pe.badan_usaha', $f['badan_usaha']);
        }

        return $query;
    }

    // ------------------------------------------------------------------
    // SHOW (Detail Perusahaan)
    // ------------------------------------------------------------------

    public function show($id)
    {
        $perusahaan = PerusahaanEkspedisi::findOrFail($id);
        abort_unless($this->bolehLihatVendor($perusahaan), 404);
        $own = $this->ownCabang();

        $db = DB::connection('sqlsrv');
        $rel = $this->loadRelations([$perusahaan->id_perusahaan], $own);

        // KG: baris tarif/kendaraan cabang lain disembunyikan (perusahaannya tetap bisa dibuka).
        // Role lain: semua cabang tampil, baris cabang user di atas.
        $isKg = auth()->user()?->userUtility?->role === 'KG';
        if ($isKg) {
            $rel['vs'] = $rel['vs']->whereIn('cabang_code', $own)->values();
            $rel['units'] = $rel['units']->whereIn('id_cabang', $own)->values();
        }

        $vendorSkillBase = function () use ($db, $perusahaan, $own, $isKg) {
            $q = $db->table('sesi_perusahaan_skill as ps')
                ->join('sesi_master_skill as ms', 'ps.id_skill', '=', 'ms.id_skill')
                ->leftJoin('sesi_master_cabang as mc', function ($join) {
                    $join->on('ps.cabang_code', '=', DB::raw('mc.Code COLLATE SQL_Latin1_General_CP1_CI_AS'));
                })
                ->where('ps.id_perusahaan', $perusahaan->id_perusahaan);
            if ($isKg) {
                $own ? $q->whereIn('ps.cabang_code', $own) : $q->whereRaw('1 = 0');
            }
            $q->where('ps.flag', true);
            $this->orderMineFirst($q, $own);

            return $q->orderBy('ps.cabang_code')->orderBy('ms.nama_skill');
        };

        $tarifSewa = $vendorSkillBase()->whereNotNull('ps.harga_sewa')
            ->get(['ps.id_vendor_skill', 'ps.id_skill', 'ps.cabang_code', 'mc.Name as nama_cabang', 'ms.nama_skill', 'ps.harga_sewa', 'ps.update_date_source', 'ps.updated_at']);
        // Shortcut "Buat Pengajuan" per baris tarif — cuma KG. Nggak ada syarat cabang: endpoint
        // yang dipanggil wizard SETELAH perusahaan diisi (kendaraan-by-perusahaan / rate-card)
        // udah otomatis nge-scope ke cabang KG sendiri apa pun baris yang diklik (lihat
        // tarifPengajuanUrl()) — baris cabang/skill/harga di sini cuma jadi hint starting point.
        $tarifSewa->each(function ($t) use ($perusahaan) {
            $t->pengajuan_url = $this->tarifPengajuanUrl($perusahaan, 'sewa_truk', (int) $t->id_skill, (float) $t->harga_sewa);
        });

        $tarifKiriman = $vendorSkillBase()->whereExists(function ($q) {
            $q->select(DB::raw(1))->from('sesi_tarif_kiriman_rutin as t')
                ->whereColumn('t.id_vendor_skill', 'ps.id_vendor_skill')->where('t.flag', true);
        })
            ->get(['ps.id_vendor_skill', 'ps.id_skill', 'ps.cabang_code', 'mc.Name as nama_cabang', 'ms.nama_skill', 'ps.updated_at']);
        // 1 baris = 1 kombinasi cabang + area kirim (tarif per barang). id_skill ikut dibawa supaya
        // area di wizard langsung terpilih.
        $tarifKiriman->each(function ($t) use ($perusahaan) {
            $t->pengajuan_url = $this->tarifPengajuanUrl($perusahaan, 'pengiriman_rutin', (int) $t->id_skill, null, $t->nama_skill, (int) $t->id_vendor_skill);
        });

        $tarifRows = $tarifKiriman->isEmpty() ? collect() : $db->table('sesi_tarif_kiriman_rutin')
            ->whereIn('id_vendor_skill', $tarifKiriman->pluck('id_vendor_skill'))
            ->where('flag', true)->get();
        $hargaByVs = [];
        foreach ($tarifRows as $t) {
            $hargaByVs[$t->id_vendor_skill][$t->id_jenis_barang] = (float) $t->biaya_per_unit;
        }
        // Kolom jenis barang cuma yang beneran punya harga di perusahaan ini (hemat lebar tabel).
        $barangIds = $tarifRows->pluck('id_jenis_barang')->unique()->values();
        $jenisBarangCols = $barangIds->isEmpty() ? collect() : JenisBarangKiriman::whereIn('id_jenis_barang', $barangIds)->orderBy('nama_barang')->get(['id_jenis_barang', 'nama_barang']);

        // vendor_skill terdaftar tapi belum ada harga sewa maupun tarif (mis. placeholder dari wizard).
        $belumAdaTarif = $vendorSkillBase()
            ->whereNull('ps.harga_sewa')
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))->from('sesi_tarif_kiriman_rutin as t')
                    ->whereColumn('t.id_vendor_skill', 'ps.id_vendor_skill')->where('t.flag', true);
            })
            ->get(['ps.id_vendor_skill', 'ps.cabang_code', 'mc.Name as nama_cabang', 'ms.nama_skill']);

        $kendaraan = $this->kendaraanItems($rel['units'], $rel['vs'], $rel['skillNames'], $perusahaan->nama_perusahaan);

        $cabangCount = $rel['vs']->pluck('cabang_code')->merge($rel['units']->pluck('id_cabang'))->filter()->unique()->count();
        $areaCount = $rel['vs']->pluck('id_skill')->unique()->count();

        $breadcrumb = [
            'back_url' => route('perusahaan.index'),
            'back_label' => 'Perusahaan',
            'title' => $perusahaan->nama_perusahaan,
        ];

        // Usulan harga master yang masih berjalan, key "jenis|cabang|skill|barang" (penanda per baris tarif).
        $usulanBerjalan = UsulanHarga::where('id_perusahaan', $perusahaan->id_perusahaan)
            ->sedangBerjalan()
            ->when(! auth()->user()->isGlobalAccess(), fn ($q) => $q->whereIn('cabang_code', $own ?: ['__none__']))
            ->get()
            ->keyBy(fn ($u) => $u->jenis.'|'.$u->cabang_code.'|'.$u->id_skill.'|'.($u->id_jenis_barang ?? ''));
        // Pilihan barang di form "Usulkan Harga" Kiriman Rutin (termasuk barang yang belum punya tarif).
        $jenisBarangSemua = $isKg
            ? JenisBarangKiriman::where('flag', true)->orderBy('nama_barang')->get(['id_jenis_barang', 'nama_barang'])
            : collect();

        // Kelola master (edit profil, tambah/edit kendaraan): WM/WC/WH/DCI. Pilihan cabang kendaraan
        // = cabang yang boleh diakses user (WM cabang sendiri, user global semua cabang).
        $bolehKelola = in_array(auth()->user()->userUtility?->role, self::ROLE_KELOLA_MASTER, true);
        $cabangKelola = ! $bolehKelola ? collect() : $db->table('sesi_master_cabang')
            ->when(! auth()->user()->isGlobalAccess(), fn ($q) => $q->whereIn('Code', $own ?: ['__none__']))
            ->orderBy('Code')->get(['Code', 'Name']);
        $jenisKendaraanList = $bolehKelola
            ? MasterJenisKendaraan::orderBy('nama_jenis')->get(['id_jenis_kendaraan', 'nama_jenis', 'muatan_maksimal_ton'])
            : collect();

        return view('pages.perusahaan.show', compact(
            'perusahaan', 'kendaraan', 'tarifSewa', 'tarifKiriman', 'hargaByVs',
            'jenisBarangCols', 'belumAdaTarif', 'cabangCount', 'areaCount', 'breadcrumb',
            'usulanBerjalan', 'jenisBarangSemua', 'bolehKelola', 'cabangKelola', 'jenisKendaraanList'
        ) + ['ownCabang' => $own]);
    }

    /**
     * Riwayat perubahan harga master 1 baris tarif (KA/WM/WC/WH/DCI). Sewa truk: harga_sewa;
     * kiriman rutin: biaya per unit tiap jenis barang. Terbaru di atas.
     * GET /api/perusahaan/riwayat-harga/{sewa-truk|kiriman-rutin}/{idVendorSkill}
     */
    public function riwayatHarga(string $jenis, int $idVendorSkill)
    {
        $vs = PerusahaanSkill::with('perusahaan')->findOrFail($idVendorSkill);
        abort_unless($vs->perusahaan && $this->bolehLihatVendor($vs->perusahaan), 404);

        if ($jenis === 'sewa-truk') {
            $rows = RiwayatHargaSewaTruk::with('diubahOleh:id,name')
                ->where('id_vendor_skill', $idVendorSkill)
                ->orderByDesc('tanggal_perubahan')->orderByDesc('id_riwayat')
                ->get()
                ->map(fn ($r) => [
                    'tanggal' => $r->tanggal_perubahan?->translatedFormat('d M Y, H:i'),
                    'barang' => null,
                    'lama' => $r->harga_lama !== null ? (float) $r->harga_lama : null,
                    'baru' => (float) $r->harga_baru,
                    'oleh' => $r->diubahOleh?->name,
                ]);
        } else {
            $tarif = TarifKirimanRutin::withInactive()->with('jenisBarang:id_jenis_barang,nama_barang')
                ->where('id_vendor_skill', $idVendorSkill)->get()->keyBy('id_tarif');
            $rows = $tarif->isEmpty() ? collect() : RiwayatTarifKirimanRutin::with('diubahOleh:id,name')
                ->whereIn('id_tarif', $tarif->keys())
                ->orderByDesc('tanggal_perubahan')->orderByDesc('id_riwayat')
                ->get()
                ->map(fn ($r) => [
                    'tanggal' => $r->tanggal_perubahan?->translatedFormat('d M Y, H:i'),
                    'barang' => $tarif->get($r->id_tarif)?->jenisBarang?->nama_barang,
                    'lama' => $r->biaya_lama !== null ? (float) $r->biaya_lama : null,
                    'baru' => (float) $r->biaya_baru,
                    'oleh' => $r->diubahOleh?->name,
                ]);
        }

        return response()->json(['success' => true, 'riwayat' => $rows->values()]);
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    /**
     * Urutan default (tie-breaker biar pagination deterministik) — kolom yang
     * sudah dipakai sbg sort utama di-skip, karena SQL Server nolak kolom yang
     * muncul 2x di ORDER BY.
     */
    private function orderDefaults($query, array $columns, ?string $except): void
    {
        foreach ($columns as $column) {
            if ($column !== $except) {
                $query->orderBy($column);
            }
        }
    }

    /**
     * URL shortcut "Buat Pengajuan" dari 1 baris Tarif (Sewa Truk atau Kiriman Rutin) di Detail
     * Perusahaan — null kalau bukan KG. TANPA syarat cabang: endpoint yang dipanggil wizard
     * setelah `perusahaan_id` diisi (`kendaraan-by-perusahaan` utk sewa_truk, `rate-card` utk
     * pengiriman_rutin — lihat `PengajuanController`) udah otomatis nge-scope ke cabang KG
     * sendiri, apa pun baris yang diklik. Beda dari shortcut di tabel Kendaraan
     * (`kendaraanItems()`): baris Tarif = vendor_skill (perusahaan+cabang+skill), BUKAN unit
     * truk spesifik, jadi nggak ada id_kendaraan buat di-prefill — query param `perusahaan_id`
     * (bukan `id`) sengaja beda nama supaya app.js tahu ini jalur "pilih/tambah kendaraan" atau
     * "resolve rate-card vendor", bukan jalur "kendaraan sudah dipilih" — lihat App.init() di
     * resources/js/app.js. `$skillId`/`$harga` cuma dipakai jenis `sewa_truk` (pre-centang skill
     * & starting value harga di form tambah kendaraan baru); `pengiriman_rutin` nggak butuh itu,
     * tarif per-item di-resolve sendiri oleh `resolveRateCardForVendor()`.
     */
    private function tarifPengajuanUrl(PerusahaanEkspedisi $perusahaan, string $jenis, ?int $skillId = null, ?float $harga = null, ?string $skillNama = null, ?int $idVendorSkill = null): ?string
    {
        if (auth()->user()->userUtility?->role !== 'KG') {
            return null;
        }

        $params = [
            'perusahaan_id' => $perusahaan->id_perusahaan,
            'nama_perusahaan' => $perusahaan->nama_perusahaan,
            'badan_usaha' => $perusahaan->badan_usaha,
            'no_telepon' => $perusahaan->no_telepon,
            'alamat_kantor' => $perusahaan->alamat_kantor,
            'jenis' => $jenis,
        ];
        if ($skillId !== null) {
            $params['skill'] = $skillId;
        }
        if ($harga !== null) {
            $params['harga'] = (int) $harga;
        }
        // Nama area ikut dibawa — app.js menambah pilihan sintetis kalau area ini tidak ada di
        // daftar area cabang (lihat resolveVendorSkillIds()).
        if ($skillNama !== null) {
            $params['skill_nama'] = $skillNama;
        }
        // id_vendor_skill baris ini — app.js mengambil tarif kombinasi ini saja di langkah
        // "Area & Tarif", bukan gabungan semua area vendor.
        if ($idVendorSkill !== null) {
            $params['id_vendor_skill'] = $idVendorSkill;
        }

        return route('pengajuan.kg', $params);
    }

    /**
     * Opsi facet filter (Cabang/Skill/Badan Usaha) — semua cabang, cabang user paling atas.
     * $selectedCabang: kalau ada, opsi Area/Skill dipersempit cuma yang ada di cabang² itu
     * (cascading — konsisten sama filter Cabang yang lagi dicentang user).
     */
    private function facetOptions(array $own, array $selectedCabang = []): array
    {
        $db = DB::connection('sqlsrv');
        // KG hanya melihat cabang sendiri, jadi opsi filter Cabang lain tidak ditampilkan.
        $isKg = auth()->user()?->userUtility?->role === 'KG';

        $cabangQuery = $db->table('sesi_master_cabang')->whereNotNull('Name')->orderBy('Name');
        if ($isKg) {
            $own ? $cabangQuery->whereIn('Code', $own) : $cabangQuery->whereRaw('1 = 0');
        }
        $cabang = $cabangQuery
            ->get(['Code', 'Name'])
            ->map(fn ($c) => [
                'value' => $c->Code,
                'label' => "{$c->Code} — {$c->Name}".(in_array($c->Code, $own, true) ? ' (cabang Anda)' : ''),
                'mine' => in_array($c->Code, $own, true),
            ])
            // sortBy stabil: cabang user di atas, sisanya tetap urut nama.
            ->sortBy(fn ($c) => $c['mine'] ? 0 : 1)
            ->map(fn ($c) => ['value' => $c['value'], 'label' => $c['label']])
            ->values()
            ->all();

        $skill = $this->skillFacetOptions($selectedCabang);

        // Enum tetap (bukan query distinct()) — selalu semua nilai, walau datanya kosong.
        $badanUsaha = collect(self::BADAN_USAHA_OPTIONS)->map(fn ($b) => ['value' => $b, 'label' => $b])->all();

        return [
            ['key' => 'cabang',      'label' => 'Cabang',      'options' => $cabang,      'count' => count($cabang)],
            // 'requires' => 'cabang' — starting point render pertama (no-JS fallback); interaktivitas
            // instan tanpa reload ditangani script di pill-filter-modal.blade.php via endpoint AJAX.
            ['key' => 'skill',       'label' => 'Area Kirim',  'options' => $skill,       'count' => count($skill), 'requires' => 'cabang'],
            ['key' => 'badan_usaha', 'label' => 'Badan Usaha', 'options' => $badanUsaha,  'count' => count($badanUsaha)],
        ];
    }

    /** Opsi facet Area Kirim (nama_skill) — dipersempit ke cabang² di $selectedCabang kalau ada (cascading). */
    private function skillFacetOptions(array $selectedCabang = []): array
    {
        return DB::connection('sqlsrv')->table('sesi_perusahaan_skill as ps')
            ->join('sesi_master_skill as ms', 'ms.id_skill', '=', 'ps.id_skill')
            ->where('ps.flag', true)->where('ms.flag', true)
            ->when($selectedCabang, fn ($q) => $q->whereIn('ps.cabang_code', $selectedCabang))
            ->distinct()->orderBy('ms.nama_skill')
            ->get(['ms.id_skill', 'ms.nama_skill'])
            ->map(fn ($s) => ['value' => (string) $s->id_skill, 'label' => $s->nama_skill])
            ->all();
    }

    /** Normalisasi input filter array dari query string: buang kosong/non-scalar, trim. */
    private function cleanList($value): array
    {
        return collect((array) $value)
            ->filter(fn ($v) => is_string($v) || is_numeric($v))
            ->map(fn ($v) => trim((string) $v))
            ->filter(fn ($v) => $v !== '')
            ->unique()
            ->values()
            ->all();
    }

    /** Parse angka non-negatif dari input filter range; null kalau kosong/tidak valid. */
    private function cleanNumber($value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        $normalized = str_replace(['.', ','], '', trim((string) $value));

        return ctype_digit($normalized) ? (float) $normalized : null;
    }
}

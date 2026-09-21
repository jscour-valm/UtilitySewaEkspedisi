<?php

namespace App\Http\Controllers;

use App\Helpers\FormatHelper;
use App\Models\JenisBarangKiriman;
use App\Models\PerusahaanEkspedisi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Halaman perusahaan terpadu — gantiin /kendaraan (KG) dan 2 halaman "Kelola
 * Tarif" (Sewa Truk & Kiriman Rutin). Form edit tarif TETAP di
 * TarifKirimanRutinController (cuma route name-nya yang pindah ke perusahaan.*).
 *
 * - index(): toggle `tab` = semua | sewa-truk | kiriman-rutin.
 *   Tab "semua"  → 1 baris per PERUSAHAAN (ringkasan, tanpa harga inline).
 *   Tab lainnya  → 1 baris per vendor_skill (perusahaan+cabang+skill), sama
 *                  bentuknya kayak tabel Kelola Tarif yang lama.
 * - show(): Detail Perusahaan (profil + dokumen + kendaraan + kedua jenis tarif).
 *
 * Scoping non-global (KG/WM): perusahaan dianggap "milik" cabang user kalau
 * punya sesi_perusahaan_skill (termasuk placeholder vendor baru dari wizard)
 * ATAU sesi_unit_kendaraan di cabang itu — sesi_perusahaan_ekspedisi sendiri
 * nggak punya kolom cabang (entity global).
 */
class PerusahaanController extends Controller
{
    private const TABS = ['semua', 'sewa-truk', 'kiriman-rutin'];
    private const PER_PAGE = 25;

    // ------------------------------------------------------------------
    // INDEX
    // ------------------------------------------------------------------

    public function index(Request $request)
    {
        $tab = $request->get('tab');
        $tab = in_array($tab, self::TABS, true) ? $tab : 'semua';
        $search = trim((string) $request->get('search', ''));
        $filters = [
            'cabang'      => $this->cleanList($request->input('cabang')),
            'skill'       => array_map('intval', $this->cleanList($request->input('skill'))),
            'badan_usaha' => $this->cleanList($request->input('badan_usaha')),
        ];
        $scope = $this->cabangScope();
        $sortOrder = strtolower((string) $request->get('order', 'asc')) === 'desc' ? 'desc' : 'asc';
        $requestedSort = (string) $request->get('sort', '');

        $data = match ($tab) {
            'sewa-truk'     => $this->indexSewaTruk($search, $filters, $scope, $requestedSort, $sortOrder),
            'kiriman-rutin' => $this->indexKirimanRutin($search, $filters, $scope, $requestedSort, $sortOrder),
            default         => $this->indexSemua($search, $filters, $scope, $requestedSort, $sortOrder),
        };

        return view('pages.perusahaan.index', array_merge($data, [
            'tab'       => $tab,
            'search'    => $search,
            'filters'   => $filters,
            'facets'    => $this->facetOptions($scope),
            'sortOrder' => $sortOrder,
        ]));
    }

    private function indexSemua(string $search, array $f, ?array $scope, string $requestedSort, string $sortOrder): array
    {
        $db = DB::connection('sqlsrv');

        $query = $db->table('sesi_perusahaan_ekspedisi as pe')->where('pe.flag', true);

        if ($scope !== null) {
            $this->whereLinkedToCabang($query, $scope);
        }

        if ($search !== '') {
            $like = "%{$search}%";
            $query->where(function ($w) use ($like) {
                $w->where('pe.nama_perusahaan', 'like', $like)
                  ->orWhere('pe.badan_usaha', 'like', $like)
                  ->orWhereExists(function ($s) use ($like) {
                      $s->select(DB::raw(1))->from('sesi_perusahaan_skill as ps')
                        ->join('sesi_master_skill as ms', 'ms.id_skill', '=', 'ps.id_skill')
                        ->whereColumn('ps.id_perusahaan', 'pe.id_perusahaan')
                        ->where('ps.flag', true)
                        ->where(function ($x) use ($like) {
                            $x->where('ms.nama_skill', 'like', $like)->orWhere('ps.cabang_code', 'like', $like);
                        });
                  });
            });
        }

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

        // "Diperbarui" = paling baru antara profil perusahaan, tarif (vendor_skill), dan
        // unit kendaraannya — biar tarif/kendaraan yang baru diedit ikut ngangkat baris.
        $lastUpdate = "(SELECT MAX(d) FROM (VALUES
            (pe.updated_at),
            ((SELECT MAX(x.updated_at) FROM sesi_perusahaan_skill x WHERE x.id_perusahaan = pe.id_perusahaan AND x.flag = 1)),
            ((SELECT MAX(k.updated_at) FROM sesi_unit_kendaraan k WHERE k.id_perusahaan = pe.id_perusahaan AND k.flag = 1))
        ) AS t(d))";

        $query->select('pe.id_perusahaan', 'pe.nama_perusahaan', 'pe.badan_usaha')
              ->selectRaw("{$lastUpdate} as last_update");

        $sortMap = ['nama' => 'pe.nama_perusahaan', 'badan_usaha' => 'pe.badan_usaha', 'diperbarui' => 'last_update'];
        $sortBy = isset($sortMap[$requestedSort]) ? $requestedSort : null;
        if ($sortBy) {
            $query->orderBy($sortMap[$sortBy], $sortOrder);
        }
        $this->orderDefaults($query, ['pe.nama_perusahaan', 'pe.id_perusahaan'], $sortBy ? $sortMap[$sortBy] : null);

        $rows = $query->paginate(self::PER_PAGE)->withQueryString();

        $ids = collect($rows->items())->pluck('id_perusahaan')->all();
        $rel = $this->loadRelations($ids, $scope);
        $vsBy = $rel['vs']->groupBy('id_perusahaan');
        $unitsBy = $rel['units']->groupBy('id_perusahaan');

        $rows->getCollection()->transform(function ($row) use ($rel, $vsBy, $unitsBy) {
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

            $row->cabang_count = $vs->pluck('cabang_code')->merge($units->pluck('id_cabang'))->filter()->unique()->count();
            $row->area_count = $areas->unique()->count();
            $row->has_sewa = $vs->contains(fn ($v) => $v->harga_sewa !== null);
            $row->has_kiriman = $vs->contains(fn ($v) => isset($rel['tarifVsIds'][$v->id_vendor_skill]));
            $row->kendaraan = $this->kendaraanItems($units, $vs, $rel['skillNames'], $row->nama_perusahaan);

            return $row;
        });

        return ['rows' => $rows, 'sortBy' => $sortBy];
    }

    private function indexSewaTruk(string $search, array $f, ?array $scope, string $requestedSort, string $sortOrder): array
    {
        $query = $this->vendorSkillBase($search, $f, $scope)->whereNotNull('ps.harga_sewa');

        $sortMap = [
            'kode_area'   => 'mc.Area',
            'cabang'      => 'ps.cabang_code',
            'nama'        => 'pe.nama_perusahaan',
            'badan_usaha' => 'pe.badan_usaha',
            'area_kirim'  => 'ms.nama_skill',
            'harga'       => 'ps.harga_sewa',
            'dibuat'      => 'ps.created_at',
        ];
        $sortBy = null;
        if ($requestedSort === 'diupdate') {
            $sortBy = 'diupdate';
            $query->orderByRaw('COALESCE(ps.update_date_source, ps.updated_at) ' . $sortOrder);
        } elseif (isset($sortMap[$requestedSort])) {
            $sortBy = $requestedSort;
            $query->orderBy($sortMap[$sortBy], $sortOrder);
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

    private function indexKirimanRutin(string $search, array $f, ?array $scope, string $requestedSort, string $sortOrder): array
    {
        $jenisBarangList = JenisBarangKiriman::where('flag', true)->orderBy('nama_barang')->get(['id_jenis_barang', 'nama_barang']);

        $query = $this->vendorSkillBase($search, $f, $scope)->whereExists(function ($q) {
            $q->select(DB::raw(1))
              ->from('sesi_tarif_kiriman_rutin as t')
              ->whereColumn('t.id_vendor_skill', 'ps.id_vendor_skill')
              ->where('t.flag', true);
        });

        $sortMap = [
            'kode_area'  => 'mc.Area',
            'cabang'     => 'ps.cabang_code',
            'nama'       => 'pe.nama_perusahaan',
            'area_kirim' => 'ms.nama_skill',
            'diupdate'   => 'ps.updated_at',
            'dibuat'     => 'ps.created_at',
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
                . '(SELECT tt.biaya_per_unit FROM sesi_tarif_kiriman_rutin tt WHERE tt.id_vendor_skill = ps.id_vendor_skill AND tt.id_jenis_barang = ? AND tt.flag = 1) ' . $sortOrder,
                [(int) $m[1], (int) $m[1]]
            );
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
     * plus search, scoping cabang, dan facet filter (server-side biar tetap
     * benar dikombinasi sama pagination).
     */
    private function vendorSkillBase(string $search, array $f, ?array $scope)
    {
        $query = DB::connection('sqlsrv')->table('sesi_perusahaan_skill as ps')
            ->join('sesi_perusahaan_ekspedisi as pe', 'ps.id_perusahaan', '=', 'pe.id_perusahaan')
            ->join('sesi_master_skill as ms', 'ps.id_skill', '=', 'ms.id_skill')
            ->leftJoin('sesi_master_cabang as mc', function ($join) {
                $join->on('ps.cabang_code', '=', DB::raw('mc.Code COLLATE SQL_Latin1_General_CP1_CI_AS'));
            })
            ->where('ps.flag', true)
            ->where('pe.flag', true);

        if ($search !== '') {
            $like = "%{$search}%";
            $query->where(function ($q) use ($like) {
                $q->where('pe.nama_perusahaan', 'like', $like)
                  ->orWhere('ps.cabang_code', 'like', $like)
                  ->orWhere('ms.nama_skill', 'like', $like);
            });
        }

        if ($scope !== null) {
            $query->whereIn('ps.cabang_code', $scope);
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
        $scope = $this->cabangScope();

        if ($scope !== null) {
            $linked = DB::connection('sqlsrv')->table('sesi_perusahaan_ekspedisi as pe')
                ->where('pe.id_perusahaan', $perusahaan->id_perusahaan);
            $this->whereLinkedToCabang($linked, $scope);
            abort_unless($linked->exists(), 403, 'Perusahaan ini tidak terkait dengan cabang Anda');
        }

        $db = DB::connection('sqlsrv');
        $rel = $this->loadRelations([$perusahaan->id_perusahaan], $scope);

        $vendorSkillBase = fn () => $db->table('sesi_perusahaan_skill as ps')
            ->join('sesi_master_skill as ms', 'ps.id_skill', '=', 'ms.id_skill')
            ->leftJoin('sesi_master_cabang as mc', function ($join) {
                $join->on('ps.cabang_code', '=', DB::raw('mc.Code COLLATE SQL_Latin1_General_CP1_CI_AS'));
            })
            ->where('ps.id_perusahaan', $perusahaan->id_perusahaan)
            ->where('ps.flag', true)
            ->when($scope !== null, fn ($q) => $q->whereIn('ps.cabang_code', $scope))
            ->orderBy('ps.cabang_code')->orderBy('ms.nama_skill');

        $tarifSewa = $vendorSkillBase()->whereNotNull('ps.harga_sewa')
            ->get(['ps.id_vendor_skill', 'ps.cabang_code', 'mc.Name as nama_cabang', 'ms.nama_skill', 'ps.harga_sewa', 'ps.update_date_source', 'ps.updated_at']);

        $tarifKiriman = $vendorSkillBase()->whereExists(function ($q) {
                $q->select(DB::raw(1))->from('sesi_tarif_kiriman_rutin as t')
                  ->whereColumn('t.id_vendor_skill', 'ps.id_vendor_skill')->where('t.flag', true);
            })
            ->get(['ps.id_vendor_skill', 'ps.cabang_code', 'mc.Name as nama_cabang', 'ms.nama_skill', 'ps.updated_at']);

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
            'back_url'   => route('perusahaan.index'),
            'back_label' => 'Perusahaan',
            'title'      => $perusahaan->nama_perusahaan,
        ];

        return view('pages.perusahaan.show', compact(
            'perusahaan', 'kendaraan', 'tarifSewa', 'tarifKiriman', 'hargaByVs',
            'jenisBarangCols', 'belumAdaTarif', 'cabangCount', 'areaCount', 'breadcrumb'
        ));
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

    /** null = global access (WH/DCI, nggak difilter); array = kode cabang yang boleh dilihat user. */
    private function cabangScope(): ?array
    {
        $user = auth()->user();
        if ($user->isGlobalAccess()) {
            return null;
        }

        $ids = $user->getCabangIds() ?: array_values(array_filter([$user->getCabangId()]));

        return $ids ?: ['__none__'];
    }

    /** Batasi query `sesi_perusahaan_ekspedisi as pe` ke perusahaan yang ter-link ke salah satu cabang di $cabangIds. */
    private function whereLinkedToCabang($query, array $cabangIds): void
    {
        $query->where(function ($w) use ($cabangIds) {
            $w->whereExists(function ($s) use ($cabangIds) {
                $s->select(DB::raw(1))->from('sesi_perusahaan_skill as sc')
                  ->whereColumn('sc.id_perusahaan', 'pe.id_perusahaan')
                  ->where('sc.flag', true)
                  ->whereIn('sc.cabang_code', $cabangIds);
            })->orWhereExists(function ($s) use ($cabangIds) {
                $s->select(DB::raw(1))->from('sesi_unit_kendaraan as uc')
                  ->whereColumn('uc.id_perusahaan', 'pe.id_perusahaan')
                  ->where('uc.flag', true)
                  ->whereIn('uc.id_cabang', $cabangIds);
            });
        });
    }

    /** Ambil vendor_skill, unit kendaraan, dan penanda tarif kiriman utk sekumpulan perusahaan sekaligus (hindari N+1). */
    private function loadRelations(array $ids, ?array $scope): array
    {
        $db = DB::connection('sqlsrv');

        if (empty($ids)) {
            return ['vs' => collect(), 'units' => collect(), 'tarifVsIds' => [], 'skillNames' => collect()];
        }

        $vs = $db->table('sesi_perusahaan_skill')
            ->whereIn('id_perusahaan', $ids)->where('flag', true)
            ->when($scope !== null, fn ($q) => $q->whereIn('cabang_code', $scope))
            ->get(['id_vendor_skill', 'id_perusahaan', 'id_skill', 'cabang_code', 'harga_sewa']);

        $units = $db->table('sesi_unit_kendaraan')
            ->whereIn('id_perusahaan', $ids)->where('flag', true)
            ->when($scope !== null, fn ($q) => $q->whereIn('id_cabang', $scope))
            ->orderBy('id_cabang')->orderBy('jenis_kendaraan')->orderBy('id_kendaraan')
            ->get(['id_kendaraan', 'id_perusahaan', 'id_cabang', 'id_skill', 'jenis_kendaraan', 'plat_nomor_truk', 'muatan_maksimal']);

        $tarifVsIds = $vs->isEmpty() ? [] : $db->table('sesi_tarif_kiriman_rutin as t')
            ->join('sesi_perusahaan_skill as p', 'p.id_vendor_skill', '=', 't.id_vendor_skill')
            ->whereIn('p.id_perusahaan', $ids)
            ->where('t.flag', true)->where('p.flag', true)
            ->distinct()->pluck('t.id_vendor_skill')->flip()->all();

        $skillNames = $db->table('sesi_master_skill')->pluck('nama_skill', 'id_skill');

        return compact('vs', 'units', 'tarifVsIds', 'skillNames');
    }

    /**
     * Unit kendaraan → array siap render (index hover & Detail Perusahaan).
     * `pengajuan_url` cuma diisi buat role KG (shortcut ke wizard Pengajuan Sewa,
     * bentuk query param sama persis kayak yang dibaca app.js). "harga" di URL
     * itu cuma info referensi di step 2 — diambil dari patokan master
     * (sesi_perusahaan_skill) kalau ada, BUKAN harga pengajuan terakhir.
     */
    private function kendaraanItems($units, $vs, $skillNames, string $namaPerusahaan): array
    {
        $isKg = auth()->user()->userUtility?->role === 'KG';

        $patokan = [];
        foreach ($vs as $v) {
            if ($v->harga_sewa !== null) {
                $patokan[$v->id_perusahaan . '|' . $v->cabang_code . '|' . (int) $v->id_skill] = (float) $v->harga_sewa;
            }
        }

        return $units->map(function ($u) use ($isKg, $patokan, $skillNames, $namaPerusahaan) {
            $skillIds = array_values(array_filter(
                array_map('trim', explode(',', (string) $u->id_skill)),
                fn ($t) => ctype_digit($t)
            ));
            $skills = collect($skillIds)->map(fn ($sid) => $skillNames->get((int) $sid))->filter()->values()->all();

            $hargaRef = null;
            foreach ($skillIds as $sid) {
                $key = $u->id_perusahaan . '|' . $u->id_cabang . '|' . (int) $sid;
                if (isset($patokan[$key])) {
                    $hargaRef = $patokan[$key];
                    break;
                }
            }

            $muatan = $u->muatan_maksimal !== null ? FormatHelper::ton($u->muatan_maksimal) : '—';

            return [
                'id'     => $u->id_kendaraan,
                'cabang' => $u->id_cabang,
                'jenis'  => $u->jenis_kendaraan,
                'plat'   => $u->plat_nomor_truk,
                'muatan' => $muatan,
                'skills' => $skills,
                'pengajuan_url' => $isKg ? route('pengajuan.kg', [
                    'id'         => $u->id_kendaraan,
                    'nama'       => $namaPerusahaan,
                    'kendaraan'  => $u->jenis_kendaraan,
                    'muatan'     => $muatan,
                    'muatan_raw' => $u->muatan_maksimal,
                    'harga'      => $hargaRef !== null ? FormatHelper::rupiah($hargaRef) : 'Rp 0',
                    'skill'      => $u->id_skill,
                ]) : null,
            ];
        })->all();
    }

    /** Opsi facet filter (Cabang/Skill/Badan Usaha), sudah di-scope ke cabang user kalau bukan global. */
    private function facetOptions(?array $scope): array
    {
        $db = DB::connection('sqlsrv');

        $cabang = $db->table('sesi_master_cabang')
            ->whereNotNull('Name')
            ->when($scope !== null, fn ($q) => $q->whereIn('Code', $scope))
            ->orderBy('Name')
            ->get(['Code', 'Name'])
            ->map(fn ($c) => ['value' => $c->Code, 'label' => "{$c->Code} — {$c->Name}"])
            ->all();

        $skill = $db->table('sesi_perusahaan_skill as ps')
            ->join('sesi_master_skill as ms', 'ms.id_skill', '=', 'ps.id_skill')
            ->where('ps.flag', true)->where('ms.flag', true)
            ->when($scope !== null, fn ($q) => $q->whereIn('ps.cabang_code', $scope))
            ->distinct()->orderBy('ms.nama_skill')
            ->get(['ms.id_skill', 'ms.nama_skill'])
            ->map(fn ($s) => ['value' => (string) $s->id_skill, 'label' => $s->nama_skill])
            ->all();

        $badanUsaha = $db->table('sesi_perusahaan_ekspedisi')
            ->where('flag', true)->whereNotNull('badan_usaha')->where('badan_usaha', '!=', '-')
            ->distinct()->orderBy('badan_usaha')
            ->pluck('badan_usaha')
            ->map(fn ($b) => ['value' => $b, 'label' => $b])
            ->all();

        return [
            ['key' => 'cabang',      'label' => 'Cabang',      'options' => $cabang],
            ['key' => 'skill',       'label' => 'Area / Skill', 'options' => $skill],
            ['key' => 'badan_usaha', 'label' => 'Badan Usaha', 'options' => $badanUsaha],
        ];
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
}

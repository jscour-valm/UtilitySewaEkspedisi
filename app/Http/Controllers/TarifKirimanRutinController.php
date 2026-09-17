<?php

namespace App\Http\Controllers;

use App\Models\TarifKirimanRutin;
use App\Models\JenisBarangKiriman;
use App\Models\PerusahaanEkspedisi;
use App\Models\PerusahaanSkill;
use App\Models\Kendaraan;
use App\Http\Controllers\Concerns\ManagesVendorMasterData;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TarifKirimanRutinController extends Controller
{
    use ManagesVendorMasterData;


    /**
     * Halaman list "master tabel Sewa Truk" (role: DCI) — kolom ngikutin
     * Excel sumbernya (lihat ImportTarifSewaTrukCommand). 1 baris =
     * 1 sesi_perusahaan_skill. Nama Cabang & Kode Area di-JOIN dari
     * sesi_master_cabang (via cabang_code) — bukan disimpan ulang.
     * Filter whereNotNull('harga_sewa') biar baris yang cuma relevan buat
     * Kiriman Rutin (belum pernah punya harga sewa truk) ga ikut nongol.
     *
     * Kolom Revisi/Tanggal Revisi/KTP-NPWP sengaja TIDAK ditampilkan lagi
     * (keputusan Jo 16 Sept) — dianggap terwakili oleh Harga Sewa, Diupdate,
     * dan identitas_owner (foto). Datanya tetap ada di DB, cuma nggak dipakai
     * di UI manapun lagi (list & edit).
     */
    public function indexSewaTruk(Request $request)
    {
        $search = trim((string) $request->get('search', ''));

        $query = DB::connection('sqlsrv')->table('sesi_perusahaan_skill as ps')
            ->join('sesi_perusahaan_ekspedisi as pe', 'ps.id_perusahaan', '=', 'pe.id_perusahaan')
            ->join('sesi_master_skill as ms', 'ps.id_skill', '=', 'ms.id_skill')
            ->leftJoin('sesi_master_cabang as mc', function ($join) {
                $join->on('ps.cabang_code', '=', DB::raw('mc.Code COLLATE SQL_Latin1_General_CP1_CI_AS'));
            })
            ->where('ps.flag', true)
            ->whereNotNull('ps.harga_sewa');

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('pe.nama_perusahaan', 'like', "%{$search}%")
                  ->orWhere('ps.cabang_code', 'like', "%{$search}%")
                  ->orWhere('ms.nama_skill', 'like', "%{$search}%");
            });
        }

        $rows = $query->select(
                'ps.id_vendor_skill',
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
            ->orderBy('pe.nama_perusahaan')
            ->orderBy('ps.cabang_code')
            ->paginate(25)
            ->withQueryString();

        // identitas_owner: query builder mentah (bukan Eloquent) jadi nggak
        // otomatis kena cast 'array' kayak di model PerusahaanEkspedisi —
        // decode manual di sini. Diupdate: pakai Update Date asli dari Excel
        // kalau ada (update_date_source), fallback ke updated_at kalau nggak.
        $rows->getCollection()->transform(function ($row) {
            $row->identitas_owner = json_decode($row->identitas_owner ?? '[]', true) ?: [];
            $row->diupdate = $row->update_date_source ?? $row->updated_at;
            return $row;
        });

        return view('pages.tarif-sewa-truk.index', compact('rows', 'search'));
    }

    public function editSewaTruk($id)
    {
        $vendorSkill = PerusahaanSkill::with('perusahaan')->findOrFail($id);
        ['kendaraanList' => $kendaraanList, 'skillList' => $skillList] = $this->vendorKendaraanData($vendorSkill);
        $cabangList = $this->cabangOptions();
        $tarifSkillList = $this->skillOptionsForCabang($vendorSkill->cabang_code);
        $isSkillInScope = $tarifSkillList->contains('id_skill', $vendorSkill->id_skill);

        $namaPerusahaan = $vendorSkill->perusahaan->nama_perusahaan ?? null;
        $breadcrumb = [
            'back_url' => route('kelola-tarif.sewa-truk'),
            'back_label' => 'Master Tabel Sewa Truk',
            'title' => ($namaPerusahaan && $namaPerusahaan !== '-') ? $namaPerusahaan : 'Edit Tarif Sewa Truk',
        ];

        return view('pages.tarif-sewa-truk.edit', compact('vendorSkill', 'kendaraanList', 'skillList', 'cabangList', 'tarifSkillList', 'isSkillInScope', 'breadcrumb'));
    }

    public function updateSewaTruk(Request $request, $id)
    {
        $request->validate([
            'harga_sewa'  => 'nullable|numeric|min:0',
            'cabang_code' => 'required|string|max:10|exists:sqlsrv.dbo.sesi_master_cabang,Code',
            'id_skill'    => 'nullable|integer|exists:sqlsrv.dbo.sesi_master_skill,id_skill',
            'skill_baru'  => 'nullable|string|max:255',
        ]);

        $vendorSkill = PerusahaanSkill::findOrFail($id);

        // skill_baru (dari checklist step2-style "Tambah Area Baru") menang kalau
        // diisi — resolveSkillId() sekaligus registrasi ulang ke sesi_cabang_skill
        // kalau belum ada, jadi baris lama hasil import yang "salah tempat" (id_skill
        // valid di master tapi belum terdaftar di cabang_skill cabang ini) otomatis
        // ke-sinkronkan begitu di-submit ulang.
        $idSkill = $request->filled('skill_baru')
            ? $this->resolveSkillId($request->skill_baru, $request->cabang_code)
            : $request->id_skill;

        if (!$idSkill) {
            return back()->withErrors(['error' => 'Pilih atau tambahkan Skill/Area Kirim.'])->withInput();
        }

        if ($conflict = $this->checkVendorSkillConflict($vendorSkill, $request->cabang_code, $idSkill)) {
            return $conflict;
        }

        try {
            $vendorSkill->update([
                'harga_sewa'  => $request->harga_sewa,
                'cabang_code' => $request->cabang_code,
                'id_skill'    => $idSkill,
            ]);

            return redirect()->route('kelola-tarif.sewa-truk')->with('success', 'Data Sewa Truk berhasil diperbarui.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Gagal menyimpan: ' . $e->getMessage()])->withInput();
        }
    }

    /**
     * Master data cabang buat dropdown "Cabang" di halaman edit — sumbernya
     * sesi_master_cabang (tabel eksternal IT), ditampilkan "Code — Name".
     */
    private function cabangOptions()
    {
        return DB::connection('sqlsrv')->table('sesi_master_cabang')
            ->whereNotNull('Name')
            ->orderBy('Name')
            ->get(['Code', 'Name']);
    }

    /**
     * Skill/area yang terdaftar di sesi_cabang_skill utk 1 cabang tertentu —
     * pola sama kayak PengajuanController::getSkillList(), tapi cabang-nya
     * parameter bebas (bukan cabang user login) karena di halaman edit tarif
     * ini DCI bisa ganti Cabang baris tarif kapan saja dari dropdown, dan
     * dropdown Skill/Area di atasnya harus ikut ke-scope ulang.
     */
    private function skillOptionsForCabang(?string $cabangCode)
    {
        if (!$cabangCode) {
            return collect();
        }

        return DB::connection('sqlsrv')->table('sesi_cabang_skill as cs')
            ->join('sesi_master_skill as ms', 'ms.id_skill', '=', 'cs.id_skill')
            ->where('cs.cabang_code', $cabangCode)
            ->where('cs.flag', true)
            ->where('ms.flag', true)
            ->orderBy('ms.nama_skill')
            ->get(['ms.id_skill', 'ms.nama_skill']);
    }

    /**
     * GET /api/master-skill/by-cabang/{cabang_code} — dipakai dropdown "Cabang"
     * di halaman edit Sewa Truk/Kiriman Rutin buat refresh dropdown "Skill/Area
     * Kirim" on-the-fly tiap Cabang diganti (dependent dropdown, pola sama
     * kayak wizard Pengajuan Sewa tapi cabang-nya dinamis bukan fixed user login).
     */
    public function skillByCabang($cabangCode)
    {
        return response()->json($this->skillOptionsForCabang($cabangCode));
    }

    /**
     * Cek constraint unique (id_perusahaan+id_skill+cabang_code) sebelum
     * update — dipanggil dari updateSewaTruk()/updateKirimanRutin() pas Cabang
     * atau Skill/Area diganti dari dropdown. Balik response redirect kalau
     * bentrok (dipakai langsung sbg return statement caller), null kalau aman.
     */
    private function checkVendorSkillConflict(PerusahaanSkill $vendorSkill, string $cabangCode, int $idSkill)
    {
        $exists = PerusahaanSkill::where('id_perusahaan', $vendorSkill->id_perusahaan)
            ->where('id_skill', $idSkill)
            ->where('cabang_code', $cabangCode)
            ->where('id_vendor_skill', '!=', $vendorSkill->id_vendor_skill)
            ->exists();

        if ($exists) {
            return back()->withErrors(['error' => 'Kombinasi vendor + cabang + area ini sudah ada di baris lain.'])->withInput();
        }

        return null;
    }

    /**
     * Halaman list "master tabel Kiriman Rutin" (role: DCI) — bentuk wide/
     * pivot ngikutin Excel sumbernya (1 kolom per jenis barang, lihat
     * ImportTarifKirimanRutinWideCommand). 1 baris = 1 sesi_perusahaan_skill
     * yang punya minimal 1 tarif kiriman rutin (whereExists) — beda dari
     * Sewa Truk, CSV ini ga punya kolom Badan Usaha/KTP-NPWP/Revisi.
     */
    public function indexKirimanRutin(Request $request)
    {
        $search = trim((string) $request->get('search', ''));

        $jenisBarangList = JenisBarangKiriman::where('flag', true)->orderBy('nama_barang')->get(['id_jenis_barang', 'nama_barang']);

        $query = DB::connection('sqlsrv')->table('sesi_perusahaan_skill as ps')
            ->join('sesi_perusahaan_ekspedisi as pe', 'ps.id_perusahaan', '=', 'pe.id_perusahaan')
            ->join('sesi_master_skill as ms', 'ps.id_skill', '=', 'ms.id_skill')
            ->leftJoin('sesi_master_cabang as mc', function ($join) {
                $join->on('ps.cabang_code', '=', DB::raw('mc.Code COLLATE SQL_Latin1_General_CP1_CI_AS'));
            })
            ->where('ps.flag', true)
            ->whereExists(function ($q) {
                $q->select(DB::raw(1))
                  ->from('sesi_tarif_kiriman_rutin as t')
                  ->whereColumn('t.id_vendor_skill', 'ps.id_vendor_skill')
                  ->where('t.flag', true);
            });

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('pe.nama_perusahaan', 'like', "%{$search}%")
                  ->orWhere('ps.cabang_code', 'like', "%{$search}%")
                  ->orWhere('ms.nama_skill', 'like', "%{$search}%");
            });
        }

        $rows = $query->select(
                'ps.id_vendor_skill',
                'pe.nama_perusahaan',
                'ps.cabang_code',
                'mc.Name as nama_cabang',
                'mc.Area as kode_area',
                'ms.nama_skill as area_kirim',
                'ps.updated_at',
                'ps.created_at'
            )
            ->orderBy('pe.nama_perusahaan')
            ->orderBy('ps.cabang_code')
            ->paginate(25)
            ->withQueryString();

        // Ambil semua tarif utk vendor-skill di halaman ini sekaligus (hindari N+1),
        // lalu index by id_vendor_skill supaya gampang di-pivot di view.
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

        return view('pages.tarif-kiriman-rutin.index', compact('rows', 'search', 'jenisBarangList'));
    }

    public function editKirimanRutin($id)
    {
        $vendorSkill = PerusahaanSkill::with('perusahaan')->findOrFail($id);
        $jenisBarangList = JenisBarangKiriman::where('flag', true)->orderBy('nama_barang')->get();
        $tarifExisting = TarifKirimanRutin::where('id_vendor_skill', $id)->where('flag', true)->get()->keyBy('id_jenis_barang');
        ['kendaraanList' => $kendaraanList, 'skillList' => $skillList] = $this->vendorKendaraanData($vendorSkill);
        $cabangList = $this->cabangOptions();
        $tarifSkillList = $this->skillOptionsForCabang($vendorSkill->cabang_code);
        $isSkillInScope = $tarifSkillList->contains('id_skill', $vendorSkill->id_skill);

        $namaPerusahaan = $vendorSkill->perusahaan->nama_perusahaan ?? null;
        $breadcrumb = [
            'back_url' => route('kelola-tarif.kiriman-rutin'),
            'back_label' => 'Master Tabel Kiriman Rutin',
            'title' => ($namaPerusahaan && $namaPerusahaan !== '-') ? $namaPerusahaan : 'Edit Tarif Kiriman Rutin',
        ];

        return view('pages.tarif-kiriman-rutin.edit', compact('vendorSkill', 'jenisBarangList', 'tarifExisting', 'kendaraanList', 'skillList', 'cabangList', 'tarifSkillList', 'isSkillInScope', 'breadcrumb'));
    }

    /**
     * Data buat seksi "Kendaraan" di halaman edit Sewa Truk & Kiriman Rutin
     * (16 Sept — dulu cuma tarif, sekarang sekalian bisa kelola profil
     * perusahaan & kendaraannya dari sini). Kendaraan di-scope ke CABANG yang
     * sama dengan baris tarif ini ($vendorSkill->cabang_code), bukan semua
     * kendaraan milik perusahaan ini lintas cabang.
     */
    private function vendorKendaraanData(PerusahaanSkill $vendorSkill): array
    {
        $kendaraanList = Kendaraan::where('id_perusahaan', $vendorSkill->id_perusahaan)
            ->where('id_cabang', $vendorSkill->cabang_code)
            ->where('flag', true)
            ->orderByDesc('updated_at')
            ->get();

        $skillList = DB::connection('sqlsrv')->table('sesi_master_skill')
            ->where('flag', true)
            ->orderBy('nama_skill')
            ->get(['id_skill', 'nama_skill']);

        return compact('kendaraanList', 'skillList');
    }

    public function updateKirimanRutin(Request $request, $id)
    {
        $request->validate([
            'harga'       => 'nullable|array',
            'harga.*'     => 'nullable|numeric|min:0',
            'cabang_code' => 'required|string|max:10|exists:sqlsrv.dbo.sesi_master_cabang,Code',
            'id_skill'    => 'nullable|integer|exists:sqlsrv.dbo.sesi_master_skill,id_skill',
            'skill_baru'  => 'nullable|string|max:255',
        ]);

        $vendorSkill = PerusahaanSkill::findOrFail($id);

        $idSkill = $request->filled('skill_baru')
            ? $this->resolveSkillId($request->skill_baru, $request->cabang_code)
            : $request->id_skill;

        if (!$idSkill) {
            return back()->withErrors(['error' => 'Pilih atau tambahkan Skill/Area Kirim.'])->withInput();
        }

        if ($conflict = $this->checkVendorSkillConflict($vendorSkill, $request->cabang_code, $idSkill)) {
            return $conflict;
        }

        try {
            DB::connection('sqlsrv')->beginTransaction();

            $vendorSkill->update(['cabang_code' => $request->cabang_code, 'id_skill' => $idSkill]);

            foreach ($request->input('harga', []) as $idJenisBarang => $biaya) {
                if ($biaya === null || $biaya === '') {
                    // Kosong = hapus tarif yang mungkin sudah ada (soft delete)
                    TarifKirimanRutin::where('id_vendor_skill', $id)
                        ->where('id_jenis_barang', $idJenisBarang)
                        ->update(['flag' => false]);
                    continue;
                }

                // withInactive(): reaktivasi kalau sebelumnya pernah dihapus lalu diisi lagi
                TarifKirimanRutin::withInactive()->updateOrCreate(
                    ['id_vendor_skill' => $id, 'id_jenis_barang' => $idJenisBarang],
                    ['biaya_per_unit' => $biaya, 'flag' => true]
                );
            }

            DB::connection('sqlsrv')->commit();

            return redirect()->route('kelola-tarif.kiriman-rutin')->with('success', 'Tarif Kiriman Rutin berhasil diperbarui.');
        } catch (\Exception $e) {
            DB::connection('sqlsrv')->rollBack();
            return back()->withErrors(['error' => 'Gagal menyimpan: ' . $e->getMessage()])->withInput();
        }
    }

    /**
     * Fetch daftar tarif kiriman rutin untuk 1 vendor-skill (vendor+skill/area
     * +cabang) tertentu — bisa juga terima array id_vendor_skill (dipakai
     * PengajuanController pas 1 cabang punya beberapa skill sekaligus).
     * GET /api/tarif-kiriman-rutin?id_vendor_skill={id}
     * GET /api/tarif-kiriman-rutin?id_vendor_skill[]={id1}&id_vendor_skill[]={id2}
     */
    public function list(Request $request)
    {
        $request->validate([
            'id_vendor_skill' => 'required',
            'id_vendor_skill.*' => 'integer',
        ]);

        try {
            $ids = collect((array) $request->id_vendor_skill)->map(fn ($v) => (int) $v);

            $tarif = TarifKirimanRutin::whereIn('id_vendor_skill', $ids)
                ->where('flag', true)
                ->with('jenisBarang:id_jenis_barang,nama_barang')
                ->orderBy('id_jenis_barang')
                ->get()
                ->map(function ($t) {
                    return [
                        'id_tarif' => $t->id_tarif,
                        'id_vendor_skill' => $t->id_vendor_skill,
                        'id_jenis_barang' => $t->id_jenis_barang,
                        'nama_barang' => $t->jenisBarang?->nama_barang,
                        'biaya_per_unit' => $t->biaya_per_unit,
                    ];
                });

            return response()->json([
                'success' => true,
                'tarif' => $tarif,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal fetch tarif: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Fetch semua baris sesi_perusahaan_skill milik 1 vendor — tiap baris =
     * 1 kombinasi cabang+skill/area yang bisa punya tarif & harga_sewa
     * sendiri-sendiri. Menggantikan rate-card lama (sesi_unit_kendaraan
     * plat_nomor_truk NULL, comma-CSV banyak skill jadi 1 baris).
     * GET /api/rate-card-kiriman-rutin?id_perusahaan_ekspedisi={id}
     */
    public function listRateCard(Request $request)
    {
        $request->validate(['id_perusahaan_ekspedisi' => 'required|integer']);

        try {
            $vendorSkills = PerusahaanSkill::where('id_perusahaan', $request->id_perusahaan_ekspedisi)
                ->where('flag', true)
                ->orderBy('cabang_code')
                ->get(['id_vendor_skill', 'id_perusahaan', 'id_skill', 'cabang_code'])
                ->map(function ($vs) {
                    return [
                        'id_vendor_skill' => $vs->id_vendor_skill,
                        'id_cabang' => $vs->cabang_code,
                        'id_skill' => $vs->id_skill,
                        'area_label' => $vs->nama_skill,
                    ];
                });

            return response()->json([
                'success' => true,
                'rate_card' => $vendorSkills,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal fetch rate card: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Fetch semua master skill/area aktif (dipakai buat checkbox pilih area
     * rate card baru) — beda dari PengajuanController::getSkillList yang
     * di-scope per cabang submitter, di sini DCI kelola lintas cabang jadi
     * tampilkan semua.
     * GET /api/master-skill
     */
    public function listSkill()
    {
        try {
            $skill = DB::connection('sqlsrv')->table('sesi_master_skill')
                ->where('flag', true)
                ->orderBy('nama_skill')
                ->get(['id_skill', 'nama_skill']);

            return response()->json([
                'success' => true,
                'skill' => $skill,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal fetch master skill: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Resolve-or-create baris sesi_perusahaan_skill utk kombinasi
     * vendor+cabang+area — dipakai halaman Kelola Tarif pas DCI mau nambah
     * tarif utk kombinasi yang belum pernah ada. 1 request bisa pilih
     * beberapa skill sekaligus (checkbox), tapi masing-masing jadi 1 baris
     * sesi_perusahaan_skill sendiri (bukan digabung jadi 1 CSV kayak
     * rate-card lama) — supaya tiap skill bisa punya tarif sendiri-sendiri.
     * POST /api/rate-card-kiriman-rutin
     */
    public function storeRateCard(Request $request)
    {
        $request->validate([
            'id_perusahaan_ekspedisi' => 'required|integer|exists:sqlsrv.dbo.sesi_perusahaan_ekspedisi,id_perusahaan',
            'id_cabang' => 'required|string|max:10',
            'id_skill' => 'required|array|min:1',
            'id_skill.*' => 'required|integer|exists:sqlsrv.dbo.sesi_master_skill,id_skill',
        ]);

        try {
            $idCabang = strtoupper(trim($request->id_cabang));

            $vendorSkills = collect($request->id_skill)->unique()->map(function ($skillId) use ($request, $idCabang) {
                return PerusahaanSkill::firstOrCreate(
                    [
                        'id_perusahaan' => $request->id_perusahaan_ekspedisi,
                        'id_skill' => $skillId,
                        'cabang_code' => $idCabang,
                    ],
                    ['flag' => true]
                );
            })->map(function (PerusahaanSkill $vs) {
                return [
                    'id_vendor_skill' => $vs->id_vendor_skill,
                    'id_cabang' => $vs->cabang_code,
                    'id_skill' => $vs->id_skill,
                    'area_label' => $vs->nama_skill,
                ];
            })->values();

            return response()->json([
                'success' => true,
                'message' => 'Rate card berhasil disiapkan',
                'rate_card' => $vendorSkills,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal membuat rate card: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Halaman kelola Jenis Barang Kiriman (Master Data, role DCI). Endpoint
     * CRUD-nya (listBarang/storeBarang/updateBarang/destroyBarang di bawah)
     * sudah lama ada — sebelumnya cuma dipakai sbg dropdown source di halaman
     * Kelola Tarif, sekarang dapet halaman kelola sendiri.
     */
    public function indexBarang()
    {
        $jenisBarang = JenisBarangKiriman::orderBy('nama_barang')->get();

        return view('pages.master.jenis-barang-kiriman', compact('jenisBarang'));
    }

    /**
     * Fetch daftar jenis barang yang aktif (untuk dropdown & tabel master)
     * GET /api/jenis-barang-kiriman
     */
    public function listBarang()
    {
        try {
            $barang = JenisBarangKiriman::where('flag', true)
                ->orderBy('nama_barang')
                ->get(['id_jenis_barang', 'nama_barang']);

            return response()->json([
                'success' => true,
                'barang' => $barang,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal fetch jenis barang: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Simpan tarif baru
     */
    public function store(Request $request)
    {
        $request->validate([
            'id_vendor_skill' => 'required|integer|exists:sqlsrv.dbo.sesi_perusahaan_skill,id_vendor_skill',
            'id_jenis_barang' => 'required|integer|exists:sqlsrv.dbo.sesi_jenis_barang_kiriman,id_jenis_barang',
            'biaya_per_unit' => 'required|numeric|min:0',
        ]);

        try {
            DB::connection('sqlsrv')->beginTransaction();

            // Check unique constraint: (id_vendor_skill, id_jenis_barang)
            $exists = TarifKirimanRutin::where('id_vendor_skill', $request->id_vendor_skill)
                ->where('id_jenis_barang', $request->id_jenis_barang)
                ->where('flag', true)
                ->exists();

            if ($exists) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tarif untuk jenis barang ini sudah ada untuk vendor+skill/area+cabang tersebut',
                ], 400);
            }

            $tarif = TarifKirimanRutin::create([
                'id_vendor_skill' => $request->id_vendor_skill,
                'id_jenis_barang' => $request->id_jenis_barang,
                'biaya_per_unit' => $request->biaya_per_unit,
                'flag' => true,
            ]);

            DB::connection('sqlsrv')->commit();

            return response()->json([
                'success' => true,
                'message' => 'Tarif berhasil ditambahkan',
                'tarif' => $tarif->load('jenisBarang:id_jenis_barang,nama_barang'),
            ], 201);
        } catch (\Exception $e) {
            DB::connection('sqlsrv')->rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal menyimpan tarif: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update tarif yang sudah ada
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            'id_jenis_barang' => 'required|integer|exists:sqlsrv.dbo.sesi_jenis_barang_kiriman,id_jenis_barang',
            'biaya_per_unit' => 'required|numeric|min:0',
        ]);

        try {
            DB::connection('sqlsrv')->beginTransaction();

            $tarif = TarifKirimanRutin::findOrFail($id);

            // Guard: hanya bisa update yang aktif (flag=true)
            if (!$tarif->flag) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tarif ini sudah dihapus/nonaktif',
                ], 403);
            }

            // Check unique constraint jika id_jenis_barang berubah
            if ($tarif->id_jenis_barang !== (int) $request->id_jenis_barang) {
                $exists = TarifKirimanRutin::where('id_vendor_skill', $tarif->id_vendor_skill)
                    ->where('id_jenis_barang', $request->id_jenis_barang)
                    ->where('flag', true)
                    ->where('id_tarif', '!=', $id)
                    ->exists();

                if ($exists) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Tarif untuk jenis barang ini sudah ada',
                    ], 400);
                }
            }

            $tarif->update([
                'id_jenis_barang' => $request->id_jenis_barang,
                'biaya_per_unit' => $request->biaya_per_unit,
            ]);

            DB::connection('sqlsrv')->commit();

            return response()->json([
                'success' => true,
                'message' => 'Tarif berhasil diperbarui',
                'tarif' => $tarif->load('jenisBarang:id_jenis_barang,nama_barang'),
            ]);
        } catch (\Exception $e) {
            DB::connection('sqlsrv')->rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui tarif: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Soft delete tarif (set flag=false)
     */
    public function destroy($id)
    {
        try {
            DB::connection('sqlsrv')->beginTransaction();

            $tarif = TarifKirimanRutin::findOrFail($id);

            // Guard: hanya bisa delete yang aktif (flag=true)
            if (!$tarif->flag) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tarif ini sudah dihapus sebelumnya',
                ], 403);
            }

            $tarif->update(['flag' => false]);

            DB::connection('sqlsrv')->commit();

            return response()->json([
                'success' => true,
                'message' => 'Tarif berhasil dihapus',
            ]);
        } catch (\Exception $e) {
            DB::connection('sqlsrv')->rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus tarif: ' . $e->getMessage(),
            ], 500);
        }
    }

    // ===== MASTER JENIS BARANG CRUD =====

    /**
     * Simpan jenis barang baru
     * POST /api/jenis-barang-kiriman
     */
    public function storeBarang(Request $request)
    {
        $request->validate([
            'nama_barang' => 'required|string|max:150|unique:sqlsrv.dbo.sesi_jenis_barang_kiriman,nama_barang',
        ]);

        try {
            DB::connection('sqlsrv')->beginTransaction();

            $barang = JenisBarangKiriman::create([
                'nama_barang' => $request->nama_barang,
                'flag' => true,
            ]);

            DB::connection('sqlsrv')->commit();

            return response()->json([
                'success' => true,
                'message' => 'Jenis barang berhasil ditambahkan',
                'barang' => $barang,
            ], 201);
        } catch (\Exception $e) {
            DB::connection('sqlsrv')->rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal menyimpan jenis barang: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update jenis barang
     * PUT /api/jenis-barang-kiriman/{id}
     */
    public function updateBarang(Request $request, $id)
    {
        $request->validate([
            'nama_barang' => 'required|string|max:150|unique:sqlsrv.dbo.sesi_jenis_barang_kiriman,nama_barang,' . $id . ',id_jenis_barang',
        ]);

        try {
            DB::connection('sqlsrv')->beginTransaction();

            $barang = JenisBarangKiriman::findOrFail($id);

            // Guard: hanya bisa edit yang aktif
            if (!$barang->flag) {
                return response()->json([
                    'success' => false,
                    'message' => 'Jenis barang ini sudah dihapus/nonaktif',
                ], 403);
            }

            $barang->update(['nama_barang' => $request->nama_barang]);

            DB::connection('sqlsrv')->commit();

            return response()->json([
                'success' => true,
                'message' => 'Jenis barang berhasil diperbarui',
                'barang' => $barang,
            ]);
        } catch (\Exception $e) {
            DB::connection('sqlsrv')->rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui jenis barang: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Soft delete jenis barang (set flag=false)
     * DELETE /api/jenis-barang-kiriman/{id}
     *
     * Guard: Cek apakah masih dipakai di tarif aktif manapun (warning only, tidak hard block)
     */
    public function destroyBarang($id)
    {
        try {
            DB::connection('sqlsrv')->beginTransaction();

            $barang = JenisBarangKiriman::findOrFail($id);

            // Guard: hanya bisa delete yang aktif
            if (!$barang->flag) {
                return response()->json([
                    'success' => false,
                    'message' => 'Jenis barang ini sudah dihapus sebelumnya',
                ], 403);
            }

            // Warning: cek apakah masih dipakai
            $usedCount = TarifKirimanRutin::where('id_jenis_barang', $id)
                ->where('flag', true)
                ->count();

            $barang->update(['flag' => false]);

            DB::connection('sqlsrv')->commit();

            return response()->json([
                'success' => true,
                'message' => 'Jenis barang berhasil dihapus',
                'warning' => $usedCount > 0 ? "Jenis barang ini masih dipakai di $usedCount tarif aktif" : null,
            ]);
        } catch (\Exception $e) {
            DB::connection('sqlsrv')->rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus jenis barang: ' . $e->getMessage(),
            ], 500);
        }
    }
}

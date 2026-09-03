<?php

namespace App\Http\Controllers;

use App\Models\Armada;
use App\Models\PerusahaanEkspedisi;
use App\Models\PengajuanSewa;
use App\Models\RasioSewa;
use App\Models\JenisBiaya;
use App\Models\BiayaTambahan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Helpers\DbHelper;

class PengajuanController extends Controller
{
    public function storeArmada(Request $request)
    {
        $request->validate([
            'perusahaan_id'   => 'required|exists:sqlsrv.dbo.sesi_perusahaan_ekspedisi,id_perusahaan',
            'id_skill'        => 'required|array|min:1',
            'id_skill.*'      => 'required|string',
            'jenis_kendaraan'  => 'nullable|string|max:100',
            'plat_nomor_truk'      => 'nullable|string|max:20|unique:sqlsrv.dbo.sesi_unit_kendaraan,plat_nomor_truk',
            'muatan_maksimal' => 'required|numeric|min:0.01',
        ]);

        try {
            DB::connection('sqlsrv')->beginTransaction();

            $user     = auth()->user();
            $cabangId = $user->getCabangId();
            $userId   = $user->id;

            if (!$cabangId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cabang tidak ditemukan dari username',
                ], 400);
            }

            // Perusahaan sudah ada (dibuat terpisah di storePerusahaan), cukup gunakan ID
            $perusahaan = PerusahaanEkspedisi::findOrFail($request->perusahaan_id);

            // Convert skill array to comma-separated string
            $skillString = implode(',', $request->id_skill);

            // Create armada
            $armada = Armada::create([
                'id_perusahaan'  => $perusahaan->id_perusahaan,
                'id_cabang'      => $cabangId,
                'id_skill'       => $skillString,
                'jenis_kendaraan' => $request->jenis_kendaraan ? $request->jenis_kendaraan : null,
                'plat_nomor_truk'     => $request->plat_nomor_truk ? strtoupper($request->plat_nomor_truk) : null,
                'muatan_maksimal'=> $request->muatan_maksimal,
                'flag'           => true,
            ]);

            // Insert new skills to master + cabang mapping if any are provided
            if (!empty($request->id_skill)) {
                foreach ($request->id_skill as $skillName) {
                    $skillName = strtoupper(trim($skillName));
                    if (empty($skillName)) continue;

                    // Insert ke master skill kalau belum ada
                    $exists = DB::connection('sqlsrv')
                        ->table('sesi_master_skill')
                        ->where('id_skill', $skillName)
                        ->exists();

                    if (!$exists) {
                        DB::connection('sqlsrv')->table('sesi_master_skill')->insert([
                            'id_skill'   => $skillName,
                            'flag'       => true,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }

                    // Insert ke cabang_skill kalau belum ada
                    $cabangSkillExists = DB::connection('sqlsrv')
                        ->table('sesi_cabang_skill')
                        ->where('cabang_code', $cabangId)
                        ->where('id_skill', $skillName)
                        ->exists();

                    if (!$cabangSkillExists) {
                        DB::connection('sqlsrv')->table('sesi_cabang_skill')->insert([
                            'cabang_code' => $cabangId,
                            'id_skill'    => $skillName,
                            'flag'        => true,
                            'created_at'  => now(),
                            'updated_at'  => now(),
                        ]);
                    }
                }
            }

            DB::connection('sqlsrv')->commit();

            return response()->json([
                'success' => true,
                'message' => 'Armada berhasil ditambahkan',
                'armada'  => [
                    'id'        => $armada->id_kendaraan,
                    'nama'      => $perusahaan->nama_perusahaan,
                    'kendaraan' => $armada->jenis_kendaraan,
                    'muatan'    => (int)$armada->muatan_maksimal . ' Ton',
                    'muatan_raw' => $armada->muatan_maksimal,
                    'harga'     => 'Rp 0',
                    'skill'     => $armada->id_skill,
                    'updated'   => now()->format('d M Y'),
                ],
            ]);
        } catch (\Exception $e) {
            DB::connection('sqlsrv')->rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal menyimpan armada: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function submitPengajuan(Request $request)
    {
        $request->validate([
            'id_armada'          => 'required|integer',
            'tanggal_pengiriman' => 'required|date',
            'harga_sewa'         => 'required|numeric|min:0',
            'value_muatan'       => 'required|numeric|min:1',
            'tujuan_penyewaan'   => 'required|in:Toko,PAC',
            'id_skill'           => 'required|array|min:1',
            'id_skill.*'         => 'required|string',
            'skill_baru'         => 'nullable|array',
            'skill_baru.*'       => 'nullable|string|max:50',
            'kategori_toko'      => 'required|string',
            'catatan'            => 'nullable|string',
            'biaya_tambahan'     => 'nullable|array',
            'biaya_tambahan.*.id_jenis_biaya' => 'required|integer|exists:sqlsrv.dbo.sesi_jenis_biaya,id_jenis_biaya',
            'biaya_tambahan.*.nominal'        => 'required|numeric|min:0',
        ]);

        try {
            DB::connection('sqlsrv')->beginTransaction();

            $user     = auth()->user();
            $cabangId = $user->getCabangId();

            if (!$cabangId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cabang tidak ditemukan',
                ], 400);
            }

            // Gabung skill existing + skill baru
            $allSkills = collect($request->id_skill)
                ->map(fn($s) => strtoupper(trim($s)))
                ->filter()
                ->values();

            $skillBaru = collect($request->skill_baru ?? [])
                ->map(fn($s) => strtoupper(trim($s)))
                ->filter()
                ->values();

            // Insert skill baru ke sesi_master_skill + sesi_cabang_skill
            foreach ($skillBaru as $nama) {
                // Insert ke master skill kalau belum ada
                $exists = DB::connection('sqlsrv')
                    ->table('sesi_master_skill')
                    ->where('id_skill', $nama)
                    ->exists();

                if (!$exists) {
                    DB::connection('sqlsrv')->table('sesi_master_skill')->insert([
                        'id_skill'   => $nama,
                        'flag'       => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                // Insert ke cabang_skill kalau belum ada
                $cabangSkillExists = DB::connection('sqlsrv')
                    ->table('sesi_cabang_skill')
                    ->where('cabang_code', $cabangId)
                    ->where('id_skill', $nama)
                    ->exists();

                if (!$cabangSkillExists) {
                    DB::connection('sqlsrv')->table('sesi_cabang_skill')->insert([
                        'cabang_code' => $cabangId,
                        'id_skill'    => $nama,
                        'flag'        => true,
                        'created_at'  => now(),
                        'updated_at'  => now(),
                    ]);
                }

                // Tambah ke allSkills
                $allSkills->push($nama);
            }

            $allSkills = $allSkills->unique()->values();
            $idSkillStr = $allSkills->implode(',');

            // Hitung rasio sewa termasuk biaya tambahan
            $totalBiayaTambahan = collect($request->biaya_tambahan ?? [])->sum('nominal');
            $rasioSewa = (($request->harga_sewa + $totalBiayaTambahan) / $request->value_muatan) * 100;

            // Cek area baru (ada skill yang belum pernah ada di cabang ini sebelum submit ini)
            $isAreaBaru = $skillBaru->isNotEmpty();

            // Tentukan kategori approval
            $kategoriApproval = 'normal';
            $rasioMaks = \App\Models\RasioSewa::aktif()?->persentase_maksimal ?? 2.5;
            if ($rasioSewa > $rasioMaks || $request->tujuan_penyewaan === 'PAC' || $isAreaBaru) {
                $kategoriApproval = 'over_threshold';
            }

            $pengajuan = PengajuanSewa::create([
                'id_kendaraan'              => $request->id_kendaraan,
                'id_cabang'              => $cabangId,
                'tanggal_pengiriman'     => $request->tanggal_pengiriman,
                'value_muatan'           => $request->value_muatan,
                'harga_sewa'             => $request->harga_sewa,
                'rasio_sewa'             => round($rasioSewa, 2),
                'kategori_approval'      => $kategoriApproval,
                'tujuan_penyewaan'       => $request->tujuan_penyewaan,
                'id_skill'               => $idSkillStr,
                'kategori_toko'          => $request->kategori_toko,
                'status_pengajuan'       => 'Pending',
                'catatan_pengajuan'      => $request->catatan,
                'submitted_by'           => $user->id,
                'submitted_at'           => now(),
                'flag'                   => true,
            ]);

            // Simpan biaya tambahan
            if ($request->biaya_tambahan) {
                foreach ($request->biaya_tambahan as $biaya) {
                    BiayaTambahan::create([
                        'id_pengajuan_sewa' => $pengajuan->id_pengajuan_sewa,
                        'id_jenis_biaya'    => $biaya['id_jenis_biaya'],
                        'jumlah'            => $biaya['nominal'],
                        'flag'              => true,
                    ]);
                }
            }

            DB::connection('sqlsrv')->commit();

            return response()->json([
                'success'      => true,
                'message'      => 'Pengajuan berhasil disubmit',
                'id_pengajuan' => $pengajuan->id_pengajuan_sewa,
            ]);
        } catch (\Exception $e) {
            DB::connection('sqlsrv')->rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal submit pengajuan: ' . $e->getMessage(),
            ], 500);
        }
    }

    // GET /api/pengajuan/skill-list
    public function getSkillList()
    {
        $result = DbHelper::safeQuery(function () {
            $user       = auth()->user();
            $cabangCode = $user->getCabangId();

            if (!$cabangCode) {
                return [];
            }

            $skills = DB::connection('sqlsrv')
                ->table('sesi_cabang_skill as cs')
                ->join('sesi_master_skill as ms', 'ms.id_skill', '=', 'cs.id_skill')
                ->where('cs.cabang_code', $cabangCode)
                ->where('cs.flag', true)
                ->where('ms.flag', true)
                ->orderBy('ms.id_skill')
                ->pluck('ms.id_skill')
                ->map(fn($s) => ['id_skill' => $s, 'nama_skill' => $s])
                ->values()
                ->toArray();

            return $skills;
        });

        return response()->json($result);
    }

    // GET /api/pengajuan/kategori-toko-list
    // Return distinct kategoriToko values (untuk dropdown)
    public function getKategoriTokoList()
    {
        $result = DbHelper::safeQuery(function () {
            $user       = auth()->user();
            $cabangCode = $user->getCabangId();

            if (!$cabangCode) {
                return [];
            }

            return DB::connection('sqlsrv')
                ->table('sesi_cabang_skill as cs')
                ->join('Q_CustomerLocusAtribute as q', function ($join) {
                    $join->on(DB::raw('UPPER(TRIM(q.skills))'), '=', 'cs.id_skill');
                })
                ->where('cs.cabang_code', $cabangCode)
                ->where('cs.flag', true)
                ->whereNotNull('q.kategoriToko')
                ->where('q.kategoriToko', '!=', '')
                ->where('q.kategoriToko', '!=', ' ')
                ->distinct()
                ->orderBy('q.kategoriToko')
                ->pluck('q.kategoriToko')
                ->map(fn($k) => trim($k))
                ->filter(fn($k) => $k !== '')
                ->unique()
                ->values()
                ->toArray();
        });

        return response()->json($result);
    }

    // GET /api/pengajuan/jenis-biaya
    // Return daftar jenis biaya dari sesi_jenis_biaya
    public function getJenisBiaya()
    {
        $result = DbHelper::safeQuery(function () {
            return JenisBiaya::where('flag', true)
                ->orderBy('nama_biaya')
                ->get(['id_jenis_biaya', 'nama_biaya'])
                ->toArray();
        });

        return response()->json($result);
    }

    public function edit($id)
    {
        $pengajuan = PengajuanSewa::with(['armada.perusahaan', 'biayaTambahan.jenisBiaya'])
            ->findOrFail($id);

        // Authorization: hanya KG pemilik pengajuan, dalam cabang yang sama
        if (!auth()->user()->canAccessCabang($pengajuan->id_cabang)) {
            abort(403);
        }

        if ($pengajuan->submitted_by != auth()->id()) {
            abort(403);
        }

        // Hanya Pending atau Rejected bisa diedit
        if (!in_array($pengajuan->status_pengajuan, ['pending', 'rejected'])) {
            abort(403);
        }

        $dummyDokumenMapping = [1, 2, 3];

        $editData = [
            'id_pengajuan_sewa'  => $pengajuan->id_pengajuan_sewa,
            'id_armada'          => $pengajuan->id_armada,
            'tanggal_pengiriman' => $pengajuan->tanggal_pengiriman->format('Y-m-d'),
            'harga_sewa'         => (float)$pengajuan->harga_sewa,
            'value_muatan'       => (float)$pengajuan->value_muatan,
            'tujuan_penyewaan'   => $pengajuan->tujuan_penyewaan,
            'kategori_toko'      => $pengajuan->kategori_toko,
            'id_skill'           => array_map('trim', explode(',', $pengajuan->id_skill)),
            'catatan_pengajuan'  => $pengajuan->catatan_pengajuan,
            'biaya_tambahan'     => $pengajuan->biayaTambahan->map(function ($biaya) {
                return [
                    'id_jenis_biaya' => (int)$biaya->id_jenis_biaya,
                    'nominal'        => (float)$biaya->jumlah,
                ];
            })->toArray(),
            'dokumen_dipilih'    => $dummyDokumenMapping, // Prefill dokumen untuk Step 3 (temporary: hardcoded)
            'armada'             => [
                'id'        => $pengajuan->armada->id_kendaraan,
                'nama'      => $pengajuan->armada->perusahaan->nama_perusahaan,
                'kendaraan' => $pengajuan->armada->jenis_kendaraan,
                'muatan'    => (int)$pengajuan->armada->muatan_maksimal . ' Ton',
                'muatan_raw' => (float)$pengajuan->armada->muatan_maksimal,
                'skill'     => $pengajuan->armada->id_skill,
                'updated_at' => $pengajuan->armada->updated_at->format('d M Y'),
            ],
        ];

        return view('pages.pengajuan.index', ['editPengajuan' => $editData]);
    }

    public function update(Request $request, $id)
    {
        $pengajuan = PengajuanSewa::findOrFail($id);

        // Authorization checks
        if (!auth()->user()->canAccessCabang($pengajuan->id_cabang)) {
            abort(403);
        }

        if ($pengajuan->submitted_by != auth()->id()) {
            abort(403);
        }

        if (!in_array($pengajuan->status_pengajuan, ['pending', 'rejected'])) {
            abort(403);
        }

        $request->validate([
            'id_kendaraan'          => 'required|integer|exists:sqlsrv.dbo.sesi_unit_kendaraan,id_kendaraan',
            'tanggal_pengiriman' => 'required|date',
            'harga_sewa'         => 'required|numeric|min:0',
            'value_muatan'       => 'required|numeric|min:1',
            'tujuan_penyewaan'   => 'required|in:Toko,PAC',
            'id_skill'           => 'required|array|min:1',
            'id_skill.*'         => 'required|string',
            'skill_baru'         => 'nullable|array',
            'skill_baru.*'       => 'nullable|string|max:50',
            'kategori_toko'      => 'required|string',
            'catatan'            => 'nullable|string',
            'biaya_tambahan'     => 'nullable|array',
            'biaya_tambahan.*.id_jenis_biaya' => 'required|integer|exists:sqlsrv.dbo.sesi_jenis_biaya,id_jenis_biaya',
            'biaya_tambahan.*.nominal'        => 'required|numeric|min:0',
        ]);

        try {
            DB::connection('sqlsrv')->beginTransaction();

            $user = auth()->user();
            $cabangId = $user->getCabangId();

            // Gabung skill existing + skill baru
            $allSkills = collect($request->id_skill)
                ->map(fn($s) => strtoupper(trim($s)))
                ->filter()
                ->values();

            $skillBaru = collect($request->skill_baru ?? [])
                ->map(fn($s) => strtoupper(trim($s)))
                ->filter()
                ->values();

            // Insert skill baru ke sesi_master_skill + sesi_cabang_skill
            foreach ($skillBaru as $nama) {
                $exists = DB::connection('sqlsrv')
                    ->table('sesi_master_skill')
                    ->where('id_skill', $nama)
                    ->exists();

                if (!$exists) {
                    DB::connection('sqlsrv')->table('sesi_master_skill')->insert([
                        'id_skill'   => $nama,
                        'flag'       => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                $cabangSkillExists = DB::connection('sqlsrv')
                    ->table('sesi_cabang_skill')
                    ->where('cabang_code', $cabangId)
                    ->where('id_skill', $nama)
                    ->exists();

                if (!$cabangSkillExists) {
                    DB::connection('sqlsrv')->table('sesi_cabang_skill')->insert([
                        'cabang_code' => $cabangId,
                        'id_skill'    => $nama,
                        'flag'        => true,
                        'created_at'  => now(),
                        'updated_at'  => now(),
                    ]);
                }

                $allSkills->push($nama);
            }

            $allSkills = $allSkills->unique()->values();
            $idSkillStr = $allSkills->implode(',');

            // Hitung rasio sewa termasuk biaya tambahan
            $totalBiayaTambahan = collect($request->biaya_tambahan ?? [])->sum('nominal');
            $rasioSewa = (($request->harga_sewa + $totalBiayaTambahan) / $request->value_muatan) * 100;
            $isAreaBaru = $skillBaru->isNotEmpty();

            $kategoriApproval = 'normal';
            $rasioMaks = \App\Models\RasioSewa::aktif()?->persentase_maksimal ?? 2.5;
            if ($rasioSewa > $rasioMaks || $request->tujuan_penyewaan === 'PAC' || $isAreaBaru) {
                $kategoriApproval = 'over_threshold';
            }

            // Update pengajuan, set status_pengajuan ke Pending (baik dari Pending tetap Pending, atau dari Rejected jadi Pending)
            $pengajuan->update([
                'tanggal_pengiriman'     => $request->tanggal_pengiriman,
                'value_muatan'           => $request->value_muatan,
                'harga_sewa'             => $request->harga_sewa,
                'rasio_sewa'             => round($rasioSewa, 2),
                'kategori_approval'      => $kategoriApproval,
                'tujuan_penyewaan'       => $request->tujuan_penyewaan,
                'id_skill'               => $idSkillStr,
                'kategori_toko'          => $request->kategori_toko,
                'status_pengajuan'       => 'Pending',
                'catatan_pengajuan'      => $request->catatan,
            ]);

            // Hapus biaya tambahan lama dan insert ulang
            BiayaTambahan::where('id_pengajuan_sewa', $pengajuan->id_pengajuan_sewa)->delete();

            if ($request->biaya_tambahan) {
                foreach ($request->biaya_tambahan as $biaya) {
                    BiayaTambahan::create([
                        'id_pengajuan_sewa' => $pengajuan->id_pengajuan_sewa,
                        'id_jenis_biaya'    => $biaya['id_jenis_biaya'],
                        'jumlah'            => $biaya['nominal'],
                        'flag'              => true,
                    ]);
                }
            }

            DB::connection('sqlsrv')->commit();

            return response()->json([
                'success'      => true,
                'message'      => 'Pengajuan berhasil diperbarui',
                'id_pengajuan' => $pengajuan->id_pengajuan_sewa,
            ]);
        } catch (\Exception $e) {
            DB::connection('sqlsrv')->rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal update pengajuan: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function show($id)
    {
        $pengajuan = PengajuanSewa::with(['armada.perusahaan', 'submittedBy', 'biayaTambahan.jenisBiaya', 'approvalLogs.approver'])
            ->findOrFail($id);

        if (!auth()->user()->canAccessCabang($pengajuan->id_cabang)) {
            abort(403);
        }

        // Build timeline: merge approval logs + synthetic entry kalau ada resubmit
        $timeline = collect($pengajuan->approvalLogs)->map(function ($log) {
            return [
                'type'       => 'approval',
                'status'     => $log->status,
                'approver'   => $log->approver,
                'decided_at' => $log->decided_at,
                'reason'     => $log->alasan_penolakan,
            ];
        });

        // Cek apakah ada resubmit: status Pending sekarang + ada log Rejected terbaru, dan updated_at lebih baru dari decided_at log terakhir
        if ($pengajuan->status_pengajuan === 'Pending' && $timeline->isNotEmpty()) {
            $lastRejection = $timeline
                ->where('status', 'Rejected')
                ->sortBy('decided_at')
                ->last();

            if ($lastRejection && $pengajuan->updated_at > $lastRejection['decided_at']) {
                // Insert synthetic resubmit entry di posisi yang tepat (berdasarkan waktu)
                $timeline->push([
                    'type'       => 'resubmit',
                    'aktor'      => $pengajuan->submittedBy,
                    'decided_at' => $pengajuan->updated_at,
                ]);
            }
        }

        // Sort timeline berdasarkan timestamp (ascending)
        $timeline = $timeline->sortBy('decided_at')->values();

        // Get ambang rasio sewa dari database
        $rasioSewaSetting = RasioSewa::aktif();
        $ambangRasio = $rasioSewaSetting ? $rasioSewaSetting->persentase_maksimal : 2.5;

        return view('pages.pengajuan.detailPengajuan', [
            'pengajuan' => $pengajuan,
            'timeline' => $timeline,
            'ambangRasio' => $ambangRasio,
            'breadcrumb' => [
                'back_url' => route('dashboard'),
                'back_label' => 'Dashboard',
                'title' => 'Detail Pengajuan',
                'status' => strtolower($pengajuan->status_pengajuan),
            ],
        ]);
    }

    /**
     * Fetch list perusahaan existing (untuk dropdown di step1)
     */
    public function getPerusahaanList()
    {
        $perusahaan = PerusahaanEkspedisi::where('flag', true)
            ->orderBy('nama_perusahaan')
            ->get(['id_perusahaan', 'nama_perusahaan', 'badan_usaha', 'no_telepon', 'alamat_kantor']);

        return response()->json(['data' => $perusahaan]);
    }

    /**
     * Create perusahaan baru + upload identitas owner
     */
    public function storePerusahaan(Request $request)
    {
        $request->validate([
            'nama_perusahaan'    => 'required|string|max:255|unique:sqlsrv.dbo.sesi_perusahaan_ekspedisi,nama_perusahaan',
            'badan_usaha'        => 'required|in:PT,CV,UD,Perseorangan',
            'no_telepon'         => 'required|string|max:20',
            'alamat_kantor'      => 'required|string',
            'identitas_owner'    => 'required|array|min:1|max:3',
            'identitas_owner.*'  => 'required|file|mimes:jpg,jpeg,png,webp|max:5120',
        ]);

        try {
            DB::connection('sqlsrv')->beginTransaction();

            // Encode identitas owner files to base64
            $identitas_owner_data = [];
            if ($request->hasFile('identitas_owner')) {
                foreach ($request->file('identitas_owner') as $file) {
                    $identitas_owner_data[] = base64_encode(file_get_contents($file->getRealPath()));
                }
            }

            // Create perusahaan
            $perusahaan = PerusahaanEkspedisi::create([
                'nama_perusahaan' => $request->nama_perusahaan,
                'badan_usaha'     => $request->badan_usaha,
                'no_telepon'      => $request->no_telepon,
                'alamat_kantor'   => $request->alamat_kantor,
                'identitas_owner' => json_encode($identitas_owner_data),
                'flag'            => true,
            ]);

            DB::connection('sqlsrv')->commit();

            return response()->json([
                'success' => true,
                'message' => 'Perusahaan berhasil disimpan',
                'perusahaan' => $perusahaan,
            ], 201);
        } catch (\Exception $e) {
            DB::connection('sqlsrv')->rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal menyimpan perusahaan: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Fetch armada by perusahaan ID (untuk ditampilkan setelah user pilih perusahaan)
     */
    public function getArmadaByPerusahaan(Request $request)
    {
        $request->validate(['perusahaan_id' => 'required|integer']);

        try {
            $armada = DB::connection('sqlsrv')->table('sesi_unit_kendaraan')
                ->join('sesi_perusahaan_ekspedisi', 'sesi_unit_kendaraan.id_perusahaan', '=', 'sesi_perusahaan_ekspedisi.id_perusahaan')
                ->select(
                    'sesi_unit_kendaraan.id_kendaraan as id',
                    'sesi_perusahaan_ekspedisi.badan_usaha as badan_usaha',
                    'sesi_perusahaan_ekspedisi.nama_perusahaan as nama',
                    'sesi_unit_kendaraan.id_skill as skill',
                    'sesi_unit_kendaraan.jenis_kendaraan as kendaraan',
                    'sesi_unit_kendaraan.plat_nomor_truk as plat_nomor',
                    'sesi_unit_kendaraan.muatan_maksimal as muatan_raw',
                    DB::raw("FORMAT(sesi_unit_kendaraan.updated_at, 'dd MMM yyyy') as updated")
                )
                ->where('sesi_unit_kendaraan.id_perusahaan', $request->perusahaan_id)
                ->where('sesi_unit_kendaraan.flag', true)
                ->orderByDesc('sesi_unit_kendaraan.updated_at')
                ->get()
                ->map(function($a) {
                    return [
                        ...(array) $a,
                        'muatan' => \App\Helpers\FormatHelper::ton($a->muatan_raw),
                    ];
                });

            return response()->json(['data' => $armada]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal fetch armada: ' . $e->getMessage(),
            ], 500);
        }
    }
}
<?php

namespace App\Http\Controllers;

use App\Models\Armada;
use App\Models\PerusahaanEkspedisi;
use App\Models\PengajuanSewa;
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
            'nama_perusahaan' => 'required|string|max:255',
            'badan_usaha'     => 'required|in:PT,CV,UD,Perorangan',
            'no_telepon'      => 'required|string|max:20',
            'alamat_kantor'   => 'required|string',
            'id_skill'        => 'required|array|min:1',
            'id_skill.*'      => 'required|string',
            'nama_kendaraan'  => 'required|string|max:100',
            'plat_nomor'      => 'required|string|max:20|unique:sqlsrv.dbo.sesi_armada,plat_nomor',
            'muatan_maksimal' => 'required|numeric|min:0.01',
            'ktp_supir'       => 'required|file|mimes:jpg,jpeg,png,webp|max:5120',
            'sim_supir'       => 'required|file|mimes:jpg,jpeg,png,webp|max:5120',
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

            $perusahaan = PerusahaanEkspedisi::firstOrCreate(
                ['nama_perusahaan' => $request->nama_perusahaan],
                [
                    'badan_usaha'   => $request->badan_usaha,
                    'no_telepon'    => $request->no_telepon,
                    'alamat_kantor' => $request->alamat_kantor,
                    'flag'          => true,
                ]
            );

            // Convert skill array to comma-separated string
            $skillString = implode(',', $request->id_skill);

            // Create armada first to get ID for filename
            $armada = Armada::create([
                'id_perusahaan'  => $perusahaan->id_perusahaan,
                'id_cabang'      => $cabangId,
                'ktp_supir'      => null,
                'sim_supir'      => null,
                'id_skill'       => $skillString,
                'nama_kendaraan' => $request->nama_kendaraan,
                'plat_nomor'     => strtoupper($request->plat_nomor),
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
                        ->where('nama_skill', $skillName)
                        ->exists();

                    if (!$exists) {
                        DB::connection('sqlsrv')->table('sesi_master_skill')->insert([
                            'nama_skill' => $skillName,
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

            // Ensure public/images directory exists
            $imagesDir = public_path('images');
            if (!is_dir($imagesDir)) {
                mkdir($imagesDir, 0755, true);
            }

            // Save KTP file
            if ($request->hasFile('ktp_supir')) {
                $ktpFile = $request->file('ktp_supir');
                $ktpExt = $ktpFile->getClientOriginalExtension();
                $ktpFilename = "{$cabangId}-{$userId}-{$armada->id_armada}-ktp.{$ktpExt}";
                $ktpFile->move($imagesDir, $ktpFilename);
                $ktpPath = "/images/{$ktpFilename}";
            }

            // Save SIM file
            if ($request->hasFile('sim_supir')) {
                $simFile = $request->file('sim_supir');
                $simExt = $simFile->getClientOriginalExtension();
                $simFilename = "{$cabangId}-{$userId}-{$armada->id_armada}-sim.{$simExt}";
                $simFile->move($imagesDir, $simFilename);
                $simPath = "/images/{$simFilename}";
            }

            // Update armada with file paths
            $armada->update([
                'ktp_supir' => $ktpPath ?? null,
                'sim_supir' => $simPath ?? null,
            ]);

            DB::connection('sqlsrv')->commit();

            return response()->json([
                'success' => true,
                'message' => 'Armada berhasil ditambahkan',
                'armada'  => [
                    'id'        => $armada->id_armada,
                    'nama'      => $perusahaan->nama_perusahaan,
                    'kendaraan' => $armada->nama_kendaraan,
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
                    ->where('nama_skill', $nama)
                    ->exists();

                if (!$exists) {
                    DB::connection('sqlsrv')->table('sesi_master_skill')->insert([
                        'nama_skill' => $nama,
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

            $rasioSewa = ($request->harga_sewa / $request->value_muatan) * 100;

            // Cek area baru (ada skill yang belum pernah ada di cabang ini sebelum submit ini)
            $isAreaBaru = $skillBaru->isNotEmpty();

            // Tentukan kategori approval
            $kategoriApproval = 'normal';
            $rasioMaks = \App\Models\RasioSewa::aktif()?->persentase_maksimal ?? 2.5;
            if ($rasioSewa > $rasioMaks || $request->tujuan_penyewaan === 'PAC' || $isAreaBaru) {
                $kategoriApproval = 'over_threshold';
            }

            $pengajuan = PengajuanSewa::create([
                'id_armada'              => $request->id_armada,
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
                ->join('sesi_master_skill as ms', 'ms.nama_skill', '=', 'cs.id_skill')
                ->where('cs.cabang_code', $cabangCode)
                ->where('cs.flag', true)
                ->where('ms.flag', true)
                ->orderBy('ms.nama_skill')
                ->pluck('ms.nama_skill')
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

    public function show($id)
    {
        $pengajuan = PengajuanSewa::with(['armada.perusahaan', 'pengaju', 'biayaTambahan.jenisBiaya'])
            ->findOrFail($id);

        if (!auth()->user()->canAccessCabang($pengajuan->id_cabang)) {
            abort(403);
        }

        return view('pages.pengajuan.detailPengajuan', compact('pengajuan'));
    }
}
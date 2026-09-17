<?php

namespace App\Http\Controllers;
use Illuminate\Support\Facades\DB;
use App\Models\Kendaraan;
use App\Http\Controllers\Concerns\ManagesVendorMasterData;

use Illuminate\Http\Request;

class KendaraanController extends Controller
{
    use ManagesVendorMasterData;

    public function index()
    {
        return view('pages.kendaraan.listKendaraan');
    }

    public function show($id)
    {
        $kendaraan = Kendaraan::with('perusahaan')->findOrFail($id);

        // Get harga sewa terakhir dari pengajuan yang sudah approved
        $hargaSewaTerakhir = $kendaraan->pengajuan()
            ->where('status_pengajuan', 'approved')
            ->orderByDesc('submitted_at')
            ->value('harga_sewa');

        return view('pages.kendaraan.detail', compact('kendaraan', 'hargaSewaTerakhir'));
    }

    /**
     * Tambah kendaraan baru dari halaman Kelola Perusahaan (Master Data, DCI) —
     * beda dari PengajuanController::storeKendaraan() (wizard, id_cabang implisit
     * dari auth()->user()->getCabangId()): di sini id_cabang WAJIB dikirim
     * eksplisit dari form, karena DCI itu global access (getCabangId() null).
     * POST /api/kendaraan
     */
    public function store(Request $request)
    {
        $request->validate([
            'id_perusahaan'   => 'required|integer|exists:sqlsrv.dbo.sesi_perusahaan_ekspedisi,id_perusahaan',
            'id_cabang'       => 'required|string|max:10|exists:sqlsrv.dbo.sesi_master_cabang,Code',
            'id_skill'        => 'nullable|array',
            'id_skill.*'      => 'integer|exists:sqlsrv.dbo.sesi_master_skill,id_skill',
            'skill_baru'      => 'nullable|array',
            'skill_baru.*'    => 'nullable|string|max:100',
            'jenis_kendaraan' => 'nullable|string|max:100',
            'plat_nomor_truk' => 'nullable|string|max:20|unique:sqlsrv.dbo.sesi_unit_kendaraan,plat_nomor_truk',
            'muatan_maksimal' => 'required|numeric|min:0.01',
        ]);

        if (empty($request->id_skill) && empty(array_filter($request->skill_baru ?? []))) {
            return response()->json([
                'success' => false,
                'message' => 'Pilih atau tambahkan minimal 1 area/skill.',
            ], 422);
        }

        try {
            DB::connection('sqlsrv')->beginTransaction();

            $idCabang = strtoupper(trim($request->id_cabang));

            $skillIds = collect($request->id_skill ?? [])->map(fn ($s) => (int) $s);
            foreach (collect($request->skill_baru ?? [])->filter() as $nama) {
                $idBaru = $this->resolveSkillId($nama, $idCabang);
                if ($idBaru) {
                    $skillIds->push($idBaru);
                }
            }
            $skillString = $skillIds->unique()->values()->implode(',');

            $kendaraan = Kendaraan::create([
                'id_perusahaan'   => $request->id_perusahaan,
                'id_cabang'       => $idCabang,
                'id_skill'        => $skillString,
                'jenis_kendaraan' => $request->jenis_kendaraan ?: null,
                'plat_nomor_truk' => $request->plat_nomor_truk ? strtoupper($request->plat_nomor_truk) : null,
                'muatan_maksimal' => $request->muatan_maksimal,
                'flag'            => true,
            ]);

            DB::connection('sqlsrv')->commit();

            return response()->json([
                'success' => true,
                'message' => 'Kendaraan berhasil ditambahkan',
                'kendaraan' => $kendaraan->fresh(),
            ], 201);
        } catch (\Throwable $e) {
            DB::connection('sqlsrv')->rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal menyimpan kendaraan: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Edit kendaraan yang sudah ada — dari halaman Kelola Perusahaan (Master
     * Data, DCI). Belum pernah ada endpoint update kendaraan sebelum ini
     * (cuma create via wizard).
     * PUT /api/kendaraan/{id}
     */
    public function update(Request $request, $id)
    {
        $kendaraan = Kendaraan::where('flag', true)->findOrFail($id);

        $request->validate([
            'id_skill'        => 'nullable|array',
            'id_skill.*'      => 'integer|exists:sqlsrv.dbo.sesi_master_skill,id_skill',
            'skill_baru'      => 'nullable|array',
            'skill_baru.*'    => 'nullable|string|max:100',
            'jenis_kendaraan' => 'nullable|string|max:100',
            'plat_nomor_truk' => 'nullable|string|max:20|unique:sqlsrv.dbo.sesi_unit_kendaraan,plat_nomor_truk,' . $kendaraan->id_kendaraan . ',id_kendaraan',
            'muatan_maksimal' => 'required|numeric|min:0.01',
        ]);

        try {
            DB::connection('sqlsrv')->beginTransaction();

            $skillIds = collect($request->id_skill ?? [])->map(fn ($s) => (int) $s);
            foreach (collect($request->skill_baru ?? [])->filter() as $nama) {
                $idBaru = $this->resolveSkillId($nama, $kendaraan->id_cabang);
                if ($idBaru) {
                    $skillIds->push($idBaru);
                }
            }
            // Kosongkan id_skill di request = biarkan skill lama apa adanya (bukan
            // dikosongkan) — cuma update kalau memang dikirim (checkbox/skill_baru ada).
            $skillString = $skillIds->isEmpty() ? $kendaraan->id_skill : $skillIds->unique()->values()->implode(',');

            $kendaraan->update([
                'jenis_kendaraan' => $request->jenis_kendaraan ?: null,
                'plat_nomor_truk' => $request->plat_nomor_truk ? strtoupper($request->plat_nomor_truk) : null,
                'muatan_maksimal' => $request->muatan_maksimal,
                'id_skill'        => $skillString,
            ]);

            DB::connection('sqlsrv')->commit();

            return response()->json([
                'success' => true,
                'message' => 'Kendaraan berhasil diperbarui',
                'kendaraan' => $kendaraan->fresh(),
            ]);
        } catch (\Throwable $e) {
            DB::connection('sqlsrv')->rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui kendaraan: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Soft delete kendaraan (flag=false).
     * DELETE /api/kendaraan/{id}
     */
    public function destroy($id)
    {
        $kendaraan = Kendaraan::where('flag', true)->findOrFail($id);

        try {
            $kendaraan->update(['flag' => false]);

            return response()->json([
                'success' => true,
                'message' => 'Kendaraan berhasil dihapus',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus kendaraan: ' . $e->getMessage(),
            ], 500);
        }
    }
}

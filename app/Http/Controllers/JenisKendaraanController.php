<?php

namespace App\Http\Controllers;

use App\Models\MasterJenisKendaraan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Master Jenis Kendaraan: halaman lihat untuk WM/WC/WH/DCI, CRUD hanya DCI (lihat routes/web.php).
 * Sebelumnya `sesi_unit_kendaraan.jenis_kendaraan` cuma teks bebas + muatan
 * diisi manual sendiri-sendiri; sekarang jenis kendaraan jadi lookup dan
 * muatan_maksimal ikut ter-lock per entri.
 */
class JenisKendaraanController extends Controller
{
    public function index()
    {
        $jenisKendaraan = MasterJenisKendaraan::orderBy('nama_jenis')->get();

        return view('pages.master.jenis-kendaraan', compact('jenisKendaraan'));
    }

    /** GET /api/jenis-kendaraan — dropdown "Jenis Kendaraan" di form tambah/edit kendaraan. */
    public function list()
    {
        $jenisKendaraan = MasterJenisKendaraan::where('flag', true)
            ->orderBy('nama_jenis')
            ->get(['id_jenis_kendaraan', 'nama_jenis', 'muatan_maksimal_ton']);

        return response()->json(['data' => $jenisKendaraan]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'nama_jenis' => 'required|string|max:100|unique:sqlsrv.dbo.sesi_master_jenis_kendaraan,nama_jenis',
            'muatan_maksimal_ton' => 'required|numeric|min:0.01',
        ]);

        try {
            DB::connection('sqlsrv')->beginTransaction();

            $jenisKendaraan = MasterJenisKendaraan::create([
                'nama_jenis' => $request->nama_jenis,
                'muatan_maksimal_ton' => $request->muatan_maksimal_ton,
                'flag' => false, // default false, nanti bisa diaktifkan oleh WH/DCI
            ]);

            DB::connection('sqlsrv')->commit();

            return response()->json([
                'success' => true,
                'message' => 'Jenis kendaraan berhasil ditambahkan',
                'jenis_kendaraan' => $jenisKendaraan,
            ], 201);
        } catch (\Exception $e) {
            DB::connection('sqlsrv')->rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Gagal menyimpan jenis kendaraan: '.$e->getMessage(),
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'nama_jenis' => 'required|string|max:100|unique:sqlsrv.dbo.sesi_master_jenis_kendaraan,nama_jenis,'.$id.',id_jenis_kendaraan',
            'muatan_maksimal_ton' => 'required|numeric|min:0.01',
        ]);

        try {
            DB::connection('sqlsrv')->beginTransaction();

            $jenisKendaraan = MasterJenisKendaraan::findOrFail($id);

            if (! $jenisKendaraan->flag) {
                return response()->json([
                    'success' => false,
                    'message' => 'Jenis kendaraan ini sudah dihapus/nonaktif',
                ], 403);
            }

            $jenisKendaraan->update([
                'nama_jenis' => $request->nama_jenis,
                'muatan_maksimal_ton' => $request->muatan_maksimal_ton,
            ]);

            DB::connection('sqlsrv')->commit();

            return response()->json([
                'success' => true,
                'message' => 'Jenis kendaraan berhasil diperbarui',
                'jenis_kendaraan' => $jenisKendaraan,
            ]);
        } catch (\Exception $e) {
            DB::connection('sqlsrv')->rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui jenis kendaraan: '.$e->getMessage(),
            ], 500);
        }
    }

    public function destroy($id)
    {
        try {
            DB::connection('sqlsrv')->beginTransaction();

            $jenisKendaraan = MasterJenisKendaraan::findOrFail($id);

            if (! $jenisKendaraan->flag) {
                return response()->json([
                    'success' => false,
                    'message' => 'Jenis kendaraan ini sudah dihapus sebelumnya',
                ], 403);
            }

            $jenisKendaraan->update(['flag' => false]);

            DB::connection('sqlsrv')->commit();

            return response()->json([
                'success' => true,
                'message' => 'Jenis kendaraan berhasil dihapus',
            ]);
        } catch (\Exception $e) {
            DB::connection('sqlsrv')->rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus jenis kendaraan: '.$e->getMessage(),
            ], 500);
        }
    }
}

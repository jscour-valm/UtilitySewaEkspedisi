<?php

namespace App\Http\Controllers;

use App\Models\JenisBiaya;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * CRUD Jenis Biaya Tambahan (Master Data, role DCI). Model JenisBiaya sudah
 * lama ada (dipakai read-only di PengajuanController::getJenisBiaya() buat
 * dropdown KG pas submit pengajuan), tapi belum pernah ada halaman kelola-nya
 * sampai sekarang — controller ini yang pertama.
 */
class JenisBiayaController extends Controller
{
    public function index()
    {
        $jenisBiaya = JenisBiaya::orderBy('nama_biaya')->get();

        return view('pages.master.jenis-biaya-tambahan', compact('jenisBiaya'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'nama_biaya' => 'required|string|max:100|unique:sqlsrv.dbo.sesi_jenis_biaya,nama_biaya',
        ]);

        try {
            DB::connection('sqlsrv')->beginTransaction();

            $jenisBiaya = JenisBiaya::create([
                'nama_biaya' => $request->nama_biaya,
                'flag' => true,
            ]);

            DB::connection('sqlsrv')->commit();

            return response()->json([
                'success' => true,
                'message' => 'Jenis biaya berhasil ditambahkan',
                'jenis_biaya' => $jenisBiaya,
            ], 201);
        } catch (\Exception $e) {
            DB::connection('sqlsrv')->rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal menyimpan jenis biaya: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'nama_biaya' => 'required|string|max:100|unique:sqlsrv.dbo.sesi_jenis_biaya,nama_biaya,' . $id . ',id_jenis_biaya',
        ]);

        try {
            DB::connection('sqlsrv')->beginTransaction();

            $jenisBiaya = JenisBiaya::findOrFail($id);

            if (!$jenisBiaya->flag) {
                return response()->json([
                    'success' => false,
                    'message' => 'Jenis biaya ini sudah dihapus/nonaktif',
                ], 403);
            }

            $jenisBiaya->update(['nama_biaya' => $request->nama_biaya]);

            DB::connection('sqlsrv')->commit();

            return response()->json([
                'success' => true,
                'message' => 'Jenis biaya berhasil diperbarui',
                'jenis_biaya' => $jenisBiaya,
            ]);
        } catch (\Exception $e) {
            DB::connection('sqlsrv')->rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui jenis biaya: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function destroy($id)
    {
        try {
            DB::connection('sqlsrv')->beginTransaction();

            $jenisBiaya = JenisBiaya::findOrFail($id);

            if (!$jenisBiaya->flag) {
                return response()->json([
                    'success' => false,
                    'message' => 'Jenis biaya ini sudah dihapus sebelumnya',
                ], 403);
            }

            $jenisBiaya->update(['flag' => false]);

            DB::connection('sqlsrv')->commit();

            return response()->json([
                'success' => true,
                'message' => 'Jenis biaya berhasil dihapus',
            ]);
        } catch (\Exception $e) {
            DB::connection('sqlsrv')->rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus jenis biaya: ' . $e->getMessage(),
            ], 500);
        }
    }
}

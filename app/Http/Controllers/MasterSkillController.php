<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ManagesVendorMasterData;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Master Skill (area kirim per cabang): sesi_master_skill (nama area, global) +
 * sesi_cabang_skill (area terdaftar di cabang). 1 baris tabel = 1 pasangan cabang + area.
 *
 * Lihat/tambah/ganti nama: WM (cabang sendiri), WC, WH, DCI. Hapus (flag 0 di sesi_cabang_skill): DCI.
 * KG menambah area lewat form kendaraan di wizard pengajuan (resolveSkillId).
 */
class MasterSkillController extends Controller
{
    use ManagesVendorMasterData;

    public function index(Request $request)
    {
        $user = auth()->user();
        $global = $user->isGlobalAccess();
        $own = $global ? [] : array_values(array_unique($user->getCabangIds() ?: array_filter([$user->getCabangId()])));
        $db = DB::connection('sqlsrv');

        $search = trim((string) $request->get('search', ''));
        $cabang = strtoupper(trim((string) $request->get('cabang', '')));

        $rows = $db->table('sesi_cabang_skill as cs')
            ->join('sesi_master_skill as ms', 'ms.id_skill', '=', 'cs.id_skill')
            ->leftJoin('sesi_master_cabang as mc', function ($join) {
                $join->on('cs.cabang_code', '=', DB::raw('mc.Code COLLATE SQL_Latin1_General_CP1_CI_AS'));
            })
            ->where('cs.flag', true)->where('ms.flag', true)
            ->when(! $global, fn ($q) => $q->whereIn('cs.cabang_code', $own ?: ['__none__']))
            ->when($cabang !== '', fn ($q) => $q->where('cs.cabang_code', $cabang))
            ->when($search !== '', fn ($q) => $q->where('ms.nama_skill', 'like', '%'.$search.'%'))
            ->orderBy('cs.cabang_code')->orderBy('ms.nama_skill')
            ->select(['cs.cabang_code', 'mc.Name as nama_cabang', 'ms.id_skill', 'ms.nama_skill', 'cs.created_at'])
            ->paginate(50)->withQueryString();

        $cabangList = $db->table('sesi_master_cabang')
            ->when(! $global, fn ($q) => $q->whereIn('Code', $own ?: ['__none__']))
            ->orderBy('Code')->get(['Code', 'Name']);

        return view('pages.master.skill', [
            'rows' => $rows,
            'cabangList' => $cabangList,
            'search' => $search,
            'cabang' => $cabang,
            'isDci' => $user->userUtility?->role === 'DCI',
        ]);
    }

    /** POST /api/master-skill — daftarkan area ke cabang (area baru dibuat kalau namanya belum ada). */
    public function store(Request $request)
    {
        $data = $request->validate([
            'nama_skill' => 'required|string|max:100',
            'cabang_code' => 'required|string|max:10|exists:sqlsrv.dbo.sesi_master_cabang,Code',
        ]);
        $cabang = strtoupper(trim($data['cabang_code']));
        $nama = strtoupper(trim($data['nama_skill']));

        if (! auth()->user()->canAccessCabang($cabang)) {
            return response()->json(['success' => false, 'message' => 'Anda tidak punya akses ke cabang ini.'], 403);
        }

        $db = DB::connection('sqlsrv');
        $sudahAda = $db->table('sesi_cabang_skill as cs')
            ->join('sesi_master_skill as ms', 'ms.id_skill', '=', 'cs.id_skill')
            ->where('cs.cabang_code', $cabang)->where('ms.nama_skill', $nama)
            ->where('cs.flag', true)->where('ms.flag', true)
            ->exists();
        if ($sudahAda) {
            return response()->json(['success' => false, 'message' => "Area {$nama} sudah terdaftar di cabang {$cabang}."], 422);
        }

        $db->transaction(fn () => $this->resolveSkillId($nama, $cabang));

        return response()->json(['success' => true, 'message' => "Area {$nama} ditambahkan ke cabang {$cabang}."]);
    }

    /**
     * PUT /api/master-skill/{id} — ganti nama area. Nama berlaku di semua cabang, jadi user
     * non-global hanya boleh mengganti area yang tidak terdaftar di cabang lain.
     */
    public function update(Request $request, int $id)
    {
        $data = $request->validate(['nama_skill' => 'required|string|max:100']);
        $nama = strtoupper(trim($data['nama_skill']));
        $db = DB::connection('sqlsrv');

        $skill = $db->table('sesi_master_skill')->where('id_skill', $id)->where('flag', true)->first();
        if (! $skill) {
            return response()->json(['success' => false, 'message' => 'Area tidak ditemukan.'], 404);
        }

        $user = auth()->user();
        if (! $user->isGlobalAccess()) {
            $cabangArea = $db->table('sesi_cabang_skill')->where('id_skill', $id)->where('flag', true)->pluck('cabang_code')->all();
            $own = $user->getCabangIds() ?: array_filter([$user->getCabangId()]);
            if (! $cabangArea || array_diff($cabangArea, $own)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Area ini juga terdaftar di cabang lain. Ganti nama lewat WC, WH, atau DCI.',
                ], 403);
            }
        }

        if ($db->table('sesi_master_skill')->where('nama_skill', $nama)->where('id_skill', '!=', $id)->exists()) {
            return response()->json(['success' => false, 'message' => "Nama area {$nama} sudah dipakai."], 422);
        }

        $db->table('sesi_master_skill')->where('id_skill', $id)->update(['nama_skill' => $nama, 'updated_at' => now()]);

        return response()->json(['success' => true, 'message' => 'Nama area diperbarui.']);
    }

    /**
     * DELETE /api/master-skill/{id}?cabang_code=XXX (DCI) — cabut area dari 1 cabang (flag 0).
     * Tarif & kendaraan yang sudah memakai area ini tidak ikut berubah.
     */
    public function destroy(Request $request, int $id)
    {
        $cabang = strtoupper(trim((string) $request->query('cabang_code', '')));
        $affected = DB::connection('sqlsrv')->table('sesi_cabang_skill')
            ->where('cabang_code', $cabang)->where('id_skill', $id)->where('flag', true)
            ->update(['flag' => false, 'updated_at' => now()]);

        if (! $affected) {
            return response()->json(['success' => false, 'message' => 'Area di cabang ini tidak ditemukan.'], 404);
        }

        return response()->json(['success' => true, 'message' => 'Area dihapus dari cabang '.$cabang.'.']);
    }
}

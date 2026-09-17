<?php

namespace App\Http\Controllers;

use App\Models\Approval;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Halaman "Setting Approver" (role: DCI) — kelola manual WM mana yang bisa
 * approve cabang mana lewat sesi_user_cabang, tabel yang BENERAN dipakai
 * buat cek otorisasi (User::canAccessCabang(), dipanggil dari
 * ApprovalController::approve()/reject()). Data default-nya sudah di-sync
 * otomatis dari view eksternal IT (lihat UserCabangSeeder) — halaman ini
 * buat kasus khusus (cabang baru/belum ke-cover sync, cabang tanpa WM).
 *
 * sesi_approval (approver resmi per cabang buat pencatatan/log) TIDAK dipakai
 * buat cek otorisasi, jadi nambah baris di sesi_user_cabang lewat sini SUDAH
 * CUKUP bikin WM itu bisa approve — tidak perlu ubah ApprovalController.
 * Tetap auto-upsert 1 baris sesi_approval (kalau belum ada rule utk cabang
 * itu) biar log approval ke depannya bisa ke-attach id_approval_rule.
 */
class ApproverController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->get('search', ''));
        $area = trim((string) $request->get('area', ''));

        $query = DB::connection('sqlsrv')->table('sesi_master_cabang')
            ->whereNotNull('Name');

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('Code', 'like', "%{$search}%")
                  ->orWhere('Name', 'like', "%{$search}%");
            });
        }

        if ($area !== '') {
            $query->where('Area', $area);
        }

        $cabangs = $query->orderBy('Area')
            ->orderBy('Name')
            ->paginate(25)
            ->withQueryString();

        $codes = collect($cabangs->items())->pluck('Code');

        // Semua WM aktif per cabang di halaman ini sekaligus (hindari N+1),
        // join manual ke lntrn_users (via username) buat dapetin nama.
        $wmByCabang = DB::connection('sqlsrv')->table('sesi_user_cabang as uc')
            ->join('lntrn_users as u', 'u.username', '=', 'uc.username')
            ->whereIn('uc.cabang_code', $codes)
            ->where('uc.role', 'WM')
            ->where('uc.flag', true)
            ->orderBy('u.name')
            ->get(['uc.id_user_cabang', 'uc.cabang_code', 'u.id as user_id', 'u.name'])
            ->groupBy('cabang_code');

        $cabangs->getCollection()->transform(function ($c) use ($wmByCabang) {
            $c->wmList = $wmByCabang->get($c->Code, collect());
            return $c;
        });

        // Dropdown pilihan WM (dipakai form tambah approver, per-cabang & per-area)
        $wmUsers = User::whereHas('userUtility', fn ($q) => $q->where('role', 'WM'))
            ->orderBy('name')
            ->get(['id', 'name', 'username']);

        // Daftar Area unik (dipakai dropdown filter list & dropdown bulk-assign per area)
        $areaList = DB::connection('sqlsrv')->table('sesi_master_cabang')
            ->whereNotNull('Name')
            ->whereNotNull('Area')
            ->distinct()
            ->orderBy('Area')
            ->pluck('Area');

        return view('pages.setting-approver.index', compact('cabangs', 'search', 'area', 'areaList', 'wmUsers'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'cabang_code' => 'required|string|max:10|exists:sqlsrv.dbo.sesi_master_cabang,Code',
            'user_id' => 'required|integer|exists:sqlsrv.dbo.lntrn_users,id',
        ]);

        $wm = $this->findWm($request->user_id);
        if (!$wm) {
            return back()->withErrors(['error' => 'User yang dipilih bukan WM.'])->withInput();
        }

        $this->assignWmToCabang($wm, $request->cabang_code);

        return back()->with('success', "WM {$wm->name} berhasil di-assign ke cabang {$request->cabang_code}.");
    }

    /**
     * Bulk-assign 1 WM ke SEMUA cabang dalam 1 Area sekaligus — mempercepat
     * kasus 1 WM emang megang 1 area penuh, tanpa perlu klik satu-satu per
     * cabang. Pakai helper assignWmToCabang() yang sama kayak store(), jadi
     * behavior-nya (reaktivasi/insert sesi_user_cabang + auto-upsert
     * sesi_approval per cabang) identik dengan assign manual per cabang.
     */
    public function storeByArea(Request $request)
    {
        $request->validate([
            'area' => 'required|string|max:20',
            'user_id' => 'required|integer|exists:sqlsrv.dbo.lntrn_users,id',
        ]);

        $wm = $this->findWm($request->user_id);
        if (!$wm) {
            return back()->withErrors(['error' => 'User yang dipilih bukan WM.'])->withInput();
        }

        $codes = DB::connection('sqlsrv')->table('sesi_master_cabang')
            ->where('Area', $request->area)
            ->whereNotNull('Name')
            ->pluck('Code');

        if ($codes->isEmpty()) {
            return back()->withErrors(['error' => 'Area tidak ditemukan atau tidak punya cabang.'])->withInput();
        }

        foreach ($codes as $code) {
            $this->assignWmToCabang($wm, $code);
        }

        return back()->with('success', "WM {$wm->name} berhasil di-assign ke {$codes->count()} cabang di Area {$request->area}.");
    }

    private function findWm(int $userId): ?User
    {
        return User::whereHas('userUtility', fn ($q) => $q->where('role', 'WM'))->find($userId);
    }

    /**
     * Assign 1 WM ke 1 cabang: reaktivasi/insert baris sesi_user_cabang (tabel
     * yang BENERAN dipakai buat cek otorisasi approve), lalu auto-upsert
     * approval rule resmi di sesi_approval — TIDAK menimpa kalau rule buat
     * cabang itu udah ada (biar approver resmi yang udah ke-set nggak keganti
     * diam-diam).
     */
    private function assignWmToCabang(User $wm, string $cabangCode): void
    {
        $existing = DB::connection('sqlsrv')->table('sesi_user_cabang')
            ->where('username', $wm->username)
            ->where('cabang_code', $cabangCode)
            ->first();

        if ($existing) {
            DB::connection('sqlsrv')->table('sesi_user_cabang')
                ->where('id_user_cabang', $existing->id_user_cabang)
                ->update(['role' => 'WM', 'flag' => true, 'updated_at' => now()]);
        } else {
            DB::connection('sqlsrv')->table('sesi_user_cabang')->insert([
                'username' => $wm->username,
                'cabang_code' => $cabangCode,
                'area' => null,
                'role' => 'WM',
                'flag' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        Approval::withInactive()->firstOrCreate(
            ['id_cabang' => $cabangCode, 'role_berwenang' => 'WM', 'tingkat' => 1],
            ['id_approver' => $wm->id, 'flag' => true]
        );
    }

    public function destroy($id)
    {
        $row = DB::connection('sqlsrv')->table('sesi_user_cabang')
            ->where('id_user_cabang', $id)
            ->first();

        if (!$row) {
            return back()->withErrors(['error' => 'Data tidak ditemukan.']);
        }

        DB::connection('sqlsrv')->table('sesi_user_cabang')
            ->where('id_user_cabang', $id)
            ->update(['flag' => false, 'updated_at' => now()]);

        return back()->with('success', 'Approver berhasil dihapus dari cabang ini.');
    }
}

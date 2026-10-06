<?php

namespace App\Http\Controllers;

use App\Models\Approval;
use App\Models\User;
use App\Services\UserCabangResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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

        // Dropdown pilihan WM (dipakai form tambah approver, per-cabang & per-area)
        $wmUsers = User::whereHas('userUtility', fn ($q) => $q->where('role', 'WM'))
            ->orderBy('name')
            ->get(['id', 'name', 'username']);
        $wmUserByUsername = $wmUsers->keyBy('username');

        $overrideWmByCabang = UserCabangResolver::overrideWmMapByCabang();
        $liveWmByCabang = UserCabangResolver::liveWmMapByCabang();

        $cabangs->getCollection()->transform(function ($c) use ($overrideWmByCabang, $liveWmByCabang, $wmUserByUsername) {
            $override = $overrideWmByCabang->get($c->Code, collect())->map(function ($wm) {
                $wm->source = 'override';

                return $wm;
            });

            $liveUsernames = $liveWmByCabang->get($c->Code, collect())->pluck('username')->unique();
            $sudahAdaUserId = $override->pluck('user_id')->all();
            $live = $liveUsernames
                ->map(fn ($username) => $wmUserByUsername->get($username))
                ->filter()
                ->reject(fn ($u) => in_array($u->id, $sudahAdaUserId, true)) // hindari duplikat kalau kebetulan ada di override juga
                ->map(fn ($u) => (object) [
                    'id_approval_rule' => null,
                    'cabang_code' => $c->Code,
                    'user_id' => $u->id,
                    'name' => $u->name,
                    'username' => $u->username,
                    'source' => 'live',
                ]);

            $c->wmList = $override->merge($live)->sortBy('name')->values();

            return $c;
        });

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
        if (! $wm) {
            return back()->withErrors(['error' => 'User yang dipilih bukan WM.'])->withInput();
        }

        $this->assignWmToCabang($wm, $request->cabang_code);

        return back()->with('success', "WM {$wm->name} berhasil di-assign ke cabang {$request->cabang_code}.");
    }

    public function storeByArea(Request $request)
    {
        $request->validate([
            'area' => 'required|string|max:20',
            'user_id' => 'required|integer|exists:sqlsrv.dbo.lntrn_users,id',
        ]);

        $wm = $this->findWm($request->user_id);
        if (! $wm) {
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

    private function assignWmToCabang(User $wm, string $cabangCode): void
    {
        Approval::withInactive()->updateOrCreate(
            ['id_cabang' => $cabangCode, 'role_berwenang' => 'WM', 'tingkat' => 1],
            ['id_approver' => $wm->id, 'flag' => true]
        );

        UserCabangResolver::clearCache();
    }

    public function destroy($id)
    {
        $rule = Approval::withInactive()->find($id);

        if (! $rule || $rule->role_berwenang !== 'WM') {
            return back()->withErrors(['error' => 'Data tidak ditemukan.']);
        }

        $rule->update(['flag' => false]);

        UserCabangResolver::clearCache();

        return back()->with('success', 'Approver berhasil dihapus dari cabang ini.');
    }
}

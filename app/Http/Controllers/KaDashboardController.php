<?php

namespace App\Http\Controllers;

use App\Helpers\RentangTanggalDashboard;
use App\Models\PengajuanSewa;

class KaDashboardController extends Controller
{
    /**
     * Dashboard KA (KaAdmin) — view-only: cuma pengajuan berstatus approved
     * di cabang KA sendiri (pending/rejected tidak boleh terlihat).
     */
    public function index()
    {
        $cabangIds = auth()->user()->getCabangIds();

        $rentang = RentangTanggalDashboard::dariRequest();
        $countApproved = empty($cabangIds)
            ? 0
            : PengajuanSewa::whereIn('id_cabang', $cabangIds)
                ->where('status_pengajuan', 'approved')
                ->get(['submitted_at', 'created_at'])
                ->filter(fn ($p) => $rentang->mencakup($p->submitted_at ?? $p->created_at))
                ->count();

        return view('pages.dashboard.ka', [
            'countApproved' => $countApproved,
            'cabangIds' => $cabangIds,
        ]);
    }
}

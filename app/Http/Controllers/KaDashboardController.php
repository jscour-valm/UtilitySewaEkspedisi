<?php

namespace App\Http\Controllers;

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

        $countApproved = empty($cabangIds)
            ? 0
            : PengajuanSewa::whereIn('id_cabang', $cabangIds)
                ->where('status_pengajuan', 'approved')
                ->count();

        return view('pages.dashboard.ka', [
            'countApproved' => $countApproved,
            'cabangIds' => $cabangIds,
        ]);
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\PengajuanSewa;

class WhDashboardController extends Controller
{
    /**
     * Dashboard WH — pengajuan yang alur_approval-nya memuat WH, lintas semua cabang
     * (WH global access).
     */
    public function index()
    {
        return $this->dashboardPeran('WH', 'pages.dashboard.wh');
    }

    /**
     * Pengajuan yang melibatkan $peran di alurnya. "Pending" = benar-benar giliran
     * peran ini (PengajuanSewa::approverBerikutnya()), bukan sekadar status pending.
     */
    protected function dashboardPeran(string $peran, string $view)
    {
        $allPengajuan = PengajuanSewa::with(['approvalLogs.approval'])
            ->where(function ($q) use ($peran) {
                $q->where('alur_approval', 'like', "%{$peran}%");
                if ($peran === 'WH') {
                    $q->orWhere(fn ($qq) => $qq->whereNull('alur_approval')->where('kategori_approval', 'over_threshold'));
                }
            })
            ->orderBy('submitted_at', 'desc')
            ->get();

        return view($view, [
            'allPengajuan' => $allPengajuan,
            'countTotal' => count($allPengajuan),
            'countPending' => $allPengajuan->filter(fn ($p) => $p->approverBerikutnya() === $peran)->count(),
            'countApproved' => $allPengajuan->filter(fn ($p) => strtolower($p->status_pengajuan) === 'approved')->count(),
            'countRejected' => $allPengajuan->filter(fn ($p) => strtolower($p->status_pengajuan) === 'rejected')->count(),
        ]);
    }
}

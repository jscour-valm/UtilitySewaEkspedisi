<?php

namespace App\Http\Controllers;

class WcDashboardController extends WhDashboardController
{
    /**
     * Dashboard WC (Warehouse Manager Coordinator) — pengajuan PAC (alur memuat WC),
     * lintas semua cabang.
     */
    public function index()
    {
        return $this->dashboardPeran('WC', 'pages.dashboard.wc');
    }
}

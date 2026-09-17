<?php

namespace App\Http\Controllers;

use App\Models\PengajuanSewa;

class DciDashboardController extends Controller
{
    /**
     * Dashboard DCI — read-only, lintas semua cabang (DCI global access,
     * lihat App\Models\User::isGlobalAccess()). Cuma ringkasan angka +
     * tabel (datanya diambil sendiri sama <x-tabel-pengajuan>, otomatis
     * scope ke semua cabang buat role global-access) — nggak butuh
     * eager-load kendaraan/perusahaan per baris di sini kayak
     * WmDashboardController (yang punya bug laten null-pointer buat
     * jenis_pengajuan=pengiriman_rutin krn id_kendaraan null).
     */
    public function index()
    {
        $query = PengajuanSewa::query();

        $countTotal         = (clone $query)->count();
        $countPending       = (clone $query)->where('status_pengajuan', 'Pending')->count();
        $countApproved      = (clone $query)->where('status_pengajuan', 'Approved')->count();
        $countRejected      = (clone $query)->where('status_pengajuan', 'Rejected')->count();
        $countOverThreshold = (clone $query)->where('kategori_approval', 'over_threshold')->count();

        return view('pages.dashboard.dci', compact(
            'countTotal',
            'countPending',
            'countApproved',
            'countRejected',
            'countOverThreshold'
        ));
    }
}

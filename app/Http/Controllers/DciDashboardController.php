<?php

namespace App\Http\Controllers;

use App\Helpers\RentangTanggalDashboard;
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
        $rentang = RentangTanggalDashboard::dariRequest();
        $pengajuan = PengajuanSewa::get(['status_pengajuan', 'kategori_approval', 'submitted_at', 'created_at'])
            ->filter(fn ($p) => $rentang->tampil('DCI', $p->submitted_at ?? $p->created_at, $p->status_pengajuan, null));
        $status = fn (string $s) => $pengajuan->filter(fn ($p) => strtolower($p->status_pengajuan) === $s)->count();

        $countTotal = $pengajuan->count();
        $countPending = $status('pending');
        $countApproved = $status('approved');
        $countRejected = $status('rejected');
        $countOverThreshold = $pengajuan->where('kategori_approval', 'over_threshold')->count();

        return view('pages.dashboard.dci', compact(
            'countTotal',
            'countPending',
            'countApproved',
            'countRejected',
            'countOverThreshold'
        ));
    }
}

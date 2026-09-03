<?php

namespace App\Http\Controllers;

use App\Models\PengajuanSewa;
use Illuminate\Http\Request;

class WmDashboardController extends Controller
{
    /**
     * Show WM dashboard with pending pengajuan
     */
    public function index()
    {
        // Get ALL cabangs untuk WM (area-based role bisa pegang multiple cabang dalam 1 area)
        $userCabangs = auth()->user()->getCabangIds();

        // Fetch all pengajuan untuk semua cabang WM (status = pending, approved, rejected)
        $query = PengajuanSewa::with([
            'armada',
            'armada.perusahaan',
            'submittedBy',
        ]);

        // WM sees pengajuan from ALL cabangs dalam areanya
        if (!empty($userCabangs)) {
            $query->whereIn('id_cabang', $userCabangs);
        } else {
            // Fallback: if no cabangs found, return empty
            $query->whereRaw('1=0');
        }

        $allPengajuan = $query->orderBy('submitted_at', 'desc')->get();

        // Count by status
        $countPending = $allPengajuan->filter(fn($p) => strtolower($p->status_pengajuan) === 'pending')->count();
        $countApproved = $allPengajuan->filter(fn($p) => strtolower($p->status_pengajuan) === 'approved')->count();
        $countRejected = $allPengajuan->filter(fn($p) => strtolower($p->status_pengajuan) === 'rejected')->count();

        // Format for table display
        $pengajuanData = $allPengajuan->map(fn($p) => [
            'id' => $p->id_pengajuan_sewa,
            'kagud' => $p->submittedBy?->name ?? 'Unknown',
            'cabang' => $p->id_cabang,
            'perusahaan' => $p->armada->perusahaan->nama_perusahaan ?? '-',
            'tanggal' => $p->tanggal_pengiriman->format('d M Y'),
            'harga_sewa' => number_format($p->harga_sewa, 0, ',', '.'),
            'rasio' => number_format($p->rasio_sewa, 2, ',', '.'),
            'status' => strtolower($p->status_pengajuan),
            'kategori' => $p->kategori_approval,
        ])->toArray();

        return view('pages.dashboard.wm', [
            'allPengajuan' => $allPengajuan,
            'pengajuanData' => json_encode($pengajuanData),
            'countTotal' => count($allPengajuan),
            'countPending' => $countPending,
            'countApproved' => $countApproved,
            'countRejected' => $countRejected,
        ]);
    }
}

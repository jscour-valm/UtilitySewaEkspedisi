<?php

namespace App\Http\Controllers;

use App\Helpers\FormatHelper;
use App\Models\Approval;
use App\Models\ApprovalLog;
use App\Models\Kendaraan;
use App\Models\PengajuanSewa;
use App\Models\RasioSewa;
use App\Services\HargaMasterPengajuanService;
use App\Services\NotifikasiPengajuanService;
use App\Services\SnapshotDokumenService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ApprovalController extends Controller
{
    /** Peran yang bisa ada di alur_approval pengajuan (urutan tingkat). */
    public const PERAN_APPROVER = ['WM', 'WC', 'WH'];

    /** WM = "Validasi", WC/WH = "Approval" (istilah di Wewenang Role). */
    public static function labelMenunggu(string $peran): string
    {
        return $peran === 'WM' ? 'Menunggu Validasi WM' : "Menunggu Approval {$peran}";
    }

    /**
     * Get vendor lain dengan skill yang sama untuk referensi harga
     */
    private function getVendorLain(PengajuanSewa $pengajuan)
    {
        // Perbandingan vendor lain berbasis kendaraan+skill — cuma relevan utk sewa_truk.
        // Kiriman rutin gak punya "kendaraan", jadi belum ada perbandingan setara.
        if ($pengajuan->jenis_pengajuan === 'pengiriman_rutin') {
            return [];
        }

        try {
            // Parse skill dari pengajuan (comma-separated)
            $pengajuanSkills = array_map(
                fn ($s) => strtoupper(trim($s)),
                array_filter(explode(',', $pengajuan->kendaraan->id_skill), fn ($s) => $s !== '')
            );

            if (empty($pengajuanSkills)) {
                return [];
            }

            // Query kendaraan lain dengan skill yang mungkin match
            $candidateKendaraan = Kendaraan::with('perusahaan')
                ->where('flag', true)
                ->where('id_kendaraan', '!=', $pengajuan->id_kendaraan)
                ->get();

            $vendorLain = [];

            foreach ($candidateKendaraan as $kendaraan) {
                // Parse skill dari kendaraan kandidat
                $kendaraanSkills = array_map(
                    fn ($s) => strtoupper(trim($s)),
                    array_filter(explode(',', $kendaraan->id_skill), fn ($s) => $s !== '')
                );

                // Check apakah ada skill yang sama (intersect)
                $skillIntersect = array_intersect($kendaraanSkills, $pengajuanSkills);

                if (! empty($skillIntersect)) {
                    // Ambil harga referensi dari pengajuan terbaru yang approved
                    $lastApprovedPengajuan = $kendaraan->pengajuan()
                        ->where('status_pengajuan', 'approved')
                        ->orderByDesc('submitted_at')
                        ->first();

                    // Fallback ke pengajuan terbaru apa pun kalau belum ada yang approved
                    $lastPengajuan = $lastApprovedPengajuan ?? $kendaraan->pengajuan()
                        ->orderByDesc('submitted_at')
                        ->first();

                    $hargaSewa = $lastPengajuan ? $lastPengajuan->harga_sewa : null;

                    $vendorLain[] = [
                        'nama_vendor' => $kendaraan->perusahaan->nama_perusahaan,
                        'skill' => implode(',', FormatHelper::skillNames($kendaraan->id_skill)),
                        'harga_sewa' => $hargaSewa,
                        'muatan' => FormatHelper::ton($kendaraan->muatan_maksimal),
                        'badan_usaha' => $kendaraan->perusahaan->badan_usaha,
                    ];
                }
            }

            // Limit hasil (max 5 vendor)
            return array_slice($vendorLain, 0, 5);
        } catch (\Exception $e) {
            \Log::warning('Error getting vendor lain: '.$e->getMessage());

            return [];
        }
    }

    /**
     * Show approval detail view — dishare WM (tingkat 1) & WH (tingkat 2).
     */
    public function show($id)
    {
        $pengajuan = PengajuanSewa::with([
            'kendaraan',
            'kendaraan.perusahaan',
            'perusahaanEkspedisi',
            'biayaTambahan',
            'biayaTambahan.jenisBiaya',
            'approvalLogs.approver',
            'approvalLogs.approval',
            'submittedBy',
            'detailKirimanRutin.jenisBarang',
        ])->findOrFail($id);

        $userRole = auth()->user()->userUtility?->role;

        if (! in_array($userRole, self::PERAN_APPROVER, true)) {
            abort(403, 'Anda tidak memiliki akses ke fitur ini');
        }

        // WM hanya bisa akses pengajuan dari cabang mereka sendiri (WH/WC global,
        // canAccessCabang() otomatis true lewat isGlobalAccess()).
        if (! auth()->user()->canAccessCabang($pengajuan->id_cabang)) {
            abort(403, 'Anda hanya bisa mengakses pengajuan dari cabang Anda sendiri');
        }

        $isPending = strtolower($pengajuan->status_pengajuan) === 'pending';

        // Boleh action cuma kalau memang giliran peran ini di alur_approval pengajuan
        // (WM → WC → WH). Selain itu read-only — termasuk WM yang sudah memvalidasi.
        $approverBerikutnya = $pengajuan->approverBerikutnya();
        $canAct = $approverBerikutnya === $userRole;
        $pendingLabel = $approverBerikutnya ? self::labelMenunggu($approverBerikutnya) : 'Menunggu Validasi WM';

        // Fetch approval logs (timeline + historical)
        $approvalLogs = ApprovalLog::where('id_pengajuan_sewa', $id)
            ->with('approver')
            ->orderBy('decided_at', 'asc')
            ->get();

        // Build timeline data
        $timeline = $this->buildTimeline($approvalLogs, $pengajuan);

        // Get vendor lain untuk referensi harga
        $vendorLain = $this->getVendorLain($pengajuan);

        // Check apakah WM sudah approve (lihat approval log terbaru yang bukan rejection)
        $wmApprovedLog = $approvalLogs
            ->filter(fn ($log) => strtolower($log->status) === 'approved')
            ->last();
        $isWmApproved = $wmApprovedLog !== null;

        // Get ambang rasio sewa yang aktif dari database
        $rasioSewaSetting = RasioSewa::aktif();
        $ambangRasio = $rasioSewaSetting ? $rasioSewaSetting->persentase_maksimal : 2.5; // Fallback ke 2.5

        // Get dummy dokumen SJ/TO-ACB
        $dokumen = app(SnapshotDokumenService::class)->ambil($pengajuan);

        // Get history of this vendor (all pengajuan dari perusahaan yang sama — lewat
        // kendaraan (sewa_truk) ATAU id_perusahaan_ekspedisi langsung (pengiriman_rutin),
        // pola join yang sama dipakai di halaman Perusahaan).
        $idPerusahaan = $pengajuan->jenis_pengajuan === 'pengiriman_rutin'
            ? $pengajuan->id_perusahaan_ekspedisi
            : $pengajuan->kendaraan?->id_perusahaan;

        $historyVendor = $idPerusahaan
            ? PengajuanSewa::where(function ($q) use ($idPerusahaan) {
                $q->where('id_perusahaan_ekspedisi', $idPerusahaan)
                    ->orWhereHas('kendaraan', fn ($k) => $k->where('id_perusahaan', $idPerusahaan));
            })
                ->where('id_pengajuan_sewa', '!=', $pengajuan->id_pengajuan_sewa)
                ->with(['approvalLogs.approver'])
                ->orderByDesc('submitted_at')
                ->limit(10)
                ->get()
            : collect();

        return view('pages.pengajuan.approval-review', [
            'pengajuan' => $pengajuan,
            'timeline' => $timeline,
            'approvalLogs' => $approvalLogs,
            'vendorLain' => $vendorLain,
            'historyVendor' => $historyVendor,
            'dokumen' => $dokumen,
            'isWmApproved' => $isWmApproved,
            'ambangRasio' => $ambangRasio,
            'isPending' => $isPending,
            'canAct' => $canAct,
            'actingRole' => $userRole,
            'pendingLabel' => $pendingLabel,
            'alurApproval' => $pengajuan->alurApproval(),
            'peranSudahApprove' => $pengajuan->peranSudahApprove(),
            'approverBerikutnya' => $approverBerikutnya,
            'hargaMaster' => app(HargaMasterPengajuanService::class)->ambil($pengajuan),
            'vendorMenunggu' => strtolower($pengajuan->status_pengajuan) === 'pending' ? $pengajuan->vendorMenungguApproval() : null,
            'breadcrumb' => [
                'back_url' => route('dashboard'),
                'back_label' => 'Dashboard',
                'title' => 'Detail Pengajuan',
                'status' => strtolower($pengajuan->status_pengajuan),
                'jenis' => $pengajuan->jenis_pengajuan,
            ],
        ]);
    }

    /**
     * Build timeline from approval logs
     * Handles resubmit scenarios (synthetic entries + historical approvals)
     */
    private function buildTimeline($approvalLogs, $pengajuan)
    {
        $timeline = [];
        $lastApprovalDate = null;
        $rejectionDates = [];

        // Collect all rejection dates to detect resubmits
        foreach ($approvalLogs as $log) {
            if (strtolower($log->status) === 'rejected') {
                $rejectionDates[] = $log->decided_at;
            }
        }

        // Group logs by approval attempt (before/after rejections)
        $currentGroup = [];
        foreach ($approvalLogs as $log) {
            $currentGroup[] = $log;

            if (strtolower($log->status) === 'rejected') {
                // Process this group as a rejected attempt
                $this->processRejectionGroup($currentGroup, $timeline);
                $currentGroup = [];
                $lastApprovalDate = $log->decided_at;
            }
        }

        // Process remaining logs (current attempt)
        if (! empty($currentGroup)) {
            $this->processCurrentAttempt($currentGroup, $timeline);
        }

        // Sort by date
        usort($timeline, fn ($a, $b) => $a['decided_at'] <=> $b['decided_at']);

        return $timeline;
    }

    /**
     * Process a rejected approval group (historical)
     */
    private function processRejectionGroup(&$logs, &$timeline)
    {
        foreach ($logs as $log) {
            $timeline[] = [
                'type' => 'approval',
                'approver' => $log->approver,
                'status' => $log->status,
                'decided_at' => $log->decided_at,
                'reason' => $log->alasan_penolakan,
                'isHistorical' => true, // Mark as previous attempt
            ];
        }
    }

    /**
     * Process current attempt logs
     */
    private function processCurrentAttempt(&$logs, &$timeline)
    {
        foreach ($logs as $log) {
            $timeline[] = [
                'type' => 'approval',
                'approver' => $log->approver,
                'status' => $log->status,
                'decided_at' => $log->decided_at,
                'reason' => $log->alasan_penolakan,
                'isHistorical' => false,
            ];
        }
    }

    /**
     * Cari Approval rule yang cocok buat role tertentu. WM: 1 rule per cabang.
     * WH: 1 rule global (id_cabang null), tingkat 2.
     */
    private function findApprovalRule(string $role, PengajuanSewa $pengajuan): ?Approval
    {
        $query = Approval::where('role_berwenang', $role);

        return $role === 'WM'
            ? $query->where('id_cabang', $pengajuan->id_cabang)->first()
            : $query->whereNull('id_cabang')->first();
    }

    /**
     * Validasi (WM) / approval (WC, WH) sesuai urutan alur_approval pengajuan.
     * Status jadi approved saat peran terakhir di alur menyetujui.
     */
    public function approve(Request $request, $id)
    {
        return $this->putuskan($id, 'approved', null);
    }

    /**
     * Tolak — oleh peran yang sedang giliran. Penolakan langsung final.
     */
    public function reject(Request $request, $id)
    {
        $request->validate([
            'alasan_penolakan' => 'required|string|max:500',
        ]);

        return $this->putuskan($id, 'rejected', $request->alasan_penolakan);
    }

    private function putuskan($id, string $keputusan, ?string $alasan)
    {
        $user = auth()->user();
        $userRole = $user->userUtility?->role;

        if (! in_array($userRole, self::PERAN_APPROVER, true)) {
            return response()->json(['error' => 'Anda tidak memiliki akses'], 403);
        }

        $db = DB::connection('sqlsrv');

        try {
            $db->beginTransaction();

            // Kunci baris supaya 2 klik bersamaan tidak menghasilkan 2 log (bug WM validasi 2x).
            $pengajuan = PengajuanSewa::lockForUpdate()->findOrFail($id);
            $pengajuan->load('approvalLogs.approval');

            if (! $user->canAccessCabang($pengajuan->id_cabang)) {
                $db->rollBack();

                return response()->json(['error' => 'Anda hanya bisa memproses pengajuan dari cabang Anda'], 403);
            }

            $giliran = $pengajuan->approverBerikutnya();
            if ($giliran !== $userRole) {
                $db->rollBack();
                $pesan = $giliran
                    ? 'Belum giliran Anda — pengajuan ini '.lcfirst(self::labelMenunggu($giliran)).'.'
                    : 'Pengajuan ini sudah tidak menunggu keputusan.';

                return response()->json(['error' => $pesan], 400);
            }

            // Vendor baru harus disetujui WH dulu sebelum pengajuannya divalidasi (menolak tetap boleh).
            $vendorMenunggu = $keputusan === 'approved' ? $pengajuan->vendorMenungguApproval() : null;
            if ($vendorMenunggu) {
                $db->rollBack();

                return response()->json(['error' => "Vendor {$vendorMenunggu->nama_perusahaan} masih ".lcfirst($vendorMenunggu->labelStatusPersetujuan()).'. Pengajuan ini baru bisa divalidasi setelah vendornya disetujui WH.'], 400);
            }

            $alur = $pengajuan->alurApproval();
            ApprovalLog::create([
                'id_pengajuan_sewa' => $pengajuan->id_pengajuan_sewa,
                'id_approval_rule' => $this->findApprovalRule($userRole, $pengajuan)?->id_approval_rule,
                'id_approver' => $user->id,
                'role_approver' => $userRole,
                'tingkat' => array_search($userRole, $alur, true) + 1,
                'status' => $keputusan,
                'decided_at' => now(),
                'alasan_penolakan' => $alasan,
            ]);

            $pengajuan->load('approvalLogs.approval');
            $berikutnya = $keputusan === 'approved' ? $pengajuan->approverBerikutnya() : null;
            if ($keputusan === 'rejected') {
                $pengajuan->status_pengajuan = 'rejected';
            } elseif ($berikutnya === null) {
                $pengajuan->status_pengajuan = 'approved';
            }
            $pengajuan->save();

            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();

            return response()->json(['error' => $e->getMessage()], 500);
        }

        try {
            app(NotifikasiPengajuanService::class)->keputusan($pengajuan, $userRole, $berikutnya, $alasan);
        } catch (\Throwable $e) {
            \Log::warning('Notifikasi keputusan pengajuan gagal: '.$e->getMessage());
        }

        return response()->json([
            'message' => $keputusan === 'rejected' ? 'Pengajuan berhasil ditolak' : 'Pengajuan berhasil disetujui',
            'kategori' => $pengajuan->kategori_approval,
            'berikutnya' => $berikutnya,
        ]);
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\PengajuanSewa;
use App\Models\ApprovalLog;
use App\Models\Approval;
use App\Models\Kendaraan;
use App\Models\RasioSewa;
use Illuminate\Http\Request;
use Carbon\Carbon;

class ApprovalController extends Controller
{
    /**
     * Get vendor lain dengan skill yang sama untuk referensi harga
     */
    private function getVendorLain(PengajuanSewa $pengajuan)
    {
        try {
            // Parse skill dari pengajuan (comma-separated)
            $pengajuanSkills = array_map(
                fn($s) => strtoupper(trim($s)),
                array_filter(explode(',', $pengajuan->kendaraan->id_skill), fn($s) => $s !== '')
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
                    fn($s) => strtoupper(trim($s)),
                    array_filter(explode(',', $kendaraan->id_skill), fn($s) => $s !== '')
                );

                // Check apakah ada skill yang sama (intersect)
                $skillIntersect = array_intersect($kendaraanSkills, $pengajuanSkills);

                if (!empty($skillIntersect)) {
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
                        'nama_vendor'  => $kendaraan->perusahaan->nama_perusahaan,
                        'skill'        => implode(',', \App\Helpers\FormatHelper::skillNames($kendaraan->id_skill)),
                        'harga_sewa'   => $hargaSewa,
                        'muatan'       => \App\Helpers\FormatHelper::ton($kendaraan->muatan_maksimal),
                        'badan_usaha'  => $kendaraan->perusahaan->badan_usaha,
                    ];
                }
            }

            // Limit hasil (max 5 vendor)
            return array_slice($vendorLain, 0, 5);
        } catch (\Exception $e) {
            \Log::warning('Error getting vendor lain: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get dummy SJ/TO-ACB dokumen untuk preview (placeholder hingga Quantum integration selesai)
     * TODO: Replace dengan data asli dari Quantum API/relasi SuratJalan begitu endpoint tersedia.
     * Struktur array sengaja disamakan dengan kolom tabel supaya swap-nya tinggal ganti sumber data.
     */
    private function getDummyDokumenSj(PengajuanSewa $pengajuan): array
    {
        return [
            [
                'no_dokumen'   => 'SJ/' . now()->format('Ym') . '/0001',
                'value_muatan' => $pengajuan->value_muatan,
                'qty'          => 1,
                'total_weight' => $pengajuan->kendaraan->muatan_maksimal ?? null,
            ],
        ];
    }

    /**
     * Show WM approval detail view
     */
    public function showForWm($id)
    {
        $pengajuan = PengajuanSewa::with([
            'kendaraan',
            'kendaraan.perusahaan',
            'biayaTambahan',
            'biayaTambahan.jenisBiaya',
            'approvalLogs.approver',
            'submittedBy',
        ])->findOrFail($id);

        // Check if user is WM and can approve
        $userRole = auth()->user()->userUtility?->role;

        if ($userRole !== 'WM') {
            abort(403, 'Anda tidak memiliki akses ke fitur ini');
        }

        // WM hanya bisa akses pengajuan dari cabang mereka sendiri
        if (!auth()->user()->canAccessCabang($pengajuan->id_cabang)) {
            abort(403, 'Anda hanya bisa mengakses pengajuan dari cabang Anda sendiri');
        }

        // Check if pengajuan is pending (untuk menentukan apakah action bar ditampilkan)
        $isPending = strtolower($pengajuan->status_pengajuan) === 'pending';

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
            ->filter(fn($log) => strtolower($log->status) === 'approved')
            ->last();
        $isWmApproved = $wmApprovedLog !== null;

        // Get ambang rasio sewa yang aktif dari database
        $rasioSewaSetting = RasioSewa::aktif();
        $ambangRasio = $rasioSewaSetting ? $rasioSewaSetting->persentase_maksimal : 2.5; // Fallback ke 2.5

        // Get dummy dokumen SJ/TO-ACB
        $dokumenSj = $this->getDummyDokumenSj($pengajuan);

        // Get history of this vendor (all pengajuan from same perusahaan)
        $idPerusahaan = $pengajuan->kendaraan->id_perusahaan;

        $historyVendor = PengajuanSewa::whereHas('kendaraan', function ($q) use ($idPerusahaan) {
                $q->where('id_perusahaan', $idPerusahaan);
            })
            ->where('id_pengajuan_sewa', '!=', $pengajuan->id_pengajuan_sewa)
            ->with(['approvalLogs.approver'])
            ->orderByDesc('submitted_at')
            ->limit(10)
            ->get();

        return view('pages.pengajuan.wm-approval', [
            'pengajuan' => $pengajuan,
            'timeline' => $timeline,
            'approvalLogs' => $approvalLogs,
            'vendorLain' => $vendorLain,
            'historyVendor' => $historyVendor,
            'dokumenSj' => $dokumenSj,
            'isWmApproved' => $isWmApproved,
            'ambangRasio' => $ambangRasio,
            'isPending' => $isPending,
            'breadcrumb' => [
                'back_url' => route('dashboard'),
                'back_label' => 'Dashboard',
                'title' => 'Detail Pengajuan',
                'status' => strtolower($pengajuan->status_pengajuan),
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
        if (!empty($currentGroup)) {
            $this->processCurrentAttempt($currentGroup, $timeline);
        }

        // Sort by date
        usort($timeline, fn($a, $b) => $a['decided_at'] <=> $b['decided_at']);

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
     * WM approves pengajuan
     */
    public function approve(Request $request, $id)
    {
        $pengajuan = PengajuanSewa::findOrFail($id);

        // Validation
        if (strtolower($pengajuan->status_pengajuan) !== 'pending') {
            return response()->json(['error' => 'Pengajuan tidak dalam status pending'], 400);
        }

        $userRole = auth()->user()->userUtility?->role;

        if ($userRole !== 'WM') {
            return response()->json(['error' => 'Anda tidak memiliki akses'], 403);
        }

        // WM hanya bisa approve pengajuan dari cabang sendiri
        if (!auth()->user()->canAccessCabang($pengajuan->id_cabang)) {
            return response()->json(['error' => 'Anda hanya bisa approve pengajuan dari cabang Anda'], 403);
        }

        try {
            // Find WM approval rule for this branch (1 rule per cabang for both kategori)
            $approvalRule = Approval::where('id_cabang', $pengajuan->id_cabang)
                ->where('role_berwenang', 'WM')
                ->first();

            if (!$approvalRule) {
                // Create minimal log entry without approval rule if not found
                // This allows WM to still approve
            }

            // Create approval log entry
            $logData = [
                'id_pengajuan_sewa' => $id,
                'id_approver' => auth()->id(),
                'status' => 'approved',
                'decided_at' => now(),
                'alasan_penolakan' => null,
            ];

            // Add approval rule if found
            if ($approvalRule) {
                $logData['id_approval_rule'] = $approvalRule->id_approval_rule;
            }

            $approvalLog = ApprovalLog::create($logData);

            // Check if over_threshold — if so, status remains pending (waiting for WH)
            // If normal, update status to approved
            if ($pengajuan->kategori_approval === 'over_threshold') {
                $pengajuan->status_pengajuan = 'pending'; // Still waiting for WH
            } else {
                $pengajuan->status_pengajuan = 'approved';
            }
            $pengajuan->save();

            // TODO: Send email notification

            return response()->json([
                'message' => 'Pengajuan berhasil disetujui',
                'kategori' => $pengajuan->kategori_approval,
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * WM rejects pengajuan
     */
    public function reject(Request $request, $id)
    {
        $request->validate([
            'alasan_penolakan' => 'required|string|max:500',
        ]);

        $pengajuan = PengajuanSewa::findOrFail($id);

        // Validation
        if (strtolower($pengajuan->status_pengajuan) !== 'pending') {
            return response()->json(['error' => 'Pengajuan tidak dalam status pending'], 400);
        }

        $userRole = auth()->user()->userUtility?->role;

        if ($userRole !== 'WM') {
            return response()->json(['error' => 'Anda tidak memiliki akses'], 403);
        }

        // WM hanya bisa reject pengajuan dari cabang sendiri
        if (!auth()->user()->canAccessCabang($pengajuan->id_cabang)) {
            return response()->json(['error' => 'Anda hanya bisa reject pengajuan dari cabang Anda'], 403);
        }

        try {
            // Find WM approval rule (1 rule per cabang for both kategori)
            $approvalRule = Approval::where('id_cabang', $pengajuan->id_cabang)
                ->where('role_berwenang', 'WM')
                ->first();

            // Create rejection log entry (append-only)
            $logData = [
                'id_pengajuan_sewa' => $id,
                'id_approver' => auth()->id(),
                'status' => 'rejected',
                'decided_at' => now(),
                'alasan_penolakan' => $request->alasan_penolakan,
            ];

            if ($approvalRule) {
                $logData['id_approval_rule'] = $approvalRule->id_approval_rule;
            }

            $approvalLog = ApprovalLog::create($logData);

            // Update pengajuan status to rejected
            $pengajuan->status_pengajuan = 'rejected';
            $pengajuan->save();

            // TODO: Send email notification with rejection reason

            return response()->json(['message' => 'Pengajuan berhasil ditolak']);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}

<?php

namespace App\Services;

use App\Mail\NotifikasiPengajuanMail;
use App\Models\Approval;
use App\Models\PengajuanSewa;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Email notifikasi alur approval (dokumentasi_alursistem.md sec 2.2-2.5).
 * Dikirim SETELAH response lewat defer() — tanpa tabel `jobs` (belum ada di DB shared)
 * dan kegagalan SMTP tidak pernah menggagalkan submit/approve/reject.
 * Semua DCI selalu di-CC.
 */
class NotifikasiPengajuanService
{
    public function __construct(private SnapshotDokumenService $snapshot) {}

    /** Pengajuan baru (atau diajukan ulang) masuk: To WM cabang, CC KG pengaju. Snapshot dokumen diperbarui. */
    public function pengajuanBaru(PengajuanSewa $p, bool $ulang = false): void
    {
        $this->kirim($p, 'baru', $this->emailWm($p->id_cabang), $this->emailKg($p), $ulang, true);
    }

    /**
     * Satu email per keputusan (validasi/approval/tolak), To KG pengaju:
     * - lanjut WC        → CC WM, WC
     * - lanjut WH        → CC WM, WH (+WC kalau tujuan PAC)
     * - final approved   → CC WM, WC, KA (+WH kalau alur punya tahap approval)
     * - ditolak          → CC WM + WC/WH yang ada di alur (tanpa KA)
     * DCI selalu di-CC lewat kirim().
     */
    public function keputusan(PengajuanSewa $p, string $peranPemutus, ?string $berikutnya, ?string $alasanPenolakan = null): void
    {
        [$tipe, $peranCc] = self::penerimaKeputusan($p, $peranPemutus, $berikutnya);

        $this->kirim(
            $p,
            $tipe,
            $this->emailKg($p),
            $this->emailPeran($peranCc, $p),
            false,
            false,
            $tipe === 'menunggu_approval' ? $berikutnya : null,
            $alasanPenolakan,
        );
    }

    /**
     * Pengajuan dibatalkan pengaju: To peran yang sedang giliran (fallback WM),
     * CC KG pengaju + peran yang sudah approve. Alasan ikut di badan email.
     */
    public function dibatalkan(PengajuanSewa $p, ?string $peranGiliran): void
    {
        [$to, $cc] = self::penerimaPembatalan($p, $peranGiliran);

        $this->kirim($p, 'dibatalkan', $this->emailPeran($to, $p), array_merge($this->emailKg($p), $this->emailPeran($cc, $p)), alasan: $p->alasan_pembatalan);
    }

    /** @return array{0: string[], 1: string[]} [peran To, peran CC selain KG & DCI] */
    public static function penerimaPembatalan(PengajuanSewa $p, ?string $peranGiliran): array
    {
        $sudah = array_values(array_diff($p->peranSudahApprove(), [$peranGiliran]));

        return [[$peranGiliran ?? 'WM'], $sudah];
    }

    /** @return array{0: string, 1: string[]} [tipe email, peran yang di-CC selain DCI] */
    public static function penerimaKeputusan(PengajuanSewa $p, string $peranPemutus, ?string $berikutnya): array
    {
        $alur = $p->alurApproval();

        if (strtolower($p->status_pengajuan) === 'rejected') {
            return ['rejected', array_merge(['WM'], array_values(array_intersect(['WC', 'WH'], $alur)))];
        }

        if ($berikutnya !== null) {
            $cc = ['WM', $peranPemutus, $berikutnya];
            if ($p->tujuan_penyewaan === 'PAC') {
                $cc[] = 'WC';
            }

            return ['menunggu_approval', array_values(array_unique($cc))];
        }

        // Final di WM saja (sewa ≤ batas rasio / rutin tanpa PAC): WM, WC, KA.
        // Final setelah approval WC/WH: WM, WC, WH, KA.
        return ['approved', $alur === ['WM'] ? ['WM', 'WC', 'KA'] : ['WM', 'WC', 'WH', 'KA']];
    }

    private function kirim(PengajuanSewa $p, string $tipe, array $to, array $ccTambahan = [], bool $ulang = false, bool $perbaruiSnapshot = false, ?string $peranBerikutnya = null, ?string $alasan = null): void
    {
        $to = array_values(array_unique($to));
        if (empty($to)) {
            Log::warning("Email notifikasi '$tipe' pengajuan #{$p->id_pengajuan_sewa} dilewati: tidak ada penerima beremail.");

            return;
        }

        // CC tambahan + semua DCI (kecuali yang sudah masuk To)
        $cc = array_values(array_diff(array_unique(array_merge($ccTambahan, $this->emailRole('DCI'))), $to));

        // Satu link untuk semua penerima: redirect sesuai role (approver → halaman review, lainnya → detail)
        $url = route('pengajuan.buka', $p->id_pengajuan_sewa);

        defer(function () use ($p, $tipe, $to, $cc, $url, $alasan, $ulang, $perbaruiSnapshot, $peranBerikutnya) {
            try {
                $dokumen = $perbaruiSnapshot ? $this->snapshot->simpan($p) : $this->snapshot->ambil($p);

                $mailable = new NotifikasiPengajuanMail(
                    $p->fresh(),
                    $tipe,
                    $url,
                    $alasan,
                    $dokumen,
                    $ulang,
                    $peranBerikutnya,
                );

                // ===== MODE TES (aktif) — semua email ke alamat tes (.env MAIL_TEST_TO), penerima asli hanya dicatat di log =====
                $testTo = config('mail.test_to');
                if (! $testTo) {
                    Log::warning("Email '$tipe' pengajuan #{$p->id_pengajuan_sewa} tidak dikirim: MAIL_TEST_TO kosong (mode tes).");

                    return;
                }
                Log::info("Email '$tipe' pengajuan #{$p->id_pengajuan_sewa} dikirim ke $testTo (mode tes). Penerima asli: To=".implode(',', $to).' CC='.implode(',', $cc));
                Mail::to($testTo)->send($mailable);

                // ===== PRODUKSI — UNCOMMENT saat deploy, lalu HAPUS blok MODE TES di atas =====
                // Mail::to($to)->cc($cc)->send($mailable);
            } catch (\Throwable $e) {
                Log::warning("Gagal kirim email '$tipe' pengajuan #{$p->id_pengajuan_sewa}: ".$e->getMessage());
            }
        });
    }

    /** KG = pengaju saja (bukan semua KG cabang). */
    private function emailKg(PengajuanSewa $p): array
    {
        return $this->emailUsers(User::whereKey($p->submitted_by)->get());
    }

    /** Email untuk daftar peran: WM/KA = cabang pengajuan, WH = approver global, WC = semua user role WC. */
    private function emailPeran(array $peran, PengajuanSewa $p): array
    {
        $emails = [];
        foreach (array_unique($peran) as $r) {
            $emails = array_merge($emails, match ($r) {
                'WM' => $this->emailWm($p->id_cabang),
                'WH' => $this->emailWh(),
                'KA' => $this->emailRoleDiCabang('KA', $p->id_cabang),
                default => $this->emailRole($r),
            });
        }

        return array_values(array_unique($emails));
    }

    /** WM cabang: approver (+cadangan) di sesi_approval; fallback user role WM di cabang itu. */
    private function emailWm(?string $cabang): array
    {
        $rule = Approval::where('role_berwenang', 'WM')->where('id_cabang', $cabang)->first();

        return $this->emailApprover($rule) ?: $this->emailRoleDiCabang('WM', $cabang);
    }

    /** WH (global): approver (+cadangan) di sesi_approval; fallback semua user role WH. */
    private function emailWh(): array
    {
        $rule = Approval::where('role_berwenang', 'WH')->whereNull('id_cabang')->first();

        return $this->emailApprover($rule) ?: $this->emailRole('WH');
    }

    private function emailApprover(?Approval $rule): array
    {
        if (! $rule) {
            return [];
        }

        $ids = array_filter([$rule->id_approver, $rule->id_approver_cadangan]);

        return $this->emailUsers(User::whereIn('id', $ids)->get());
    }

    private function emailRole(string $role): array
    {
        return $this->emailUsers(
            User::whereHas('userUtility', fn ($q) => $q->where('role', $role))->get()
        );
    }

    /**
     * Fallback kalau `sesi_approval` belum punya rule utk cabang ini. Task 18
     * (2 Okt 2026): `sesi_user_cabang` sudah dihapus total — override WM
     * sekarang nempel di `sesi_approval` (via `UserCabangResolver::
     * overrideWmMapByCabang()`), digabung sama live-query: WM lewat view
     * eksternal IT, KG/KA lewat `lntrn_users.branch_id`, pola sama kayak
     * `ApproverController::index()`.
     */
    private function emailRoleDiCabang(string $role, ?string $cabang): array
    {
        if (! $cabang) {
            return [];
        }

        $usernames = collect();

        if ($role === 'WM') {
            $overrideUsernames = UserCabangResolver::overrideWmMapByCabang()
                ->get(strtoupper($cabang), collect())
                ->pluck('username');
            $liveUsernames = UserCabangResolver::liveWmMapByCabang()
                ->get(strtoupper($cabang), collect())
                ->pluck('username');
            $usernames = $overrideUsernames->merge($liveUsernames)->unique();
        } elseif (in_array($role, ['KG', 'KA'], true)) {
            $usernames = UserCabangResolver::liveBranchIdMapByCabang()
                ->get(strtoupper($cabang), collect())
                ->pluck('username');
        }

        return $this->emailUsers(
            User::whereIn('username', $usernames)
                ->whereHas('userUtility', fn ($q) => $q->where('role', $role))
                ->get()
        );
    }

    private function emailUsers($users): array
    {
        return $users->pluck('email')
            ->map(fn ($e) => trim((string) $e))
            ->filter(fn ($e) => filter_var($e, FILTER_VALIDATE_EMAIL))
            ->unique()
            ->values()
            ->all();
    }
}

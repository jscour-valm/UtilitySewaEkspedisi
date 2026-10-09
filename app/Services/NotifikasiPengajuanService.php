<?php

namespace App\Services;

use App\Mail\NotifikasiPengajuanMail;
use App\Models\PengajuanSewa;

/**
 * Email notifikasi alur approval pengajuan sewa (dokumentasi_alursistem.md sec 2.2-2.5).
 * Pengiriman (defer, mode tes, CC DCI) lewat PengirimEmail.
 */
class NotifikasiPengajuanService
{
    public function __construct(
        private SnapshotDokumenService $snapshot,
        private PenerimaEmail $penerima,
        private PengirimEmail $pengirim,
    ) {}

    /** Pengajuan baru (atau diajukan ulang) masuk: To WM cabang, CC KG pengaju. Snapshot dokumen diperbarui. */
    public function pengajuanBaru(PengajuanSewa $p, bool $ulang = false): void
    {
        $this->kirim($p, 'baru', $this->penerima->wm($p->id_cabang), $this->emailKg($p), $ulang, true);
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
        // Satu link untuk semua penerima: redirect sesuai role (approver → halaman review, lainnya → detail)
        $url = route('pengajuan.buka', $p->id_pengajuan_sewa);

        $this->pengirim->kirim("'$tipe' pengajuan #{$p->id_pengajuan_sewa}", $to, $ccTambahan, function () use ($p, $tipe, $url, $alasan, $ulang, $perbaruiSnapshot, $peranBerikutnya) {
            $dokumen = $perbaruiSnapshot ? $this->snapshot->simpan($p) : $this->snapshot->ambil($p);

            return new NotifikasiPengajuanMail($p->fresh(), $tipe, $url, $alasan, $dokumen, $ulang, $peranBerikutnya);
        });
    }

    /** KG = pengaju saja (bukan semua KG cabang). */
    private function emailKg(PengajuanSewa $p): array
    {
        return $this->penerima->user($p->submitted_by);
    }

    /** Email untuk daftar peran: WM/KA = cabang pengajuan, WH = approver global, WC = semua user role WC. */
    private function emailPeran(array $peran, PengajuanSewa $p): array
    {
        return $this->penerima->peran($peran, $p->id_cabang);
    }
}

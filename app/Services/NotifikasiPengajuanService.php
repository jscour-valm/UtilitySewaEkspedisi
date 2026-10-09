<?php

namespace App\Services;

use App\Mail\NotifikasiPengajuanMail;
use App\Models\AturanEmail;
use App\Models\PengajuanSewa;

/**
 * Email notifikasi alur approval pengajuan sewa. Penerima per kejadian diatur DCI (AturanEmail);
 * pengiriman (defer, mode tes) lewat PengirimEmail.
 */
class NotifikasiPengajuanService
{
    public function __construct(
        private SnapshotDokumenService $snapshot,
        private PenerimaEmail $penerima,
        private PengirimEmail $pengirim,
    ) {}

    /** Pengajuan baru (atau diajukan ulang) masuk. Snapshot dokumen diperbarui. */
    public function pengajuanBaru(PengajuanSewa $p, bool $ulang = false): void
    {
        $aturan = AturanEmail::penerima('pengajuan_masuk');

        $this->kirim($p, 'baru', $this->emailPeran($aturan['to'], $p), $this->emailPeran($aturan['cc'], $p), $ulang, true);
    }

    /** Satu email per keputusan (validasi / approval / tolak), penerima sesuai kejadiannya. */
    public function keputusan(PengajuanSewa $p, string $peranPemutus, ?string $berikutnya, ?string $alasanPenolakan = null): void
    {
        [$tipe, $peranTo, $peranCc] = self::penerimaKeputusan($p, $peranPemutus, $berikutnya);

        $this->kirim(
            $p,
            $tipe,
            $this->emailPeran($peranTo, $p),
            $this->emailPeran($peranCc, $p),
            false,
            false,
            $tipe === 'menunggu_approval' ? $berikutnya : null,
            $alasanPenolakan,
        );
    }

    /**
     * Pengajuan dibatalkan pengaju: To peran yang sedang giliran (fallback WM),
     * CC KG pengaju + peran yang sudah approve + DCI. Alasan ikut di badan email.
     */
    public function dibatalkan(PengajuanSewa $p, ?string $peranGiliran): void
    {
        [$to, $cc] = self::penerimaPembatalan($p, $peranGiliran);

        $this->kirim($p, 'dibatalkan', $this->emailPeran($to, $p), $this->emailPeran(array_merge(['KG', 'DCI'], $cc), $p), alasan: $p->alasan_pembatalan);
    }

    /** @return array{0: string[], 1: string[]} [peran To, peran CC selain KG & DCI] */
    public static function penerimaPembatalan(PengajuanSewa $p, ?string $peranGiliran): array
    {
        $sudah = array_values(array_diff($p->peranSudahApprove(), [$peranGiliran]));

        return [[$peranGiliran ?? 'WM'], $sudah];
    }

    /**
     * Kejadian email untuk keputusan ini. Pada penolakan, WC/WH hanya dikirimi kalau ada di alur pengajuan.
     *
     * @return array{0: string, 1: string[], 2: string[]} [tipe email, peran To, peran CC]
     */
    public static function penerimaKeputusan(PengajuanSewa $p, string $peranPemutus, ?string $berikutnya): array
    {
        $alur = $p->alurApproval();

        if (strtolower($p->status_pengajuan) === 'rejected') {
            $aturan = AturanEmail::penerima('ditolak');
            $buang = array_diff(['WC', 'WH'], $alur);

            return ['rejected', array_values(array_diff($aturan['to'], $buang)), array_values(array_diff($aturan['cc'], $buang))];
        }

        if ($berikutnya !== null) {
            $kejadian = match (true) {
                $berikutnya === 'WC' => 'menunggu_wc',
                $p->tujuan_penyewaan === 'PAC' => 'menunggu_wh_pac',
                default => 'menunggu_wh_toko',
            };
            $aturan = AturanEmail::penerima($kejadian);

            return ['menunggu_approval', $aturan['to'], $aturan['cc']];
        }

        $aturan = AturanEmail::penerima($alur === ['WM'] ? 'final_wm' : 'final_tahap2');

        return ['approved', $aturan['to'], $aturan['cc']];
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

    /** Email untuk daftar peran: KG = pengaju saja; WM/KA = cabang pengajuan; WH, WC, DCI lihat PenerimaEmail. */
    private function emailPeran(array $peran, PengajuanSewa $p): array
    {
        $emails = in_array('KG', $peran, true) ? $this->penerima->user($p->submitted_by) : [];

        return array_values(array_unique(array_merge(
            $emails,
            $this->penerima->peran(array_values(array_diff($peran, ['KG'])), $p->id_cabang),
        )));
    }
}

<?php

namespace App\Services;

use App\Models\DetailKirimanRutin;
use App\Models\PengajuanSewa;
use App\Models\PerusahaanSkill;
use App\Models\RiwayatHargaSewaTruk;
use App\Models\RiwayatTarifKirimanRutin;
use App\Models\TarifKirimanRutin;

/**
 * Harga master pembanding untuk harga di pengajuan (penanda naik/turun di Rincian Biaya).
 * Kalau usulan perubahan master pengajuan ini sudah disetujui, master sekarang = harga
 * pengajuan, jadi pembandingnya diambil dari riwayat (harga sebelum diubah).
 * null = barang/area belum punya harga master.
 */
class HargaMasterPengajuanService
{
    /** @return array{sewa: ?float, detail: array<int, ?float>} */
    public function ambil(PengajuanSewa $pengajuan): array
    {
        if ($pengajuan->jenis_pengajuan === 'pengiriman_rutin') {
            return [
                'sewa' => null,
                'detail' => $pengajuan->detailKirimanRutin
                    ->mapWithKeys(fn ($d) => [$d->id_detail_kiriman => $this->tarifKirimanRutin($d)])
                    ->all(),
            ];
        }

        return ['sewa' => $this->hargaSewaTruk($pengajuan), 'detail' => []];
    }

    /**
     * Vendor + area + cabang master Sewa Truk pengajuan ini. `id_skill` berupa CSV beberapa
     * area; dianggap satu tarif, diambil area pertama. null kalau data tidak lengkap.
     */
    public function konteksSewaTruk(PengajuanSewa $pengajuan): ?array
    {
        $idPerusahaan = $pengajuan->kendaraan?->id_perusahaan;
        $idSkill = collect(explode(',', (string) $pengajuan->id_skill))
            ->map(fn ($s) => (int) trim($s))
            ->filter()
            ->first();

        if (! $idPerusahaan || ! $idSkill) {
            return null;
        }

        return ['id_perusahaan' => $idPerusahaan, 'id_skill' => $idSkill, 'cabang_code' => $pengajuan->id_cabang];
    }

    private function hargaSewaTruk(PengajuanSewa $pengajuan): ?float
    {
        $ctx = $this->konteksSewaTruk($pengajuan);
        $vendorSkill = $ctx ? PerusahaanSkill::withInactive()->where($ctx)->first() : null;
        if (! $vendorSkill) {
            return null;
        }

        if ($pengajuan->usulan_harga_sewa && $pengajuan->usulan_status === 'approved') {
            $lama = RiwayatHargaSewaTruk::withInactive()
                ->where('id_vendor_skill', $vendorSkill->id_vendor_skill)
                ->where('harga_baru', $pengajuan->harga_sewa)
                ->orderByDesc('tanggal_perubahan')
                ->value('harga_lama');

            return $lama !== null ? (float) $lama : null;
        }

        return $vendorSkill->harga_sewa !== null ? (float) $vendorSkill->harga_sewa : null;
    }

    private function tarifKirimanRutin(DetailKirimanRutin $detail): ?float
    {
        if (! $detail->id_tarif_kiriman_rutin) {
            return null;
        }

        if ($detail->usulan_update_master && $detail->usulan_status === 'approved') {
            $lama = RiwayatTarifKirimanRutin::withInactive()
                ->where('id_tarif', $detail->id_tarif_kiriman_rutin)
                ->where('biaya_baru', $detail->harga_satuan)
                ->orderByDesc('tanggal_perubahan')
                ->value('biaya_lama');

            return $lama !== null ? (float) $lama : null;
        }

        $tarif = TarifKirimanRutin::withInactive()->find($detail->id_tarif_kiriman_rutin);

        return $tarif?->biaya_per_unit !== null ? (float) $tarif->biaya_per_unit : null;
    }
}

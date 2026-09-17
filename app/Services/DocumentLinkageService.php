<?php

namespace App\Services;

use App\Models\PengajuanSewa;
use App\Models\PengajuanSewaSuratJalan;
use App\Models\PengajuanSewaToAcb;
use Illuminate\Support\Facades\Log;

/**
 * DocumentLinkageService
 *
 * Handles linking pengajuan sewa to external documents:
 * - Surat Jalan (from Quantum API)
 * - Transfer Antar Cabang (from ERP API)
 *
 * Uses reference-only approach (no FKs to external databases)
 * Validation done at application layer
 */
class DocumentLinkageService
{
    /**
     * Link Surat Jalan to Pengajuan Sewa
     *
     * @param int $idPengajuanSewa
     * @param string $idSuratJalan (Reference to Quantum tbSJ.No_)
     * @param bool $flag
     * @return PengajuanSewaSuratJalan
     */
    public function linkSuratJalan(int $idPengajuanSewa, string $idSuratJalan, bool $flag = true): PengajuanSewaSuratJalan
    {
        // Validate pengajuan exists
        $pengajuan = PengajuanSewa::findOrFail($idPengajuanSewa);

        // TODO: Validate idSuratJalan exists in Quantum API
        // $this->validateSuratJalanExists($idSuratJalan);

        // withInactive(): baris yang pernah di-unlink (flag=0) harus di-UPDATE
        // (reactivate), bukan di-INSERT ulang — kena unique (id_pengajuan_sewa, id_surat_jalan).
        $link = PengajuanSewaSuratJalan::withInactive()->updateOrCreate(
            [
                'id_pengajuan_sewa' => $idPengajuanSewa,
                'id_surat_jalan' => $idSuratJalan,
            ],
            [
                'flag' => $flag,
            ]
        );

        Log::info('Surat Jalan linked to pengajuan', [
            'pengajuan_id' => $idPengajuanSewa,
            'surat_jalan_id' => $idSuratJalan,
            'flag' => $flag,
        ]);

        return $link;
    }

    /**
     * Unlink Surat Jalan from Pengajuan Sewa (soft delete via flag)
     *
     * @param int $idPengajuanSewa
     * @param string $idSuratJalan
     * @return bool
     */
    public function unlinkSuratJalan(int $idPengajuanSewa, string $idSuratJalan): bool
    {
        $link = PengajuanSewaSuratJalan::withInactive()
            ->where('id_pengajuan_sewa', $idPengajuanSewa)
            ->where('id_surat_jalan', $idSuratJalan)
            ->first();

        if (!$link) {
            Log::warning('Surat Jalan link not found', [
                'pengajuan_id' => $idPengajuanSewa,
                'surat_jalan_id' => $idSuratJalan,
            ]);
            return false;
        }

        $link->update(['flag' => false]);

        Log::info('Surat Jalan unlinked from pengajuan', [
            'pengajuan_id' => $idPengajuanSewa,
            'surat_jalan_id' => $idSuratJalan,
        ]);

        return true;
    }

    /**
     * Get all active Surat Jalan for a pengajuan
     *
     * @param int $idPengajuanSewa
     * @return array
     */
    public function getSuratJalansForPengajuan(int $idPengajuanSewa): array
    {
        return PengajuanSewaSuratJalan::where('id_pengajuan_sewa', $idPengajuanSewa)
            ->aktif()
            ->pluck('id_surat_jalan')
            ->toArray();
    }

    /**
     * Link Transfer Antar Cabang to Pengajuan Sewa
     *
     * @param int $idPengajuanSewa
     * @param string $idToAcb (Reference to ERP tbTransferAntarCabang.No_)
     * @param bool $flag
     * @return PengajuanSewaToAcb
     */
    public function linkTransferAntarCabang(int $idPengajuanSewa, string $idToAcb, bool $flag = true): PengajuanSewaToAcb
    {
        // Validate pengajuan exists
        $pengajuan = PengajuanSewa::findOrFail($idPengajuanSewa);

        // TODO: Validate idToAcb exists in ERP API
        // $this->validateTransferAntarCabangExists($idToAcb);

        // withInactive(): lihat catatan di linkSuratJalan().
        $link = PengajuanSewaToAcb::withInactive()->updateOrCreate(
            [
                'id_pengajuan_sewa' => $idPengajuanSewa,
                'id_to_acb' => $idToAcb,
            ],
            [
                'flag' => $flag,
            ]
        );

        Log::info('Transfer Antar Cabang linked to pengajuan', [
            'pengajuan_id' => $idPengajuanSewa,
            'to_acb_id' => $idToAcb,
            'flag' => $flag,
        ]);

        return $link;
    }

    /**
     * Unlink Transfer Antar Cabang from Pengajuan Sewa (soft delete via flag)
     *
     * @param int $idPengajuanSewa
     * @param string $idToAcb
     * @return bool
     */
    public function unlinkTransferAntarCabang(int $idPengajuanSewa, string $idToAcb): bool
    {
        $link = PengajuanSewaToAcb::withInactive()
            ->where('id_pengajuan_sewa', $idPengajuanSewa)
            ->where('id_to_acb', $idToAcb)
            ->first();

        if (!$link) {
            Log::warning('Transfer Antar Cabang link not found', [
                'pengajuan_id' => $idPengajuanSewa,
                'to_acb_id' => $idToAcb,
            ]);
            return false;
        }

        $link->update(['flag' => false]);

        Log::info('Transfer Antar Cabang unlinked from pengajuan', [
            'pengajuan_id' => $idPengajuanSewa,
            'to_acb_id' => $idToAcb,
        ]);

        return true;
    }

    /**
     * Get all active Transfer Antar Cabang for a pengajuan
     *
     * @param int $idPengajuanSewa
     * @return array
     */
    public function getTransferAntarCabangForPengajuan(int $idPengajuanSewa): array
    {
        return PengajuanSewaToAcb::where('id_pengajuan_sewa', $idPengajuanSewa)
            ->aktif()
            ->pluck('id_to_acb')
            ->toArray();
    }

    /**
     * TODO: Validate Surat Jalan exists in Quantum API
     * This method will call the Quantum API to verify the SJ exists
     */
    protected function validateSuratJalanExists(string $idSuratJalan): bool
    {
        // Implementation pending: integrate with Quantum API
        return true;
    }

    /**
     * TODO: Validate Transfer Antar Cabang exists in ERP API
     * This method will call the ERP API to verify the TO-ACB exists
     */
    protected function validateTransferAntarCabangExists(string $idToAcb): bool
    {
        // Implementation pending: integrate with ERP API
        return true;
    }
}

<?php

namespace App\Services;

use App\Models\PengajuanSewa;
use App\Models\PengajuanSewaSuratJalan;
use App\Models\PengajuanSewaToAcb;
use Illuminate\Support\Facades\DB;
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
     * @param  string  $idSuratJalan  (Reference to Quantum tbSJ.No_)
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
     */
    public function unlinkSuratJalan(int $idPengajuanSewa, string $idSuratJalan): bool
    {
        $link = PengajuanSewaSuratJalan::withInactive()
            ->where('id_pengajuan_sewa', $idPengajuanSewa)
            ->where('id_surat_jalan', $idSuratJalan)
            ->first();

        if (! $link) {
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
     * @param  string  $idToAcb  (Reference to ERP tbTransferAntarCabang.No_)
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
     */
    public function unlinkTransferAntarCabang(int $idPengajuanSewa, string $idToAcb): bool
    {
        $link = PengajuanSewaToAcb::withInactive()
            ->where('id_pengajuan_sewa', $idPengajuanSewa)
            ->where('id_to_acb', $idToAcb)
            ->first();

        if (! $link) {
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
     */
    public function getTransferAntarCabangForPengajuan(int $idPengajuanSewa): array
    {
        return PengajuanSewaToAcb::where('id_pengajuan_sewa', $idPengajuanSewa)
            ->aktif()
            ->pluck('id_to_acb')
            ->toArray();
    }

    /**
     * Detail SJ / TO-ACB yang ter-link ke pengajuan (lookup ke view Quantum), plus agregat
     * untuk email notifikasi. Kolom sama dengan PengajuanController::getDokumenList.
     * Dokumen yang tidak ditemukan lagi di sumber (mis. SJ sudah keluar dari "Belum Kirim")
     * tetap muncul dengan detail null, biar nomornya nggak hilang.
     *
     * @return array{tipe:string, items:array, jumlah_dokumen:int, jumlah_toko:int, total_berat_kg:float, total_nilai:float}
     */
    public function getDetailDokumen(PengajuanSewa $pengajuan): array
    {
        $nomorSj = $this->getSuratJalansForPengajuan($pengajuan->id_pengajuan_sewa);
        $nomorAcb = $this->getTransferAntarCabangForPengajuan($pengajuan->id_pengajuan_sewa);

        $items = [];

        if ($nomorSj) {
            $rows = DB::connection('sqlsrv')->table('Surat Jalan Belum Kirim')
                ->whereIn('No_', $nomorSj)
                ->select(
                    DB::raw('No_ as nomor'),
                    DB::raw('[Sell-to Customer No_] as customer_no'),
                    DB::raw('[Sell-to Customer Name] as customer'),
                    DB::raw('[Ship-to Address] as alamat'),
                    DB::raw('[Sell-to City] as kota'),
                    DB::raw('ISNULL(GW, 0) as berat'),
                    DB::raw('ISNULL(Amount, 0) as nilai')
                )
                ->get()->keyBy('nomor');

            foreach ($nomorSj as $no) {
                $r = $rows->get($no);
                $items[] = [
                    'nomor' => $no,
                    'customer_no' => $r->customer_no ?? null,
                    'customer' => $r->customer ?? null,
                    'alamat' => $r->alamat ?? null,
                    'kota' => $r->kota ?? null,
                    'berat' => (float) ($r->berat ?? 0),
                    'nilai' => (float) ($r->nilai ?? 0),
                ];
            }
        }

        if ($nomorAcb) {
            $rows = DB::connection('sqlsrv')->table('Transfer Antar Cabang')
                ->whereIn('No_', $nomorAcb)
                ->select(
                    DB::raw('No_ as nomor'),
                    DB::raw('[Last Shipment No_] as last_shipment_no'),
                    DB::raw('ISNULL([Gross Weight], 0) as berat'),
                    DB::raw('ISNULL(Amount, 0) as nilai')
                )
                ->get()->keyBy('nomor');

            foreach ($nomorAcb as $no) {
                $r = $rows->get($no);
                $items[] = [
                    'nomor' => $no,
                    'customer_no' => null,
                    'customer' => null,
                    'alamat' => null,
                    'kota' => null,
                    'last_shipment_no' => $r->last_shipment_no ?? null,
                    'berat' => (float) ($r->berat ?? 0),
                    'nilai' => (float) ($r->nilai ?? 0),
                ];
            }
        }

        $collection = collect($items);

        return [
            'tipe' => $nomorAcb && ! $nomorSj ? 'TO-ACB' : 'SJ',
            'items' => $items,
            'jumlah_dokumen' => count($items),
            'jumlah_toko' => $collection->pluck('customer_no')->filter()->unique()->count(),
            'total_berat_kg' => (float) $collection->sum('berat'),
            'total_nilai' => (float) $collection->sum('nilai'),
        ];
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

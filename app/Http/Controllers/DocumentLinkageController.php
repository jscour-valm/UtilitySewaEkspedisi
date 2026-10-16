<?php

namespace App\Http\Controllers;

use App\Models\PengajuanSewa;
use App\Services\DocumentLinkageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * DocumentLinkageController
 *
 * Handles API endpoints for linking/unlinking external documents to pengajuan sewa:
 * - POST /pengajuan/{id}/surat-jalan/link
 * - DELETE /pengajuan/{id}/surat-jalan/{sjId}/unlink
 * - POST /pengajuan/{id}/transfer-antar-cabang/link
 * - DELETE /pengajuan/{id}/transfer-antar-cabang/{toAcbId}/unlink
 * - GET /pengajuan/{id}/documents
 */
class DocumentLinkageController extends Controller
{
    protected DocumentLinkageService $linkageService;

    public function __construct(DocumentLinkageService $linkageService)
    {
        $this->linkageService = $linkageService;
    }

    /**
     * Link Surat Jalan to Pengajuan
     * POST /pengajuan/{id}/surat-jalan/link
     */
    public function linkSuratJalan(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate(['id_surat_jalan' => 'required|string|max:50']);

        return $this->ubahDokumen($id, $validated['id_surat_jalan'], true, 'Surat Jalan');
    }

    /**
     * Unlink Surat Jalan from Pengajuan
     * DELETE /pengajuan/{id}/surat-jalan/{sjId}/unlink
     */
    public function unlinkSuratJalan(int $id, string $sjId): JsonResponse
    {
        return $this->ubahDokumen($id, $sjId, false, 'Surat Jalan');
    }

    /**
     * Link Transfer Antar Cabang to Pengajuan
     * POST /pengajuan/{id}/transfer-antar-cabang/link
     */
    public function linkTransferAntarCabang(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate(['id_to_acb' => 'required|string|max:50']);

        return $this->ubahDokumen($id, $validated['id_to_acb'], true, 'Transfer Antar Cabang');
    }

    /**
     * Unlink Transfer Antar Cabang from Pengajuan
     * DELETE /pengajuan/{id}/transfer-antar-cabang/{toAcbId}/unlink
     */
    public function unlinkTransferAntarCabang(int $id, string $toAcbId): JsonResponse
    {
        return $this->ubahDokumen($id, $toAcbId, false, 'Transfer Antar Cabang');
    }

    /**
     * Tautkan / lepas satu dokumen. Hanya KG pengaju, cabang sendiri, selama pengajuan masih bisa
     * diedit; dokumen dicek seperti saat submit. Value muatan, rasio, dan alur dihitung ulang dari
     * dokumen yang tertaut setelahnya. Penolakan dibalas JSON {success:false, message} untuk popup.
     */
    private function ubahDokumen(int $id, string $nomor, bool $tautkan, string $label): JsonResponse
    {
        $pengajuan = PengajuanSewa::findOrFail($id);
        $kode = $pengajuan->kode;
        if ($pesan = $this->alasanTidakBisaDiubah($pengajuan)) {
            return response()->json(['success' => false, 'message' => $pesan], 403);
        }

        if ($pengajuan->jenis_pengajuan === 'pengiriman_rutin' && $pengajuan->tujuan_penyewaan === 'Toko') {
            return $this->gagal('Kiriman Rutin tujuan Toko tidak memakai dokumen.');
        }

        $tertaut = array_merge(
            $this->linkageService->getSuratJalansForPengajuan($id),
            $this->linkageService->getTransferAntarCabangForPengajuan($id)
        );

        if ($tautkan) {
            $hasil = $this->linkageService->periksaDokumen([$nomor], (string) $pengajuan->id_cabang, (string) $pengajuan->tujuan_penyewaan, $id);
            if ($hasil['masalah']) {
                return $this->gagal("Gagal menautkan $label: ".implode(' ', $hasil['masalah']));
            }
            $sisa = array_values(array_unique([...$tertaut, $nomor]));
        } else {
            if (! in_array($nomor, $tertaut, true)) {
                return $this->gagal("$label $nomor tidak tertaut ke pengajuan $kode.", 404);
            }
            $sisa = array_values(array_diff($tertaut, [$nomor]));
            if (! $sisa) {
                return $this->gagal("$label $nomor adalah dokumen terakhir pengajuan $kode. Minimal 1 dokumen harus tetap terpasang.");
            }
        }

        $hasil = $this->linkageService->periksaDokumen($sisa, (string) $pengajuan->id_cabang, (string) $pengajuan->tujuan_penyewaan, $id);
        if ($hasil['masalah']) {
            return $this->gagal('Dokumen pengajuan ini perlu diperiksa ulang lewat Edit Pengajuan: '.implode(' ', $hasil['masalah']));
        }

        DB::connection('sqlsrv')->transaction(function () use ($pengajuan, $hasil) {
            $this->linkageService->tautkanDokumen($pengajuan, $hasil['items']);
            $this->linkageService->hitungUlangNilai($pengajuan);
        });

        return response()->json([
            'success' => true,
            'message' => "$label ".($tautkan ? 'berhasil ditautkan' : 'berhasil dilepas'),
        ]);
    }

    /** Alasan pengajuan ini tidak boleh diubah dokumennya oleh user sekarang, null kalau boleh. */
    private function alasanTidakBisaDiubah(PengajuanSewa $pengajuan): ?string
    {
        $kode = $pengajuan->kode;
        $user = auth()->user();
        if ((int) $pengajuan->submitted_by !== (int) $user->id || ! $user->canAccessCabang($pengajuan->id_cabang)) {
            return "Dokumen pengajuan $kode hanya bisa diubah oleh Kepala Gudang yang mengajukannya.";
        }
        if ($pengajuan->bisaDieditPengaju()) {
            return null;
        }

        return match (strtolower((string) $pengajuan->status_pengajuan)) {
            'approved' => "Pengajuan $kode sudah disetujui, dokumennya tidak bisa diubah lagi.",
            'rejected' => "Pengajuan $kode sudah ditolak, dokumennya tidak bisa diubah lagi.",
            'cancelled' => "Pengajuan $kode sudah dibatalkan, dokumennya tidak bisa diubah lagi.",
            default => "Pengajuan $kode sudah divalidasi ".($pengajuan->alurApproval()[0] ?? 'approver').', dokumennya tidak bisa diubah lagi.',
        };
    }

    private function gagal(string $pesan, int $status = 422): JsonResponse
    {
        return response()->json(['success' => false, 'message' => $pesan], $status);
    }

    /**
     * Get all linked documents for a pengajuan
     * GET /pengajuan/{id}/documents
     */
    public function getLinkedDocuments(int $id): JsonResponse
    {
        $pengajuan = PengajuanSewa::with([
            'suratJalans:id_pengajuan_sewa_sj,id_pengajuan_sewa,id_surat_jalan,flag',
            'transferAntarCabang:id_pengajuan_sewa_to_acb,id_pengajuan_sewa,id_to_acb,flag',
        ])->findOrFail($id);

        $suratJalans = $this->linkageService->getSuratJalansForPengajuan($id);
        $transferAntarCabang = $this->linkageService->getTransferAntarCabangForPengajuan($id);

        return response()->json([
            'success' => true,
            'data' => [
                'surat_jalans' => $suratJalans,
                'transfer_antar_cabang' => $transferAntarCabang,
                'total_documents' => count($suratJalans) + count($transferAntarCabang),
            ],
        ]);
    }
}

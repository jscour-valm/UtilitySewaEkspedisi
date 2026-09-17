<?php

namespace App\Http\Controllers;

use App\Models\PengajuanSewa;
use App\Services\DocumentLinkageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
        $pengajuan = PengajuanSewa::findOrFail($id);

        $validated = $request->validate([
            'id_surat_jalan' => 'required|string|max:50',
        ]);

        try {
            $link = $this->linkageService->linkSuratJalan(
                $id,
                $validated['id_surat_jalan']
            );

            return response()->json([
                'success' => true,
                'message' => 'Surat Jalan berhasil ditautkan',
                'data' => $link,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menautkan Surat Jalan: ' . $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Unlink Surat Jalan from Pengajuan
     * DELETE /pengajuan/{id}/surat-jalan/{sjId}/unlink
     */
    public function unlinkSuratJalan(int $id, string $sjId): JsonResponse
    {
        $pengajuan = PengajuanSewa::findOrFail($id);

        try {
            $result = $this->linkageService->unlinkSuratJalan($id, $sjId);

            if (!$result) {
                return response()->json([
                    'success' => false,
                    'message' => 'Surat Jalan tidak ditemukan',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Surat Jalan berhasil dilepas',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal melepas Surat Jalan: ' . $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Link Transfer Antar Cabang to Pengajuan
     * POST /pengajuan/{id}/transfer-antar-cabang/link
     */
    public function linkTransferAntarCabang(Request $request, int $id): JsonResponse
    {
        $pengajuan = PengajuanSewa::findOrFail($id);

        $validated = $request->validate([
            'id_to_acb' => 'required|string|max:50',
        ]);

        try {
            $link = $this->linkageService->linkTransferAntarCabang(
                $id,
                $validated['id_to_acb']
            );

            return response()->json([
                'success' => true,
                'message' => 'Transfer Antar Cabang berhasil ditautkan',
                'data' => $link,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menautkan Transfer Antar Cabang: ' . $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Unlink Transfer Antar Cabang from Pengajuan
     * DELETE /pengajuan/{id}/transfer-antar-cabang/{toAcbId}/unlink
     */
    public function unlinkTransferAntarCabang(int $id, string $toAcbId): JsonResponse
    {
        $pengajuan = PengajuanSewa::findOrFail($id);

        try {
            $result = $this->linkageService->unlinkTransferAntarCabang($id, $toAcbId);

            if (!$result) {
                return response()->json([
                    'success' => false,
                    'message' => 'Transfer Antar Cabang tidak ditemukan',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Transfer Antar Cabang berhasil dilepas',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal melepas Transfer Antar Cabang: ' . $e->getMessage(),
            ], 400);
        }
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

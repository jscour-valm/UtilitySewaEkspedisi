<?php

namespace App\Services;

use App\Models\PengajuanSewa;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Snapshot daftar SJ / TO-ACB yang dipilih di pengajuan, untuk ditampilkan di badan email.
 * Disimpan saat pengajuan dibuat / diajukan ulang karena dokumen bisa hilang dari view
 * sumber (mis. SJ keluar dari "Belum Kirim") sebelum email keputusan dikirim.
 */
class SnapshotDokumenService
{
    public function __construct(private DocumentLinkageService $dokumen) {}

    private function path(PengajuanSewa $p): string
    {
        return 'dokumen-pengajuan/pengajuan-'.$p->id_pengajuan_sewa.'.json';
    }

    /**
     * Ambil detail dokumen terbaru dari sumber lalu simpan sebagai snapshot.
     *
     * @return array{tipe:string, items:array, jumlah_dokumen:int, jumlah_toko:int, total_berat_kg:float, total_nilai:float}|null
     */
    public function simpan(PengajuanSewa $p): ?array
    {
        $detail = $this->dokumen->getDetailDokumen($p);
        if ($detail['jumlah_dokumen'] === 0) {
            return null;
        }

        try {
            Storage::disk('local')->put($this->path($p), json_encode($detail));
        } catch (\Throwable $e) {
            Log::warning("Gagal menyimpan snapshot dokumen pengajuan #{$p->id_pengajuan_sewa}: ".$e->getMessage());
        }

        return $detail;
    }

    /** Snapshot tersimpan; kalau belum ada, dihitung langsung dari sumber (tanpa disimpan). */
    public function ambil(PengajuanSewa $p): ?array
    {
        $disk = Storage::disk('local');
        if ($disk->exists($this->path($p))) {
            $snapshot = json_decode($disk->get($this->path($p)), true);
            if (is_array($snapshot) && isset($snapshot['items'])) {
                return $snapshot;
            }
        }

        $detail = $this->dokumen->getDetailDokumen($p);

        return $detail['jumlah_dokumen'] === 0 ? null : $detail;
    }
}

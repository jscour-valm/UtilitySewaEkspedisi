<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/**
 * Helper bersama buat controller yang mengelola data master vendor
 * (perusahaan/kendaraan) — dipindah dari PengajuanController (16 Sept, biar
 * bisa dipakai ulang sama KendaraanController::store()/update() tanpa
 * duplikasi) TANPA mengubah behavior aslinya.
 */
trait ManagesVendorMasterData
{
    /**
     * Resolve nama skill (existing atau baru, dari checkbox/free-text) jadi
     * id_skill numeric — insert ke sesi_master_skill + sesi_cabang_skill
     * kalau belum ada (fitur "daftar skill baru on-the-fly" yang sudah lama
     * ada; 9 Sept: id_skill dibalik jadi numeric, jadi yang disimpan/dipakai
     * sekarang id-nya, bukan nama mentah).
     */
    private function resolveSkillId(string $namaSkill, string $cabangId): ?int
    {
        $namaSkill = strtoupper(trim($namaSkill));
        if ($namaSkill === '') {
            return null;
        }

        $idSkill = DB::connection('sqlsrv')->table('sesi_master_skill')
            ->where('nama_skill', $namaSkill)
            ->value('id_skill');

        if (!$idSkill) {
            $idSkill = DB::connection('sqlsrv')->table('sesi_master_skill')->insertGetId([
                'nama_skill' => $namaSkill,
                'flag'       => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $cabangSkillExists = DB::connection('sqlsrv')->table('sesi_cabang_skill')
            ->where('cabang_code', $cabangId)
            ->where('id_skill', $idSkill)
            ->exists();

        if (!$cabangSkillExists) {
            DB::connection('sqlsrv')->table('sesi_cabang_skill')->insert([
                'cabang_code' => $cabangId,
                'id_skill'    => $idSkill,
                'flag'        => true,
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);
        }

        return $idSkill;
    }

    /**
     * Kebalikan dari resolveSkillId(): ubah string id_skill comma-separated
     * (numeric) jadi nama_skill comma-separated buat ditampilkan (badge dsb).
     * Token yang BUKAN angka (leftover lama sblm konversi 9 Sept yg ga match
     * master manapun, misal "DKAH"/"-") ditampilkan apa adanya.
     */
    private function resolveSkillNames(?string $idSkillCsv): string
    {
        return implode(',', \App\Helpers\FormatHelper::skillNames($idSkillCsv));
    }

    /**
     * Simpan file identitas owner (KTP/NPWP/SIM) ke public/images/ - dipakai
     * storePerusahaan()/updateVendor(). Nama file: {cabang}-{idUser}-{idPerusahaan}-{index}.{ext}
     * (idPerusahaan wajib ada di nama file krn identitas_owner itu company-level, bisa
     * di-upload ulang oleh user beda-beda & 1 user bisa upload ke banyak perusahaan - lihat
     * Batch Fix 14). Return array path relatif ("/images/xxx.jpg") buat disimpan ke kolom
     * identitas_owner (assign MENTAH, jangan json_encode manual - kolom itu di-cast 'array').
     *
     * $startIndex: index awal penomoran file (default 1) — dipakai updateVendor() pas
     * ada foto lama yang DIPERTAHANKAN (identitas_owner_keep), biar file baru nggak
     * numpuk nama sama nomor 1 dgn foto yang di-keep.
     */
    private function storeIdentitasOwnerFiles(array $files, string $idPerusahaan, int $startIndex = 1): array
    {
        File::ensureDirectoryExists(public_path('images'));
        $cabang = auth()->user()->getCabangId() ?? 'XX';
        $userId = auth()->id();
        $paths = [];
        foreach (array_values($files) as $i => $file) {
            $index = $startIndex + $i;
            $ext = strtolower($file->getClientOriginalExtension());
            $filename = "{$cabang}-{$userId}-{$idPerusahaan}-{$index}.{$ext}";
            $file->move(public_path('images'), $filename);
            $paths[] = "/images/{$filename}";
        }
        return $paths;
    }

    /**
     * Hapus file identitas owner lama (format path baru saja - "/images/xxx.ext") sebelum
     * ganti dgn yang baru, biar gak numpuk file yatim. Data lama base64/path-legacy dibiarkan
     * (bukan file kita, gak ada yang perlu dihapus).
     */
    private function deleteIdentitasOwnerFiles(?array $paths): void
    {
        foreach ($paths ?? [] as $p) {
            if (is_string($p) && preg_match('#^/images/[\w.-]+\.(jpe?g|png|webp)$#i', $p)) {
                @unlink(public_path(ltrim($p, '/')));
            }
        }
    }
}

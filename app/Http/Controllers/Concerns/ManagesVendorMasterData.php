<?php

namespace App\Http\Controllers\Concerns;

use App\Helpers\FormatHelper;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

trait ManagesVendorMasterData
{
    /**
     * Cari-atau-buat area (sesi_master_skill, nama huruf besar) dan pastikan terdaftar di cabang
     * (sesi_cabang_skill). Area / pendaftaran cabang yang pernah dihapus (flag 0) diaktifkan lagi.
     */
    private function resolveSkillId(string $namaSkill, string $cabangId): ?int
    {
        $namaSkill = strtoupper(trim($namaSkill));
        if ($namaSkill === '') {
            return null;
        }
        $db = DB::connection('sqlsrv');

        $skill = $db->table('sesi_master_skill')->where('nama_skill', $namaSkill)->first(['id_skill', 'flag']);

        if (! $skill) {
            $idSkill = $db->table('sesi_master_skill')->insertGetId([
                'nama_skill' => $namaSkill,
                'flag' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            $idSkill = (int) $skill->id_skill;
            if (! $skill->flag) {
                $db->table('sesi_master_skill')->where('id_skill', $idSkill)->update(['flag' => true, 'updated_at' => now()]);
            }
        }

        $cabangSkill = $db->table('sesi_cabang_skill')
            ->where('cabang_code', $cabangId)
            ->where('id_skill', $idSkill)
            ->first(['flag']);

        if (! $cabangSkill) {
            $db->table('sesi_cabang_skill')->insert([
                'cabang_code' => $cabangId,
                'id_skill' => $idSkill,
                'flag' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } elseif (! $cabangSkill->flag) {
            $db->table('sesi_cabang_skill')
                ->where('cabang_code', $cabangId)->where('id_skill', $idSkill)
                ->update(['flag' => true, 'updated_at' => now()]);
        }

        return $idSkill;
    }

    private function resolveSkillNames(?string $idSkillCsv): string
    {
        return implode(',', FormatHelper::skillNames($idSkillCsv));
    }

    /**
     * Simpan file identitas owner (KTP/NPWP/SIM) ke public/images/ - dipakai
     * storePerusahaan()/updateVendor(). Nama file: {cabang}-{idUser}-{idPerusahaan}-{index}.{ext}
     * (idPerusahaan wajib ada di nama file krn identitas_owner itu company-level, bisa
     * di-upload ulang oleh user beda-beda & 1 user bisa upload ke banyak perusahaan).
     * Return array path relatif ("/images/xxx.jpg") buat disimpan ke kolom identitas_owner (assign MENTAH, jangan json_encode manual - kolom itu di-cast 'array').
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

    private function deleteIdentitasOwnerFiles(?array $paths): void
    {
        foreach ($paths ?? [] as $p) {
            if (is_string($p) && preg_match('#^/images/[\w.-]+\.(jpe?g|png|webp)$#i', $p)) {
                @unlink(public_path(ltrim($p, '/')));
            }
        }
    }
}

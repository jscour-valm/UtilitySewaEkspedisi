<?php

namespace App\Helpers;

use Illuminate\Support\Facades\DB;

class FormatHelper
{
    /**
     * Ubah string `id_skill` comma-separated (numeric, sesi_unit_kendaraan /
     * sesi_pengajuan_sewa — lihat migration 2026_08_19_033038_create_sesi_master_skill.php) jadi array
     * nama_skill buat ditampilkan (badge dsb). Token yang BUKAN angka
     * (leftover lama sblm konversi 9 Sept yg ga match master manapun) balik
     * apa adanya. Token angka yang match master tapi flag=0 (soft-deleted)
     * DI-DROP total dari hasil — bukan ditampilkan sbg angka mentah atau nama
     * lama (lihat Batch Fix 3: id_skill sampah "1936"/"1937" yg udah
     * di-soft-delete tapi masih nongol sbg angka di kolom Skill/Area).
     */
    public static function skillNames(?string $idSkillCsv): array
    {
        if (empty($idSkillCsv)) {
            return [];
        }

        $tokens = array_values(array_filter(array_map('trim', explode(',', $idSkillCsv)), fn ($t) => $t !== ''));
        $numericIds = array_values(array_filter($tokens, fn ($t) => ctype_digit($t)));

        $master = empty($numericIds) ? collect() : DB::connection('sqlsrv')
            ->table('sesi_master_skill')
            ->whereIn('id_skill', $numericIds)
            ->get(['id_skill', 'nama_skill', 'flag'])
            ->keyBy('id_skill');

        $result = array_map(function ($t) use ($master) {
            if (!ctype_digit($t)) {
                return $t; // token teks bebas, biarkan
            }
            $row = $master->get($t);
            if ($row && $row->flag) {
                return $row->nama_skill; // aktif -> nama
            }
            if ($row && !$row->flag) {
                return null; // soft-deleted -> drop
            }
            return $t; // tidak match master sama sekali -> legacy, biarkan
        }, $tokens);

        return array_values(array_filter($result, fn ($v) => $v !== null));
    }


    /**
     * Format angka desimal, trim trailing zero
     * Contoh: 2.00 -> "2", 2.50 -> "2.5", 2.75 -> "2.75"
     */
    public static function trimDecimal($value, int $maxDecimals = 2): string
    {
        $formatted = number_format((float) $value, $maxDecimals, '.', '');
        if (str_contains($formatted, '.')) {
            $formatted = rtrim(rtrim($formatted, '0'), '.');
        }
        return $formatted;
    }

    /**
     * Format muatan (ton) dengan trim decimal
     */
    public static function ton($value): string
    {
        return self::trimDecimal($value) . ' Ton';
    }

    /**
     * Format Weight jadi kg, dibulatkan 2 desimal & trailing zero di-trim.
     * Contoh: 39.000000 -> "39 kg", 62.45000000000000000000 -> "62.45 kg",
     * 60.00000000000000000000 -> "60 kg"
     */
    public static function kg($value): string
    {
        return self::trimDecimal($value) . ' kg';
    }

    /**
     * Format Rupiah ala Indonesia: pemisah ribuan titik, desimal koma HANYA
     * kalau ada sisa desimal setelah dibulatkan 2 angka di belakang koma
     * (kalau bulat, tanpa koma sama sekali).
     * Contoh: 1114255.000000 -> "Rp 1.114.255", 1359426.250000 -> "Rp 1.359.426,25"
     */
    public static function rupiah($value): string
    {
        $rounded = round((float) $value, 2);
        $decimalPart = round($rounded - floor($rounded), 2);

        $formatted = $decimalPart > 0
            ? number_format($rounded, 2, ',', '.')
            : number_format($rounded, 0, ',', '.');

        return 'Rp ' . $formatted;
    }

    /**
     * Resolve 1 item `identitas_owner` (sesi_perusahaan_ekspedisi) jadi `src`
     * <img> yang valid. Datanya campuran 2 format lama: base64 murni (dari
     * upload biasa lewat storePerusahaan()) atau path relatif lama (dari hasil
     * migrasi ktp_supir/sim_supir yg sebagian isinya path teks kayak
     * "/images/xxx.jpg" — lihat migration 2026_09_08_000002).
     */
    public static function identitasOwnerSrc(string $item): string
    {
        // Hati-hati: base64 JPEG asli juga sering diawali "/9j/" (karakter "/"),
        // jadi cek path pakai pola file path asli ("/folder/nama.ext"), bukan
        // sekadar starts_with("/") — itu false-positive match base64 JPEG.
        if (preg_match('#^/[\w./-]+\.(jpe?g|png|webp)$#i', $item) || str_starts_with($item, 'http')) {
            return asset($item);
        }

        $mime = match (true) {
            str_starts_with($item, 'iVBORw0KGgo') => 'image/png',
            str_starts_with($item, 'UklGR') => 'image/webp',
            default => 'image/jpeg',
        };

        return "data:{$mime};base64,{$item}";
    }
}

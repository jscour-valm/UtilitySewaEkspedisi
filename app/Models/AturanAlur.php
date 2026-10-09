<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

/**
 * Alur approval pengajuan sewa per kondisi (jenis × tujuan × rasio terhadap batas), diatur DCI.
 * WM selalu tahap pertama. Kalau tabel belum ada / kondisi belum diisi, dipakai ALUR_BAWAAN.
 */
class AturanAlur extends Model
{
    public const PILIHAN_ALUR = ['WM', 'WM,WC', 'WM,WH', 'WM,WC,WH'];

    /** "jenis|tujuan|rasio" => alur. rasio: bawah | atas | - (tanpa rasio). */
    public const ALUR_BAWAAN = [
        'sewa_truk|Toko|bawah' => 'WM',
        'sewa_truk|Toko|atas' => 'WM,WH',
        'sewa_truk|PAC|bawah' => 'WM,WC',
        'sewa_truk|PAC|atas' => 'WM,WH',
        'pengiriman_rutin|Toko|-' => 'WM',
        'pengiriman_rutin|PAC|bawah' => 'WM,WC',
        'pengiriman_rutin|PAC|atas' => 'WM,WH',
    ];

    protected $connection = 'sqlsrv';

    protected $table = 'sesi_aturan_alur';

    protected $primaryKey = 'id_aturan_alur';

    protected $fillable = ['jenis_pengajuan', 'tujuan_penyewaan', 'rasio', 'alur', 'updated_by'];

    private static ?array $cache = null;

    public static function kunci(string $jenis, string $tujuan, string $rasio): string
    {
        return "$jenis|$tujuan|$rasio";
    }

    /** Kunci kondisi untuk pengajuan; Kiriman Rutin ke Toko tidak memakai rasio. */
    public static function kunciUntuk(string $jenis, string $tujuan, bool $rasioDiAtas): string
    {
        $tujuan = $tujuan === 'PAC' ? 'PAC' : 'Toko';
        $rasio = PengajuanSewa::pakaiRasioUntuk($jenis, $tujuan) ? ($rasioDiAtas ? 'atas' : 'bawah') : '-';

        return self::kunci($jenis, $tujuan, $rasio);
    }

    /** @return array<string, string> semua kondisi => alur yang berlaku */
    public static function semua(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        $alur = self::ALUR_BAWAAN;
        if (self::tabelAda('sesi_aturan_alur')) {
            foreach (static::all() as $a) {
                $kunci = self::kunci($a->jenis_pengajuan, $a->tujuan_penyewaan, $a->rasio);
                if (isset($alur[$kunci]) && in_array($a->alur, self::PILIHAN_ALUR, true)) {
                    $alur[$kunci] = $a->alur;
                }
            }
        }

        return self::$cache = $alur;
    }

    public static function alurUntuk(string $jenis, string $tujuan, bool $rasioDiAtas): string
    {
        return self::semua()[self::kunciUntuk($jenis, $tujuan, $rasioDiAtas)] ?? 'WM';
    }

    /** Test unit memakai aturan bawaan (tanpa DB). */
    private static function tabelAda(string $tabel): bool
    {
        if (app()->runningUnitTests()) {
            return false;
        }

        try {
            return Schema::connection('sqlsrv')->hasTable($tabel);
        } catch (\Throwable) {
            return false;
        }
    }

    public static function lupakanCache(): void
    {
        self::$cache = null;
    }
}

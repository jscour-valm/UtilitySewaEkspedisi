<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

/**
 * Alur approval yang diatur DCI: urutan 1–3 peran (WM / WC / WH) per kondisi.
 * - Pengajuan sewa: per jenis × tujuan × rasio terhadap batas.
 * - Proses master (vendor baru, usulan harga): satu alur per proses, disimpan dengan
 *   tujuan & rasio "-".
 * Kalau tabel belum ada / kondisi belum diisi / isinya tidak sah, dipakai alur bawaan.
 */
class AturanAlur extends Model
{
    /** Peran yang boleh mengisi tahap alur. */
    public const PERAN = ['WM', 'WC', 'WH'];

    public const TAHAP_MAKS = 3;

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

    public const MASTER_VENDOR = 'vendor_baru';

    public const MASTER_HARGA = 'usulan_harga';

    /** Proses master => alur bawaan. */
    public const ALUR_MASTER_BAWAAN = [
        self::MASTER_VENDOR => 'WM,WH',
        self::MASTER_HARGA => 'WM,WH',
    ];

    public const LABEL_MASTER = [
        self::MASTER_VENDOR => 'Vendor Baru',
        self::MASTER_HARGA => 'Perubahan Harga Master',
    ];

    protected $connection = 'sqlsrv';

    protected $table = 'sesi_aturan_alur';

    protected $primaryKey = 'id_aturan_alur';

    protected $fillable = ['jenis_pengajuan', 'tujuan_penyewaan', 'rasio', 'alur', 'updated_by'];

    /** @var array{pengajuan: array<string, string>, master: array<string, string>}|null */
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

    /**
     * Alur dari isian per tahap atau teks "WM,WH": tahap kosong dibuang (dirapatkan).
     * Null kalau tidak sah — kosong, ada peran di luar PERAN, peran berulang, atau lebih dari TAHAP_MAKS.
     *
     * @param  string|array<int, ?string>|null  $alur
     */
    public static function rapikan(string|array|null $alur): ?string
    {
        $tahap = is_array($alur) ? $alur : explode(',', (string) $alur);
        $tahap = array_values(array_filter(array_map(fn ($t) => strtoupper(trim((string) $t)), $tahap), fn ($t) => $t !== ''));

        $sah = $tahap !== []
            && count($tahap) <= self::TAHAP_MAKS
            && array_diff($tahap, self::PERAN) === []
            && count($tahap) === count(array_unique($tahap));

        return $sah ? implode(',', $tahap) : null;
    }

    /** "Menunggu Validasi WM" / "Menunggu Approval WC" (WM memvalidasi, peran lain meng-approve). */
    public static function labelMenunggu(string $peran): string
    {
        return $peran === 'WM' ? 'Menunggu Validasi WM' : "Menunggu Approval {$peran}";
    }

    /** "Divalidasi WM" / "Disetujui WH". */
    public static function labelSelesai(string $peran): string
    {
        return ($peran === 'WM' ? 'Divalidasi ' : 'Disetujui ').$peran;
    }

    /** @return array<string, string> kondisi pengajuan sewa => alur yang berlaku */
    public static function semua(): array
    {
        return self::muat()['pengajuan'];
    }

    /** @return array<string, string> proses master => alur yang berlaku */
    public static function semuaMaster(): array
    {
        return self::muat()['master'];
    }

    public static function alurUntuk(string $jenis, string $tujuan, bool $rasioDiAtas): string
    {
        return self::semua()[self::kunciUntuk($jenis, $tujuan, $rasioDiAtas)] ?? 'WM';
    }

    /** Alur proses master (MASTER_VENDOR / MASTER_HARGA) untuk pengajuan berikutnya. */
    public static function alurMaster(string $proses): string
    {
        return self::semuaMaster()[$proses] ?? 'WM,WH';
    }

    private static function muat(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        $pengajuan = self::ALUR_BAWAAN;
        $master = self::ALUR_MASTER_BAWAAN;

        if (self::tabelAda('sesi_aturan_alur')) {
            foreach (static::all() as $a) {
                $alur = self::rapikan($a->alur);
                if ($alur === null) {
                    continue;
                }
                $kunci = self::kunci($a->jenis_pengajuan, $a->tujuan_penyewaan, $a->rasio);
                if (isset($pengajuan[$kunci])) {
                    $pengajuan[$kunci] = $alur;
                } elseif (isset($master[$a->jenis_pengajuan])) {
                    $master[$a->jenis_pengajuan] = $alur;
                }
            }
        }

        return self::$cache = ['pengajuan' => $pengajuan, 'master' => $master];
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

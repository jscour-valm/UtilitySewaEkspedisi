<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

/**
 * Penerima email per kejadian, diatur DCI: satu baris = (kejadian, role, To|CC).
 * Role KG = pengaju saja; WM/KA = cabang terkait; WC, WH, DCI = sesuai PenerimaEmail.
 * Kalau tabel belum ada / kosong, dipakai KEJADIAN[...]['to'|'cc'] bawaan.
 */
class AturanEmail extends Model
{
    public const ROLE = ['KG', 'WM', 'WC', 'WH', 'KA', 'DCI'];

    public const KEJADIAN = [
        'pengajuan_masuk' => ['grup' => 'Pengajuan sewa', 'label' => 'Pengajuan masuk / diajukan ulang', 'to' => ['WM'], 'cc' => ['KG', 'DCI']],
        'menunggu_wc' => ['grup' => 'Pengajuan sewa', 'label' => 'Divalidasi WM, menunggu approval WC', 'to' => ['KG'], 'cc' => ['WM', 'WC', 'DCI']],
        'menunggu_wh_toko' => ['grup' => 'Pengajuan sewa', 'label' => 'Menunggu approval WH (tujuan Toko)', 'to' => ['KG'], 'cc' => ['WM', 'WH', 'DCI']],
        'menunggu_wh_pac' => ['grup' => 'Pengajuan sewa', 'label' => 'Menunggu approval WH (tujuan PAC)', 'to' => ['KG'], 'cc' => ['WM', 'WC', 'WH', 'DCI']],
        'final_wm' => ['grup' => 'Pengajuan sewa', 'label' => 'Disetujui final oleh WM (alur hanya WM)', 'to' => ['KG'], 'cc' => ['WM', 'WC', 'KA', 'DCI']],
        'final_tahap2' => ['grup' => 'Pengajuan sewa', 'label' => 'Disetujui final setelah approval WC/WH', 'to' => ['KG'], 'cc' => ['WM', 'WC', 'WH', 'KA', 'DCI']],
        'ditolak' => ['grup' => 'Pengajuan sewa', 'label' => 'Ditolak (WC/WH hanya kalau ada di alur pengajuan)', 'to' => ['KG'], 'cc' => ['WM', 'WC', 'WH', 'DCI']],
        'master_diajukan' => ['grup' => 'Vendor baru & usulan harga', 'label' => 'Diajukan / diajukan ulang', 'to' => ['WM'], 'cc' => ['KG', 'DCI']],
        'master_divalidasi' => ['grup' => 'Vendor baru & usulan harga', 'label' => 'Divalidasi WM, menunggu approval WH', 'to' => ['WH'], 'cc' => ['KG', 'WM', 'DCI']],
        'master_disetujui' => ['grup' => 'Vendor baru & usulan harga', 'label' => 'Disetujui WH', 'to' => ['KG'], 'cc' => ['WM', 'WH', 'WC', 'DCI']],
        'master_ditolak_wm' => ['grup' => 'Vendor baru & usulan harga', 'label' => 'Ditolak WM', 'to' => ['KG'], 'cc' => ['WM', 'DCI']],
        'master_ditolak_wh' => ['grup' => 'Vendor baru & usulan harga', 'label' => 'Ditolak WH', 'to' => ['KG'], 'cc' => ['WM', 'WH', 'DCI']],
    ];

    protected $connection = 'sqlsrv';

    protected $table = 'sesi_aturan_email';

    protected $primaryKey = 'id_aturan_email';

    protected $fillable = ['kejadian', 'role', 'jenis', 'updated_by'];

    private static ?array $cache = null;

    /** @return array<string, array{to: string[], cc: string[]}> */
    public static function semua(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        $hasil = array_map(fn ($k) => ['to' => $k['to'], 'cc' => $k['cc']], self::KEJADIAN);

        if (self::tabelAda('sesi_aturan_email')) {
            $baris = static::all();
            if ($baris->isNotEmpty()) {
                $hasil = array_map(fn () => ['to' => [], 'cc' => []], self::KEJADIAN);
                foreach ($baris as $b) {
                    if (isset($hasil[$b->kejadian]) && in_array($b->role, self::ROLE, true) && in_array($b->jenis, ['to', 'cc'], true)) {
                        $hasil[$b->kejadian][$b->jenis][] = $b->role;
                    }
                }
            }
        }

        return self::$cache = $hasil;
    }

    /** @return array{to: string[], cc: string[]} role penerima kejadian */
    public static function penerima(string $kejadian): array
    {
        return self::semua()[$kejadian] ?? ['to' => [], 'cc' => []];
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

<?php

namespace App\Console\Commands;

use App\Models\PerusahaanEkspedisi;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

/**
 * Import data asli perusahaan ekspedisi (vendor) dari file CSV.
 *
 * Data restricted — command ini cuma alat baca file, isi data asli
 * sepenuhnya di tangan yang menjalankan command (tidak pernah dibaca
 * atau disimpan oleh siapa pun selain yang punya akses ke file & DB).
 *
 * Format CSV (header wajib persis ini, dipisah koma):
 *   nama_perusahaan,badan_usaha,no_telepon,alamat_kantor
 *
 * - badan_usaha harus persis salah satu: PT, CV, UD, Perseorangan
 * - identitas_owner SENGAJA tidak diisi lewat import ini — diupload
 *   manual lewat form aplikasi belakangan.
 *
 * Idempotent: dijalankan ulang dengan file yang sama tidak akan bikin
 * duplikat, cuma update baris yang nama_perusahaan-nya sudah ada.
 *
 * Contoh:
 *   php artisan import:perusahaan-ekspedisi storage/app/import-data/perusahaan_ekspedisi.csv
 *   php artisan import:perusahaan-ekspedisi storage/app/import-data/perusahaan_ekspedisi.csv --dry-run
 */
class ImportPerusahaanEkspedisiCommand extends Command {
    protected $signature = 'import:perusahaan-ekspedisi
                            {path : Path ke file CSV}
                            {--dry-run : Validasi & laporkan tanpa menulis ke database}';

    protected $description = 'Import data perusahaan ekspedisi (vendor) dari file CSV ke sesi_perusahaan_ekspedisi';

    private const REQUIRED_HEADERS = ['nama_perusahaan', 'badan_usaha', 'no_telepon', 'alamat_kantor'];
    private const BADAN_USAHA_VALID = ['PT', 'CV', 'UD', 'Perseorangan'];

    public function handle(): int {
        $path = $this->argument('path');
        $dryRun = (bool) $this->option('dry-run');

        if (!is_readable($path)) {
            $this->error("File tidak ditemukan atau tidak bisa dibaca: {$path}");
            return self::FAILURE;
        }

        $handle = fopen($path, 'r');
        if ($handle === false) {
            $this->error("Gagal membuka file: {$path}");
            return self::FAILURE;
        }

        $header = $this->readHeader($handle);
        if ($header === null) {
            $this->error('File CSV kosong atau tidak punya baris header.');
            fclose($handle);
            return self::FAILURE;
        }

        $missing = array_diff(self::REQUIRED_HEADERS, $header);
        if (!empty($missing)) {
            $this->error('Header CSV tidak lengkap. Kolom yang wajib ada: ' . implode(', ', self::REQUIRED_HEADERS));
            $this->error('Kolom yang hilang: ' . implode(', ', $missing));
            fclose($handle);
            return self::FAILURE;
        }

        if ($dryRun) {
            $this->warn('MODE DRY-RUN — tidak ada perubahan yang ditulis ke database.');
        }

        $created = 0;
        $updated = 0;
        $errors = [];
        $rowNumber = 1; // baris 1 = header

        while (($row = fgetcsv($handle)) !== false) {
            $rowNumber++;

            if (count($row) === 1 && trim((string) $row[0]) === '') {
                continue; // baris kosong, skip
            }

            if (count($row) !== count($header)) {
                $errors[] = "Baris {$rowNumber}: jumlah kolom (" . count($row) . ") tidak sama dengan header (" . count($header) . ").";
                continue;
            }

            $record = array_combine($header, array_map(fn ($v) => trim((string) $v), $row));

            $validator = Validator::make($record, [
                'nama_perusahaan' => ['required', 'string', 'max:255'],
                'badan_usaha' => ['required', 'string', 'in:' . implode(',', self::BADAN_USAHA_VALID)],
                'no_telepon' => ['required', 'string', 'max:20'],
                'alamat_kantor' => ['required', 'string'],
            ], [
                'badan_usaha.in' => 'badan_usaha harus salah satu dari: ' . implode(', ', self::BADAN_USAHA_VALID) . " (bukan 'Perorangan' — sudah di-rename jadi 'Perseorangan').",
            ]);

            if ($validator->fails()) {
                $errors[] = "Baris {$rowNumber}: " . implode(' ', $validator->errors()->all());
                continue;
            }

            $data = $validator->validated();

            try {
                // withInactive(): import boleh match & reactivate vendor yang pernah di-soft-delete
                if ($dryRun) {
                    $exists = PerusahaanEkspedisi::withInactive()
                        ->where('nama_perusahaan', $data['nama_perusahaan'])
                        ->exists();
                    $exists ? $updated++ : $created++;
                    continue;
                }

                $existed = PerusahaanEkspedisi::withInactive()
                    ->where('nama_perusahaan', $data['nama_perusahaan'])
                    ->exists();

                PerusahaanEkspedisi::withInactive()->updateOrCreate(
                    ['nama_perusahaan' => $data['nama_perusahaan']],
                    [
                        'badan_usaha' => $data['badan_usaha'],
                        'no_telepon' => $data['no_telepon'],
                        'alamat_kantor' => $data['alamat_kantor'],
                        'flag' => true,
                    ]
                );

                $existed ? $updated++ : $created++;
            } catch (\Throwable $e) {
                $errors[] = "Baris {$rowNumber}: gagal menyimpan — " . $e->getMessage();
            }
        }

        fclose($handle);

        $this->newLine();
        if (!empty($errors)) {
            $this->error('Baris bermasalah:');
            foreach ($errors as $err) {
                $this->line("  - {$err}");
            }
            $this->newLine();
        }

        $total = $created + $updated + count($errors);
        $prefix = $dryRun ? '[DRY-RUN] ' : '';
        $this->info("{$prefix}Selesai: {$created} dibuat, {$updated} diperbarui, " . count($errors) . " error, dari {$total} baris data.");

        return empty($errors) ? self::SUCCESS : self::FAILURE;
    }

    /**
     * Baca baris header, strip BOM UTF-8 kalau ada (biasa muncul dari file
     * hasil "Save As CSV" via Excel), trim tiap nama kolom.
     *
     * @return string[]|null
     */
    private function readHeader($handle): ?array {
        $header = fgetcsv($handle);
        if ($header === false) {
            return null;
        }

        $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $header[0]);

        return array_map(fn ($h) => trim((string) $h), $header);
    }
}

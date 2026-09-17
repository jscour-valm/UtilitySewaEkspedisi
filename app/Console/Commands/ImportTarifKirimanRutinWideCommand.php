<?php

namespace App\Console\Commands;

use App\Models\JenisBarangKiriman;
use App\Models\PerusahaanEkspedisi;
use App\Models\PerusahaanSkill;
use App\Models\TarifKirimanRutin;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Import data tarif Kiriman Rutin dari file CSV format ASLI ("wide"): 2
 * baris header (baris pertama nama grup kolom kayak "Biaya Ekspedisi" /
 * "Revisi Biaya Ekspedisi" yang di-merge-cell di Excel makanya sel-sel
 * sesudahnya kosong; baris kedua nama jenis barang per sub-kolom).
 *
 * Beda dari `import:tarif-kiriman-rutin` (command lama, format sederhana
 * nama_perusahaan,nama_barang,biaya_per_unit — sudah dihapus krn stale,
 * skema barunya nggak punya kolom yang dia asumsikan) — command ini
 * mem-"parut" (melt) format wide itu jadi baris per (vendor, cabang, area,
 * jenis barang).
 *
 * Data disimpan sebagai row `sesi_perusahaan_skill` — 1 baris per kombinasi
 * vendor+skill/area+cabang, bukan lagi digabung comma-CSV dalam 1 baris
 * `sesi_unit_kendaraan` (rate-card lama, direfactor 14 Sept — lihat migration
 * `2026_09_03_000006_create_sesi_perusahaan_skill_table.php`). Kalau 1 baris
 * CSV punya beberapa area sekaligus (Area Kirim isinya beberapa nama dipisah
 * koma), masing2 area jadi baris `sesi_perusahaan_skill` sendiri — tarif per
 * jenis barang ditulis ke SEMUA vendor-skill hasil resolve baris itu (bukan
 * digabung jadi 1 rate-card kayak dulu).
 *
 * "Area Kirim" yang ga bisa di-resolve ke skill valid TETAP disimpan (bukan
 * dilewati) — ditandai pakai 1 baris sentinel di sesi_master_skill
 * (nama_skill='-', flag=false, resolve-or-create sekali lewat
 * resolvePlaceholderSkillId()), pola sama persis kayak
 * `ImportTarifSewaTrukCommand.php`.
 *
 * Cara baca header 2-baris: baris 1 (row header grup) di-forward-fill —
 * sel kosong dianggap masih bagian grup terakhir yang keisi (pola umum
 * hasil export Excel dari merged cell). Baris 2 (sub-header) isi tiap
 * kolom di bawah grup "Biaya Ekspedisi" harus cocok (case-insensitive,
 * trimmed) sama salah satu nama di sesi_jenis_barang_kiriman. Kolom di
 * bawah grup manapun SELAIN "Biaya Ekspedisi" (misal "Revisi Biaya
 * Ekspedisi") dilewati & dilaporkan, TIDAK dipakai buat nulis data.
 *
 * Contoh:
 *   php artisan import:tarif-kiriman-rutin-wide storage/app/import-data/tarif_kiriman_rutin_wide.csv --dry-run
 *   php artisan import:tarif-kiriman-rutin-wide storage/app/import-data/tarif_kiriman_rutin_wide.csv
 */
class ImportTarifKirimanRutinWideCommand extends Command
{
    protected $signature = 'import:tarif-kiriman-rutin-wide
                            {path : Path ke file CSV}
                            {--dry-run : Validasi & laporkan tanpa menulis ke database}
                            {--group-column= : Nama grup kolom harga yang dipakai persis (lihat baris header 1). Kalau kosong, otomatis pakai grup non-"revisi" pertama yang ketemu — toleran typo (misal "Biaya Eskpedisi").}';

    protected $description = 'Import tarif Kiriman Rutin dari file CSV format wide (header 2 baris: grup + jenis barang per kolom), ditulis per vendor+skill/area+cabang (sesi_perusahaan_skill)';

    private const BASE_COLUMNS = ['No', 'Kode Area', 'Kode Cabang', 'Nama Cabang', 'Nama Ekspedisi', 'Area Kirim'];
    private const BIAYA_MAX_DIGITS = 10; // sesuai kolom sesi_tarif_kiriman_rutin.biaya_per_unit decimal(12,2) — 10 digit di depan koma
    private const PLACEHOLDER = '-';
    private const AREA_KIRIM_RAW_MAX_LENGTH = 200; // cell "Area Kirim" mentah di atas ini dianggap garbage/campur kalimat, bukan daftar area bersih
    private const NAMA_SKILL_MAX_LENGTH = 100; // sesuai kolom sesi_master_skill.nama_skill (varchar 100)

    public function handle(): int
    {
        $path = $this->argument('path');
        $dryRun = (bool) $this->option('dry-run');
        $groupWanted = trim((string) $this->option('group-column'));

        if (!is_readable($path)) {
            $this->error("File tidak ditemukan atau tidak bisa dibaca: {$path}");
            return self::FAILURE;
        }

        $handle = fopen($path, 'r');
        if ($handle === false) {
            $this->error("Gagal membuka file: {$path}");
            return self::FAILURE;
        }

        // File asli sering punya baris judul/metadata (misal "Data, 7 Sept 2026,
        // ... Insert KTP/NPWP ...") SEBELUM baris header beneran (hasil export
        // Excel dgn judul di atas tabel). Scan beberapa baris pertama buat cari
        // baris grup yang beneran — dicirikan punya "Kode Cabang" & "Nama
        // Ekspedisi" persis. Baris SESUDAHNYA otomatis dianggap baris sub-header.
        $groupRow = null;
        $maxScan = 15;
        for ($i = 0; $i < $maxScan; $i++) {
            $candidate = fgetcsv($handle);
            if ($candidate === false) {
                break;
            }
            $candidate[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $candidate[0]);
            $candidate = array_map(fn ($v) => trim($this->toUtf8((string) $v)), $candidate);
            if (in_array('Kode Cabang', $candidate, true) && in_array('Nama Ekspedisi', $candidate, true)) {
                $groupRow = $candidate;
                break;
            }
        }
        if ($groupRow === null) {
            $this->error('Ga ketemu baris header (yang punya "Kode Cabang" & "Nama Ekspedisi") dalam 15 baris pertama file ini.');
            fclose($handle);
            return self::FAILURE;
        }

        $subRow = fgetcsv($handle); // baris tepat sesudah baris grup: nama jenis barang per sub-kolom
        if ($subRow === false) {
            $this->error('File CSV harus punya baris sub-header (nama jenis barang) tepat sesudah baris header grup.');
            fclose($handle);
            return self::FAILURE;
        }
        // Nama sub-kolom di file asli sering punya newline di tengah (hasil word-wrap
        // sel Excel, misal "Per Koli\n(Cat Pail)") — normalize() di bawah nanti yang
        // ngerapihin whitespace internalnya, di sini cuma convert encoding + trim pinggir.
        $subRow = array_map(fn ($v) => trim($this->toUtf8((string) $v)), $subRow);

        // Forward-fill nama grup ke sel kosong sesudahnya (hasil merged cell Excel)
        $filledGroup = [];
        $lastGroup = '';
        foreach ($groupRow as $i => $g) {
            if ($g !== '') {
                $lastGroup = $g;
            }
            $filledGroup[$i] = $lastGroup;
        }

        // Cari index kolom dasar (No, Kode Cabang, Nama Ekspedisi, Area Kirim, dst)
        // berdasarkan nama di baris grup (biasanya kolom dasar ga di-merge, jadi
        // namanya langsung ada di baris 1).
        $baseIndex = [];
        foreach (self::BASE_COLUMNS as $col) {
            $idx = array_search($col, $groupRow, true);
            if ($idx !== false) {
                $baseIndex[$col] = $idx;
            }
        }
        $missingBase = array_diff(['Kode Cabang', 'Nama Ekspedisi'], array_keys($baseIndex));
        if (!empty($missingBase)) {
            $this->error('Kolom dasar wajib ga ketemu di baris header 1: ' . implode(', ', $missingBase));
            fclose($handle);
            return self::FAILURE;
        }
        $areaIdx = $baseIndex['Area Kirim'] ?? null;

        // Kalau --group-column ga diisi, otomatis pakai grup NON-"revisi" pertama
        // yang ketemu di baris 1 (di luar kolom dasar) — toleran typo kayak "Biaya
        // Eskpedisi" (bukannya hardcode "Biaya Ekspedisi" yang belum tentu match
        // persis sama tulisan asli file).
        if ($groupWanted === '') {
            foreach ($filledGroup as $i => $g) {
                if ($g === '' || in_array($i, $baseIndex, true) || stripos($g, 'revisi') !== false) {
                    continue;
                }
                $groupWanted = $g;
                break;
            }
            if ($groupWanted === '') {
                $this->error('Ga ketemu nama grup kolom harga otomatis. Coba isi manual pakai --group-column="nama grup persis".');
                fclose($handle);
                return self::FAILURE;
            }
            $this->info("Grup kolom harga otomatis terdeteksi: \"{$groupWanted}\" (override pakai --group-column kalau salah).");
        }

        // Master jenis barang, buat matching nama kolom sub-header → id_jenis_barang
        // withInactive(): import boleh match master yang pernah di-soft-delete
        $barangMap = JenisBarangKiriman::withInactive()->pluck('id_jenis_barang', 'nama_barang')
            ->mapWithKeys(fn ($id, $nama) => [$this->normalize($nama) => $id]);

        // Mapping kolom index → id_jenis_barang, HANYA utk kolom yg grup-nya cocok
        // $groupWanted DAN nama sub-headernya match master.
        $priceColumns = []; // idx => id_jenis_barang
        $ignoredGroups = [];
        $autoCreatedBarang = [];
        foreach ($subRow as $i => $subName) {
            if (in_array($i, $baseIndex, true) || $subName === '') {
                continue;
            }
            $group = $filledGroup[$i] ?? '';
            if (strcasecmp($group, $groupWanted) !== 0) {
                $ignoredGroups[$group] = ($ignoredGroups[$group] ?? 0) + 1;
                continue;
            }
            $barangId = $barangMap->get($this->normalize($subName));
            if ($barangId === null) {
                // Nama kolom ga cocok sama master manapun — daripada datanya hilang,
                // daftarkan otomatis sbg jenis barang baru (nama dirapihin dulu:
                // whitespace/newline internal diseragamkan jadi 1 spasi). Ditandai
                // di ringkasan akhir biar Jo bisa cek/rapihin manual kalau perlu.
                $namaBersih = preg_replace('/\s+/u', ' ', $subName);
                if ($dryRun) {
                    // Jangan nulis apapun di dry-run — id palsu cukup buat hitungan laporan.
                    $barangId = 'DRYRUN:' . $namaBersih;
                } else {
                    $barang = JenisBarangKiriman::create(['nama_barang' => $namaBersih, 'flag' => true]);
                    $barangId = $barang->id_jenis_barang;
                }
                $barangMap->put($this->normalize($subName), $barangId);
                $autoCreatedBarang[] = $namaBersih;
            }
            $priceColumns[$i] = $barangId;
        }

        if (empty($priceColumns)) {
            $this->error("Ga ada kolom harga yang ke-mapping di bawah grup '{$groupWanted}'. Cek nama grup di --group-column atau isi baris header 2.");
            fclose($handle);
            return self::FAILURE;
        }

        $this->info('Kolom harga yang dipakai (' . count($priceColumns) . '): ' . implode(', ', array_map(
            fn ($idx) => preg_replace('/\s+/u', ' ', $subRow[$idx]),
            array_keys($priceColumns)
        )));
        if (!empty($ignoredGroups)) {
            foreach ($ignoredGroups as $group => $count) {
                if ($group === '') continue;
                $this->warn("Grup kolom '{$group}' ({$count} kolom) DIABAIKAN — ga ditulis ke database.");
            }
        }
        if (!empty($autoCreatedBarang)) {
            $this->warn('Jenis barang BARU didaftarkan otomatis ke master (nama kolom ga cocok ke yang sudah ada) — cek/rapihin manual lewat halaman Kelola Tarif kalau perlu: ' . implode(', ', array_unique($autoCreatedBarang)));
        }
        if ($areaIdx === null) {
            $this->warn('Kolom "Area Kirim" ga ketemu — semua tarif akan ditulis dgn skill placeholder "-" (area belum diketahui).');
        }

        if ($dryRun) {
            $this->warn('MODE DRY-RUN — tidak ada perubahan yang ditulis ke database.');
        }

        // withInactive(): import boleh match master yang pernah di-soft-delete
        $vendorMap = PerusahaanEkspedisi::withInactive()->pluck('id_perusahaan', 'nama_perusahaan')
            ->mapWithKeys(fn ($id, $nama) => [$this->normalize($nama) => $id]);
        $autoCreatedVendor = [];
        $skillCache = collect(); // nama_skill (uppercase) => id_skill
        $autoCreatedSkill = [];
        $vendorSkillCache = []; // "vendorId|idCabang|skillId" => id_vendor_skill (real atau "DRYRUN:...")
        $vendorSkillBaru = 0;
        $areaTidakValidCount = 0;

        $created = 0;
        $updated = 0;
        $errors = [];
        $rowNumber = 2; // baris 1&2 sudah dipakai sbg header

        while (($row = fgetcsv($handle)) !== false) {
            $rowNumber++;
            if (count($row) === 1 && trim((string) $row[0]) === '') {
                continue;
            }

            // Cell "Nama Ekspedisi" kadang ke-wrap 2 baris di Excel (nama vendor
            // di baris 1, no. telp/nama kontak dlm kurung di baris 2) — collapse
            // whitespace run (termasuk newline & NBSP) jadi 1 spasi biar rapi &
            // match-nya konsisten sama command Sewa Truk (hindari duplikat vendor).
            $namaVendor = preg_replace('/\s+/u', ' ', trim($this->toUtf8((string) ($row[$baseIndex['Nama Ekspedisi']] ?? ''))));
            if ($namaVendor === '') {
                continue; // baris kosong/separator
            }
            $vendorId = $vendorMap->get($this->normalize($namaVendor));
            if ($vendorId === null) {
                // Vendor ga ketemu (belum pernah diimport lewat file Sewa Truk /
                // import:perusahaan-ekspedisi) — daripada datanya hilang, daftarkan
                // otomatis dgn data placeholder. Ditandai di ringkasan biar Jo bisa
                // lengkapi manual (badan usaha/telepon/alamat) belakangan.
                if ($dryRun) {
                    $vendorId = 'DRYRUN:' . $namaVendor;
                } else {
                    $vendor = PerusahaanEkspedisi::create([
                        'nama_perusahaan' => $namaVendor,
                        // "-" (placeholder, ditambah ke enum lewat migration
                        // 2026_09_09_000001) — CSV ini ga punya kolom Badan Usaha
                        // sama sekali, jadi jangan nebak "PT", biar jelas kelihatan
                        // butuh dilengkapi manual lewat form vendor.
                        'badan_usaha' => '-',
                        'no_telepon' => '-',
                        'alamat_kantor' => '-',
                        'flag' => true,
                    ]);
                    $vendorId = $vendor->id_perusahaan;
                }
                $vendorMap->put($this->normalize($namaVendor), $vendorId);
                $autoCreatedVendor[] = $namaVendor;
            }

            $rawKodeCabang = trim($this->toUtf8((string) ($row[$baseIndex['Kode Cabang']] ?? '')));
            $idCabang = $rawKodeCabang !== '' ? strtoupper($rawKodeCabang) : self::PLACEHOLDER;

            $rawArea = $areaIdx !== null ? trim($this->toUtf8((string) ($row[$areaIdx] ?? ''))) : '';
            $idSkillIds = $rawArea !== '' ? $this->resolveAreaKirimToSkillIds($rawArea, $skillCache, $autoCreatedSkill, $dryRun) : [];
            if ($rawArea !== '' && empty($idSkillIds)) {
                $areaTidakValidCount++;
            }
            if (empty($idSkillIds)) {
                $idSkillIds = [$this->resolvePlaceholderSkillId($skillCache, $dryRun)];
            }

            // Resolve/create sesi_perusahaan_skill (vendor+skill+cabang) buat
            // TIAP skill hasil resolve baris ini — 1 baris CSV bisa hasilin
            // beberapa id_vendor_skill kalau Area Kirim isinya >1 nama.
            // Di-cache per file supaya baris lain dgn kombinasi identik ga
            // bikin baris dobel.
            $idVendorSkillList = [];
            foreach ($idSkillIds as $skillId) {
                $vendorSkillKey = $vendorId . '|' . $idCabang . '|' . $skillId;
                if (!isset($vendorSkillCache[$vendorSkillKey])) {
                    $isNewCombo = str_starts_with((string) $vendorId, 'DRYRUN:') || (is_string($skillId) && str_starts_with($skillId, 'DRYRUN:'));

                    if ($isNewCombo) {
                        // Vendor atau skill-nya sendiri belum ada di DB (dry-run) —
                        // baris vendor-skill pasti baru juga, ga perlu/ga bisa di-query.
                        $idVendorSkill = 'DRYRUN:' . $vendorSkillKey;
                        $vendorSkillBaru++;
                    } elseif ($dryRun) {
                        $exists = DB::connection('sqlsrv')->table('sesi_perusahaan_skill')
                            ->where('id_perusahaan', $vendorId)
                            ->where('id_skill', $skillId)
                            ->where('cabang_code', $idCabang)
                            ->exists();
                        if ($exists) {
                            $idVendorSkill = DB::connection('sqlsrv')->table('sesi_perusahaan_skill')
                                ->where('id_perusahaan', $vendorId)
                                ->where('id_skill', $skillId)
                                ->where('cabang_code', $idCabang)
                                ->value('id_vendor_skill');
                        } else {
                            $idVendorSkill = 'DRYRUN:' . $vendorSkillKey;
                            $vendorSkillBaru++;
                        }
                    } else {
                        $vendorSkill = PerusahaanSkill::withInactive()->firstOrCreate(
                            ['id_perusahaan' => $vendorId, 'id_skill' => $skillId, 'cabang_code' => $idCabang],
                            ['flag' => true]
                        );
                        if ($vendorSkill->wasRecentlyCreated) {
                            $vendorSkillBaru++;
                        }
                        $idVendorSkill = $vendorSkill->id_vendor_skill;
                    }

                    $vendorSkillCache[$vendorSkillKey] = $idVendorSkill;
                }
                $idVendorSkillList[] = $vendorSkillCache[$vendorSkillKey];
            }

            foreach ($priceColumns as $idx => $barangId) {
                $raw = trim($this->toUtf8((string) ($row[$idx] ?? '')));
                if ($raw === '') {
                    continue; // vendor ga nawarin jenis barang ini di area ini, skip
                }
                $digits = preg_replace('/[^0-9]/', '', $raw);
                if ($digits === '') {
                    $errors[] = "Baris {$rowNumber}: nilai '{$raw}' di kolom '{$subRow[$idx]}' bukan angka, dilewati.";
                    continue;
                }
                if (strlen($digits) > self::BIAYA_MAX_DIGITS) {
                    // Sama kayak kasus di ImportTarifSewaTrukCommand: beberapa cell
                    // asli isinya gabungan beberapa angka harga jadi 1 angka raksasa
                    // yang overflow kolom decimal(12,2). Dilewati (bukan error keras)
                    // drpd bikin baris gagal — nilai aslinya tetap ditulis di laporan.
                    $errors[] = "Baris {$rowNumber}: nilai '{$raw}' di kolom '{$subRow[$idx]}' kelihatannya gabungan beberapa angka (kelebihan digit), dilewati.";
                    continue;
                }
                $biaya = (float) $digits;

                foreach ($idVendorSkillList as $idVendorSkill) {
                    // Vendor-skill atau jenis barang dgn id palsu "DRYRUN:..." itu bukti
                    // dia bakal baru dibuat (belum ada di DB sama sekali) — jangan
                    // di-query, langsung pasti "dibuat".
                    $isNewCombo = str_starts_with((string) $idVendorSkill, 'DRYRUN:')
                        || str_starts_with((string) $barangId, 'DRYRUN:');

                    try {
                        // withInactive(): import boleh match & reactivate tarif yang pernah di-soft-delete
                        if ($dryRun) {
                            if ($isNewCombo) {
                                $created++;
                                continue;
                            }
                            $exists = TarifKirimanRutin::withInactive()->where('id_vendor_skill', $idVendorSkill)
                                ->where('id_jenis_barang', $barangId)
                                ->exists();
                            $exists ? $updated++ : $created++;
                            continue;
                        }

                        $existed = TarifKirimanRutin::withInactive()->where('id_vendor_skill', $idVendorSkill)
                            ->where('id_jenis_barang', $barangId)
                            ->exists();

                        TarifKirimanRutin::withInactive()->updateOrCreate(
                            ['id_vendor_skill' => $idVendorSkill, 'id_jenis_barang' => $barangId],
                            ['biaya_per_unit' => $biaya, 'flag' => true]
                        );

                        $existed ? $updated++ : $created++;
                    } catch (\Throwable $e) {
                        $errors[] = "Baris {$rowNumber}, vendor {$namaVendor}: gagal menyimpan tarif — " . $e->getMessage();
                    }
                }
            }
        }
        fclose($handle);

        $this->newLine();
        if (!empty($errors)) {
            $this->error('Baris/data bermasalah:');
            foreach ($errors as $err) {
                $this->line("  - {$err}");
            }
            $this->newLine();
        }

        if ($vendorSkillBaru > 0) {
            $prefixVendorSkill = $dryRun ? 'akan dibuat' : 'dibuat';
            $this->warn("{$vendorSkillBaru} baris vendor+skill/area+cabang baru {$prefixVendorSkill} di sesi_perusahaan_skill.");
        }
        if (!empty($autoCreatedVendor)) {
            $prefixVendor = $dryRun ? 'akan didaftarkan' : 'didaftarkan';
            $this->warn(count(array_unique($autoCreatedVendor)) . " vendor BARU {$prefixVendor} otomatis (belum ada di sesi_perusahaan_ekspedisi sebelumnya) dgn data placeholder (badan_usaha/telepon/alamat=\"-\") — lengkapi manual lewat form vendor: " . implode(', ', array_unique($autoCreatedVendor)));
        }
        if (!empty($autoCreatedSkill)) {
            $prefixSkill = $dryRun ? 'akan didaftarkan' : 'didaftarkan';
            $this->warn(count(array_unique($autoCreatedSkill)) . " skill/area BARU {$prefixSkill} ke sesi_master_skill (nama Area Kirim ga cocok ke master yang ada) — cek/rapihin manual kalau perlu: " . implode(', ', array_unique($autoCreatedSkill)));
        }
        if ($areaTidakValidCount > 0) {
            $this->warn("{$areaTidakValidCount} baris Area Kirim-nya ga valid — ditandai skill '-' (sentinel di sesi_master_skill), perlu dicek & di-assign ulang manual lewat halaman Kelola Tarif Kiriman Rutin.");
        }

        $total = $created + $updated + count($errors);
        $prefix = $dryRun ? '[DRY-RUN] ' : '';
        $this->info("{$prefix}Selesai: {$created} tarif dibuat, {$updated} tarif diperbarui, " . count($errors) . " error, dari {$total} kombinasi vendor-skill+barang.");

        return empty($errors) ? self::SUCCESS : self::FAILURE;
    }

    /**
     * Nama sub-kolom di file asli sering ke-wrap jadi 2 baris di dalam 1 sel
     * Excel (misal "Per Koli\n(Cat Pail)"), sedangkan master di
     * sesi_jenis_barang_kiriman nulisnya 1 baris pakai spasi biasa ("Per
     * Koli (Cat Pail)") — trim() doang ga cukup krn cuma bersihin ujung
     * string, bukan whitespace/newline DI TENGAH. Makanya semua whitespace
     * run (spasi/tab/newline berturut-turut) diseragamkan jadi 1 spasi dulu.
     */
    private function normalize(string $value): string
    {
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;
        return mb_strtolower(trim($value));
    }

    /**
     * Resolve isi cell "Area Kirim" (bisa 1 nama area, atau beberapa nama
     * dipisah koma) jadi array id_skill numeric (sesi_master_skill) — 1
     * elemen per nama area, karena sesi_perusahaan_skill sekarang 1 baris =
     * 1 skill (bukan comma-CSV dalam 1 kolom lagi). Nama area yg belum ada
     * di master TAPI kelihatan valid (bukan garbage) di-auto-create — nama
     * yg jelas gabungan kalimat/kepanjangan di-skip token itu aja (bukan
     * gagalin semua). Cell yg dari awal udah kepanjangan bgt (kemungkinan
     * besar bukan daftar area, tapi catatan/kalimat) langsung dianggap ga
     * ke-resolve sama sekali → caller fallback ke skill placeholder '-'.
     *
     * @return array<int, int|string> array kosong kalau ga ada satupun token yg valid
     */
    private function resolveAreaKirimToSkillIds(string $rawAreaKirim, \Illuminate\Support\Collection $skillCache, array &$autoCreatedSkill, bool $dryRun): array
    {
        if (mb_strlen($rawAreaKirim) > self::AREA_KIRIM_RAW_MAX_LENGTH) {
            return [];
        }

        $tokens = array_values(array_filter(array_map('trim', explode(',', $rawAreaKirim)), fn ($t) => $t !== ''));
        if (empty($tokens)) {
            return [];
        }

        $ids = [];
        foreach ($tokens as $token) {
            $nama = strtoupper(preg_replace('/\s+/u', ' ', $token));
            if ($nama === '' || mb_strlen($nama) > self::NAMA_SKILL_MAX_LENGTH) {
                continue; // token individual masih kepanjangan/kosong, lewati token ini aja
            }

            $id = $skillCache->get($nama);
            if ($id === null) {
                $id = DB::connection('sqlsrv')->table('sesi_master_skill')->where('nama_skill', $nama)->value('id_skill');

                if (!$id) {
                    if ($dryRun) {
                        // Jangan nulis apapun di dry-run — id palsu (unik per nama) cukup
                        // buat preview laporan, tanpa collapse ke 1 nilai yg sama.
                        $id = 'DRYRUN:' . $nama;
                    } else {
                        $id = DB::connection('sqlsrv')->table('sesi_master_skill')->insertGetId([
                            'nama_skill' => $nama,
                            'flag'       => true,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                    $autoCreatedSkill[] = $nama;
                }
                $skillCache->put($nama, $id);
            }
            $ids[] = $id;
        }

        return array_values(array_unique($ids));
    }

    /**
     * Resolve-or-create 1 baris sentinel di sesi_master_skill (nama_skill='-',
     * flag=false) — dipakai buat baris "Area Kirim" yang ga bisa di-resolve ke
     * skill valid manapun, TETAP disimpan (bukan dilewati) tapi ditandai '-'
     * biar gampang dicari & dibenerin manual. Pola sama persis kayak
     * `ImportTarifSewaTrukCommand::resolvePlaceholderSkillId()`.
     */
    private function resolvePlaceholderSkillId(\Illuminate\Support\Collection $skillCache, bool $dryRun): int|string
    {
        $cacheKey = '__PLACEHOLDER__';
        $id = $skillCache->get($cacheKey);
        if ($id !== null) {
            return $id;
        }

        $id = DB::connection('sqlsrv')->table('sesi_master_skill')->where('nama_skill', self::PLACEHOLDER)->value('id_skill');

        if (!$id) {
            if ($dryRun) {
                $id = 'DRYRUN:' . self::PLACEHOLDER;
            } else {
                $id = DB::connection('sqlsrv')->table('sesi_master_skill')->insertGetId([
                    'nama_skill' => self::PLACEHOLDER,
                    'flag'       => false,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        $skillCache->put($cacheKey, $id);
        return $id;
    }

    /**
     * File CSV asli sering ga murni UTF-8 (hasil "Save As CSV" dari Excel di
     * Windows biasanya kepake code page 1252). Kalau dikirim apa adanya ke
     * SQL Server, driver sqlsrv nolak dengan error "translating string to
     * UCS-2". Deteksi & convert ke UTF-8 dulu per sel biar aman disimpan.
     */
    private function toUtf8(string $value): string
    {
        if ($value === '' || mb_check_encoding($value, 'UTF-8')) {
            return $value;
        }
        $converted = @mb_convert_encoding($value, 'UTF-8', 'Windows-1252');
        return $converted !== false ? $converted : $value;
    }
}

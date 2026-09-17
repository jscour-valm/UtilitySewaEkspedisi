<?php

namespace App\Console\Commands;

use App\Models\PerusahaanEkspedisi;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Import data rate Sewa Truk asli (histori per kombinasi cabang+area+vendor)
 * dari file CSV. Data restricted — command ini cuma alat baca file.
 *
 * Format CSV (header wajib persis ini, dipisah koma):
 *   No,Kode Area,Kode Cabang,Nama Cabang,Nama Ekspedisi,Badan Usaha,KTP/NPWP,Area Kirim,Harga Sewa,Revisi,Tanggal Revisi,Start Date,End Date,Update Date
 *
 * Kolom yang DIPAKAI: Kode Cabang, Nama Ekspedisi, Badan Usaha, KTP/NPWP,
 * Area Kirim, Harga Sewa, Revisi, Tanggal Revisi, Start Date (→ created_at),
 * End Date (→ updated_at), Update Date.
 *
 * Kolom yang SENGAJA DIABAIKAN: No (nomor urut baris file sumber, ga ada
 * makna bisnis), Kode Area & Nama Cabang (BISA di-join dari Kode Cabang ke
 * sesi_master_cabang — kolom Area & Name — pas ditampilkan, jadi ga perlu
 * disimpan ulang di sini).
 *
 * Data disimpan sebagai row `sesi_perusahaan_skill` — 1 baris per kombinasi
 * vendor+skill/area+cabang, bukan lagi digabung comma-CSV dalam 1 baris
 * `sesi_unit_kendaraan` (rate-card lama, direfactor 14 Sept — lihat migration
 * 2026_09_14_100000_create_sesi_perusahaan_skill_table.php). Kalau "Area
 * Kirim" 1 baris CSV punya beberapa nama area, masing2 jadi baris
 * sesi_perusahaan_skill sendiri (unique per vendor+skill+cabang), semua
 * bawa harga_sewa yang sama dari baris CSV itu.
 *
 * "Area Kirim" yang ga bisa di-resolve ke skill valid TETAP disimpan
 * (bukan dilewati) — ditandai pakai 1 baris sentinel di sesi_master_skill
 * (nama_skill='-', flag=false, resolve-or-create sekali lewat
 * resolvePlaceholderSkillId()) biar gampang dicari & dibenerin manual, tanpa
 * melanggar FK id_skill (beda dari kolom lama yang varchar bebas, bisa
 * langsung diisi teks "-"). flag=false bikin skill ini otomatis nggak
 * pernah muncul di dropdown skill manapun (semua query filter flag=true).
 *
 * Idempotent: dijalankan ulang dengan file yang sama tidak akan bikin
 * duplikat, cuma update baris yang sudah ada (kombinasi vendor+cabang+skill).
 *
 * Contoh:
 *   php artisan import:tarif-sewa-truk storage/app/import-data/tarif_sewa_truk.csv
 *   php artisan import:tarif-sewa-truk storage/app/import-data/tarif_sewa_truk.csv --dry-run
 */
class ImportTarifSewaTrukCommand extends Command
{
    protected $signature = 'import:tarif-sewa-truk
                            {path : Path ke file CSV}
                            {--dry-run : Validasi & laporkan tanpa menulis ke database}';

    protected $description = 'Import data rate Sewa Truk (histori per cabang+area+vendor) dari file CSV ke sesi_perusahaan_skill (1 baris per vendor+skill/area+cabang)';

    private const REQUIRED_HEADERS = ['Kode Cabang', 'Nama Ekspedisi', 'Badan Usaha', 'Area Kirim', 'Harga Sewa'];
    private const BADAN_USAHA_VALID = ['PT', 'CV', 'UD', 'Perseorangan'];
    private const PLACEHOLDER = '-';
    private const BADAN_USAHA_DEFAULT = self::PLACEHOLDER; // ditambah ke enum lewat migration 2026_09_09_000001, khusus placeholder impor
    private const AREA_KIRIM_RAW_MAX_LENGTH = 200; // cell "Area Kirim" mentah di atas ini dianggap garbage/campur kalimat, bukan daftar area bersih
    private const NAMA_SKILL_MAX_LENGTH = 100; // sesuai kolom sesi_master_skill.nama_skill (varchar 100)
    private const HARGA_SEWA_MAX_DIGITS = 13; // sesuai kolom sesi_perusahaan_skill.harga_sewa decimal(15,2) — 13 digit di depan koma

    public function handle(): int
    {
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
            $this->error('Ga ketemu baris header yang punya semua kolom wajib dalam 15 baris pertama file ini.');
            $this->error('Kolom wajib: ' . implode(', ', self::REQUIRED_HEADERS));
            fclose($handle);
            return self::FAILURE;
        }

        if ($dryRun) {
            $this->warn('MODE DRY-RUN — tidak ada perubahan yang ditulis ke database.');
        }
        $this->info('Kolom No, Kode Area, Nama Cabang diabaikan (Kode Area & Nama Cabang bisa di-join dari Kode Cabang pas ditampilkan).');

        $created = 0;
        $updated = 0;
        $errors = [];
        $blankSkipped = 0;
        $areaTidakValidCount = 0; // baris yg Area Kirim-nya ga ke-resolve — TETAP disimpan, skill-nya ditandai '-'
        $incomplete = []; // "Baris N: kolom X, Y diganti '-' / dikosongkan"
        $badanUsahaDefaulted = 0;
        $vendorBaru = [];
        $vendorBadanUsahaDiupdate = [];
        $autoCreatedSkill = [];
        $skillCache = collect(); // nama_skill (uppercase) => id_skill, cache biar ga query berulang
        $rowNumber = 1; // baris 1 = header

        while (($row = fgetcsv($handle)) !== false) {
            $rowNumber++;

            if (count($row) === 1 && trim((string) $row[0]) === '') {
                continue; // baris kosong (cuma 1 sel kosong), skip
            }

            if (count($row) !== count($header)) {
                $errors[] = "Baris {$rowNumber}: jumlah kolom (" . count($row) . ") tidak sama dengan header (" . count($header) . ").";
                continue;
            }

            $record = array_combine($header, array_map(fn ($v) => trim($this->toUtf8((string) $v)), $row));

            $rawKodeCabang = $record['Kode Cabang'] ?? '';
            $rawNamaEkspedisi = $record['Nama Ekspedisi'] ?? '';
            $rawAreaKirim = $record['Area Kirim'] ?? '';
            $rawHargaSewa = $record['Harga Sewa'] ?? '';
            $hargaSewaDigits = $this->parseRupiah($rawHargaSewa);

            // Baris yang SEMUA kolom kuncinya kosong itu bukan data, cuma sisa
            // baris kosong/pemisah di file Excel — skip diam2, ga dianggap error.
            if ($rawKodeCabang === '' && $rawNamaEkspedisi === '' && $rawAreaKirim === '' && $hargaSewaDigits === null) {
                $blankSkipped++;
                continue;
            }

            // Kolom yang kosong/ga valid diganti "-" (atau NULL utk harga) supaya
            // barisnya TETAP ke-import, bukan ditolak total — ditandai di ringkasan
            // biar kelihatan mana yang datanya ga lengkap, gampang dicek manual.
            $incompleteFields = [];

            $idCabang = $rawKodeCabang !== '' ? strtoupper($rawKodeCabang) : self::PLACEHOLDER;
            if ($rawKodeCabang === '') $incompleteFields[] = 'Kode Cabang';

            // Cell "Nama Ekspedisi" kadang ke-wrap 2 baris di Excel (nama vendor
            // di baris 1, no. telp/nama kontak dlm kurung di baris 2) — hasil
            // save-as-CSV nyimpen newline itu literal di dalam 1 cell. Collapse
            // semua whitespace run (termasuk newline & non-breaking-space hasil
            // encoding Windows-1252) jadi 1 spasi biar nama vendor rapi 1 baris
            // & match-nya konsisten antar file (hindari duplikat vendor kalau
            // CSV lain nge-wrap baris yang sama dgn cara beda).
            $namaEkspedisiNormalized = $rawNamaEkspedisi !== '' ? preg_replace('/\s+/u', ' ', $rawNamaEkspedisi) : '';
            $namaEkspedisi = $namaEkspedisiNormalized !== '' ? $namaEkspedisiNormalized : self::PLACEHOLDER;
            if ($rawNamaEkspedisi === '') $incompleteFields[] = 'Nama Ekspedisi';

            $idSkillIds = $rawAreaKirim !== '' ? $this->resolveAreaKirimToSkillIds($rawAreaKirim, $skillCache, $autoCreatedSkill, $dryRun) : [];
            $areaTidakValid = $rawAreaKirim !== '' && empty($idSkillIds);
            if ($rawAreaKirim === '') {
                $incompleteFields[] = 'Area Kirim';
            } elseif ($areaTidakValid) {
                // "Area Kirim" ga bisa di-resolve jadi skill/area yang valid (kepanjangan/
                // kelihatan gabungan kalimat, bukan daftar nama area bersih) — baris tetap
                // disimpan, skill-nya ditandai '-' (sentinel di sesi_master_skill,
                // resolvePlaceholderSkillId()) biar gampang dicari & dibenerin manual.
                $incompleteFields[] = "Area Kirim (nilai '{$rawAreaKirim}' ga bisa di-resolve ke skill/area yang valid, ditandai skill '-')";
                $areaTidakValidCount++;
            }
            if (empty($idSkillIds)) {
                $idSkillIds = [$this->resolvePlaceholderSkillId($skillCache, $dryRun)];
            }

            $hargaSewa = $hargaSewaDigits; // null kalau kosong/ga kebaca sbg angka — kolomnya emang nullable
            if ($hargaSewaDigits === null && trim($rawHargaSewa) !== '') {
                $incompleteFields[] = "Harga Sewa (nilai '{$rawHargaSewa}' ga kebaca sbg angka)";
            } elseif ($hargaSewaDigits === null) {
                $incompleteFields[] = 'Harga Sewa';
            } elseif (strlen($hargaSewaDigits) > self::HARGA_SEWA_MAX_DIGITS) {
                // Beberapa baris asli isi "Harga Sewa"-nya lebih dari 1 angka digabung
                // (misal beberapa opsi harga situasional dalam 1 cell) — kalau semua
                // karakter non-digit dibuang, hasilnya jadi 1 angka raksasa yang
                // overflow kolom decimal(15,2). Default ke NULL drpd baris gagal
                // total & kehilangan data id_cabang/id_skill-nya — nilai aslinya
                // tetap ditulis di laporan biar bisa dicek/dipecah manual.
                $incompleteFields[] = "Harga Sewa (nilai '{$rawHargaSewa}' kelihatannya gabungan beberapa angka, kelebihan digit, dikosongkan)";
                $hargaSewa = null;
            }

            $badanUsaha = $this->normalizeBadanUsaha($record['Badan Usaha'] ?? '');
            $badanUsahaWasDefaulted = !in_array($badanUsaha, self::BADAN_USAHA_VALID, true);
            if ($badanUsahaWasDefaulted) {
                // Data sumber ga selalu bener (misal isinya "Ekspedisi", bukan bentuk
                // badan hukum) — dikonfirmasi Jo, dianggap data ga akurat. Default ke
                // "-" (placeholder, bukan nilai asli PT/CV/UD/Perseorangan manapun)
                // biar tetap ke-import TAPI jelas kelihatan butuh dibenerin manual,
                // ga ketuker sama vendor yang beneran berbadan hukum PT.
                $badanUsaha = self::BADAN_USAHA_DEFAULT;
                $badanUsahaDefaulted++;
            }

            if (!empty($incompleteFields)) {
                $incomplete[] = "Baris {$rowNumber}: " . implode(', ', $incompleteFields);
            }

            // KTP/NPWP: identitas vendor, kolom baru (migration 2026_09_16_000000).
            // Kosong dibiarkan NULL (bukan "-") — beda dari badan_usaha/no_telepon
            // yang emang wajib diisi placeholder, KTP/NPWP murni informasional.
            $rawKtpNpwp = trim($record['KTP/NPWP'] ?? '');
            $ktpNpwp = $rawKtpNpwp !== '' ? $rawKtpNpwp : null;

            // Revisi/Tanggal Revisi/Update Date: kolom baru di sesi_perusahaan_skill
            // (migration 2026_09_16_000000) — murni informasional, ga ada validasi
            // ketat kayak Harga Sewa/Area Kirim, kosong/ga kebaca ya NULL aja.
            $rawRevisi = trim($record['Revisi'] ?? '');
            $revisi = $rawRevisi !== '' ? $rawRevisi : null;
            $tanggalRevisi = $this->parseDate($record['Tanggal Revisi'] ?? null);
            $updateDateSource = $this->parseDate($record['Update Date'] ?? null);

            $data = ['badan_usaha' => $badanUsaha, 'harga_sewa' => $hargaSewa];
            $startDate = $this->parseDate($record['Start Date'] ?? null);
            $endDate = $this->parseDate($record['End Date'] ?? null);

            try {
                // Cari vendor existing dulu — CSV Sewa Truk ga punya kolom telepon/alamat,
                // jadi JANGAN timpa data kontak asli vendor yang mungkin sudah ada
                // (misal sudah diimport lewat import:perusahaan-ekspedisi). Placeholder
                // no_telepon/alamat_kantor cuma dipakai kalau ini vendor BENAR-BENAR baru.
                //
                // PENTING: create()/update() vendor HANYA boleh jalan kalau BUKAN
                // dry-run (bug lama: blok ini kepasang di luar cek $dryRun, jadi
                // --dry-run tetap nulis vendor baru beneran ke DB kantor). Pas
                // dry-run, vendor yang belum ada dikasih id palsu "DRYRUN:..."
                // (pola sama kayak ImportTarifKirimanRutinWideCommand) biar exists-
                // check rate card di bawah tetap akurat tanpa nulis apapun.
                // withInactive(): import boleh match & reactivate vendor yang pernah di-soft-delete
                $vendor = PerusahaanEkspedisi::withInactive()->where('nama_perusahaan', $namaEkspedisi)->first();
                $vendorIsNew = $vendor === null;
                $vendorBadanUsahaAkanDiupdate = $vendor && !$badanUsahaWasDefaulted && $vendor->badan_usaha !== $data['badan_usaha'];
                // KTP/NPWP boleh diisi belakangan lewat CSV lain — jangan timpa jadi
                // NULL kalau baris ini kosong tapi vendor udah punya nilai tersimpan.
                $vendorKtpNpwpAkanDiupdate = $vendor && $ktpNpwp !== null && $vendor->ktp_npwp !== $ktpNpwp;

                if ($dryRun) {
                    $vendorId = $vendor->id_perusahaan ?? ('DRYRUN:' . $namaEkspedisi);
                } elseif ($vendor) {
                    // Jangan timpa badan_usaha existing pakai nilai yang di-default —
                    // itu tebakan, bukan data asli. Cuma update kalau nilai CSV-nya
                    // memang valid (bukan hasil default) dan beda dari yang tersimpan.
                    if ($vendorBadanUsahaAkanDiupdate || $vendorKtpNpwpAkanDiupdate) {
                        $vendor->update(array_filter([
                            'badan_usaha' => $vendorBadanUsahaAkanDiupdate ? $data['badan_usaha'] : null,
                            'ktp_npwp' => $vendorKtpNpwpAkanDiupdate ? $ktpNpwp : null,
                        ], fn ($v) => $v !== null));
                    }
                    $vendorId = $vendor->id_perusahaan;
                } else {
                    $vendor = PerusahaanEkspedisi::create([
                        'nama_perusahaan' => $namaEkspedisi,
                        'badan_usaha' => $data['badan_usaha'],
                        'ktp_npwp' => $ktpNpwp,
                        'no_telepon' => self::PLACEHOLDER,
                        'alamat_kantor' => self::PLACEHOLDER,
                        'flag' => true,
                    ]);
                    $vendorId = $vendor->id_perusahaan;
                }

                if ($vendorIsNew) {
                    $vendorBaru[] = $namaEkspedisi;
                } elseif ($vendorBadanUsahaAkanDiupdate) {
                    $vendorBadanUsahaDiupdate[] = "{$namaEkspedisi} ({$vendor->badan_usaha} → {$data['badan_usaha']})";
                }

                // Vendor dgn id palsu "DRYRUN:..." pasti belum ada di DB sama sekali
                // (baru mau dibuat) — jangan query pakai id palsu itu, langsung "dibuat".
                $vendorIsNewCombo = str_starts_with((string) $vendorId, 'DRYRUN:');

                // 1 baris CSV bisa resolve ke beberapa skill (Area Kirim isi >1 nama) —
                // masing2 jadi baris sesi_perusahaan_skill sendiri, semua bawa
                // harga_sewa yang sama dari baris CSV ini.
                foreach ($idSkillIds as $skillId) {
                    $skillIsDryRunPlaceholder = is_string($skillId) && str_starts_with($skillId, 'DRYRUN:');
                    $isNewCombo = $vendorIsNewCombo || $skillIsDryRunPlaceholder;

                    if ($dryRun) {
                        if ($isNewCombo) {
                            $created++;
                            continue;
                        }
                        $exists = DB::connection('sqlsrv')->table('sesi_perusahaan_skill')
                            ->where('id_perusahaan', $vendorId)
                            ->where('id_skill', $skillId)
                            ->where('cabang_code', $idCabang)
                            ->exists();
                        $exists ? $updated++ : $created++;
                        continue;
                    }

                    $existing = DB::connection('sqlsrv')->table('sesi_perusahaan_skill')
                        ->where('id_perusahaan', $vendorId)
                        ->where('id_skill', $skillId)
                        ->where('cabang_code', $idCabang)
                        ->first();

                    $values = [
                        'id_perusahaan' => $vendorId,
                        'id_skill' => $skillId,
                        'cabang_code' => $idCabang,
                        'harga_sewa' => $data['harga_sewa'],
                        'revisi' => $revisi,
                        'tanggal_revisi' => $tanggalRevisi,
                        'update_date_source' => $updateDateSource,
                        'flag' => true,
                        'updated_at' => $endDate ?? now(),
                    ];

                    if ($existing) {
                        DB::connection('sqlsrv')->table('sesi_perusahaan_skill')
                            ->where('id_vendor_skill', $existing->id_vendor_skill)
                            ->update($values);
                        $updated++;
                    } else {
                        DB::connection('sqlsrv')->table('sesi_perusahaan_skill')->insert([
                            ...$values,
                            'created_at' => $startDate ?? now(),
                        ]);
                        $created++;
                    }
                }
            } catch (\Throwable $e) {
                $errors[] = "Baris {$rowNumber}: gagal menyimpan — " . $e->getMessage();
            }
        }

        fclose($handle);

        $this->newLine();
        if (!empty($errors)) {
            $this->error('Baris gagal disimpan (bukan soal data ga lengkap, tapi error teknis):');
            foreach ($errors as $err) {
                $this->line("  - {$err}");
            }
            $this->newLine();
        }

        if (!empty($incomplete)) {
            $this->warn('Baris dengan data ga lengkap — tetap diimport, kolom yang kosong diganti "' . self::PLACEHOLDER . '" (cek & benerin manual lewat form vendor/kendaraan kalau perlu):');
            foreach ($incomplete as $note) {
                $this->line("  - {$note}");
            }
            $this->newLine();
        }

        if ($badanUsahaDefaulted > 0) {
            $this->warn("{$badanUsahaDefaulted} baris punya nilai Badan Usaha yang ga valid (bukan PT/CV/UD/Perseorangan) — di-default ke \"" . self::BADAN_USAHA_DEFAULT . "\". Cek & benerin manual lewat form vendor kalau perlu.");
        }

        if ($blankSkipped > 0) {
            $this->info("{$blankSkipped} baris kosong total (bukan data, kemungkinan sisa baris pemisah/footer di file) dilewati diam-diam.");
        }

        if (!empty($vendorBaru)) {
            $prefixVendor = $dryRun ? 'akan didaftarkan' : 'didaftarkan';
            $this->warn(count(array_unique($vendorBaru)) . " vendor BARU {$prefixVendor} (belum ada di sesi_perusahaan_ekspedisi sebelumnya): " . implode(', ', array_unique($vendorBaru)));
        }
        if (!empty($autoCreatedSkill)) {
            $prefixSkill = $dryRun ? 'akan didaftarkan' : 'didaftarkan';
            $this->warn(count(array_unique($autoCreatedSkill)) . " skill/area BARU {$prefixSkill} ke sesi_master_skill (nama Area Kirim ga cocok ke master yang ada) — cek/rapihin manual kalau perlu: " . implode(', ', array_unique($autoCreatedSkill)));
        }
        if (!empty($vendorBadanUsahaDiupdate)) {
            $prefixUpdate = $dryRun ? 'akan diupdate' : 'diupdate';
            $this->warn(count($vendorBadanUsahaDiupdate) . " vendor existing badan_usaha-nya {$prefixUpdate}: " . implode(', ', $vendorBadanUsahaDiupdate));
        }

        $total = $created + $updated + count($errors);
        $prefix = $dryRun ? '[DRY-RUN] ' : '';
        $this->info("{$prefix}Selesai: {$created} baris rate dibuat, {$updated} diperbarui (1 baris CSV bisa hasilin >1 baris rate kalau Area Kirim isi >1 skill), " . count($incomplete) . " ga lengkap (ditandai '-'), {$areaTidakValidCount} di antaranya Area Kirim-nya ga valid (skill disimpan sbg '-', perlu dicek manual), " . count($errors) . " error teknis, {$blankSkipped} baris kosong dilewati, dari {$total} baris diproses.");

        if ($areaTidakValidCount > 0) {
            $this->warn("Cari baris dengan skill '-' lewat halaman Kelola Tarif Kiriman Rutin atau query: SELECT * FROM sesi_perusahaan_skill WHERE id_skill = (SELECT id_skill FROM sesi_master_skill WHERE nama_skill = '-')");
        }

        return empty($errors) ? self::SUCCESS : self::FAILURE;
    }

    /**
     * File CSV asli sering ga murni UTF-8 (hasil "Save As CSV" dari Excel di
     * Windows biasanya kepake code page 1252 — apostrof/tanda kutip lengkung,
     * karakter aneh di nama perusahaan, dll). Kalau dikirim apa adanya ke SQL
     * Server, driver sqlsrv nolak dengan error "translating string to UCS-2".
     * Deteksi & convert ke UTF-8 dulu per sel biar aman disimpan.
     */
    private function toUtf8(string $value): string
    {
        if ($value === '' || mb_check_encoding($value, 'UTF-8')) {
            return $value;
        }
        $converted = @mb_convert_encoding($value, 'UTF-8', 'Windows-1252');
        return $converted !== false ? $converted : $value;
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
     * ke-resolve sama sekali → caller fallback ke placeholder "-".
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
     * biar gampang dicari & dibenerin manual. flag=false bikin skill ini
     * otomatis nggak pernah muncul di dropdown skill manapun (semua query
     * listSkill/getSkillList filter flag=true), tapi tetap kebaca kalau
     * ditampilkan by-id (FormatHelper::skillNames(), PerusahaanSkill::nama_skill).
     *
     * Pakai $skillCache yang sama kayak resolveAreaKirimToSkillIds() (key
     * khusus '__PLACEHOLDER__', ga akan collide sama nama skill asli krn
     * nama skill asli udah di-uppercase & di-trim sebelum jadi cache key).
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

    private function normalizeBadanUsaha(string $value): string
    {
        $value = trim($value);
        // Toleransi nama lama "Perorangan" (sudah di-rename jadi "Perseorangan")
        if (strcasecmp($value, 'Perorangan') === 0) {
            return 'Perseorangan';
        }
        return $value;
    }

    private function parseRupiah(string $value): ?string
    {
        $digits = preg_replace('/[^0-9]/', '', $value);
        return $digits === '' ? null : $digits;
    }

    private function parseDate(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        // Coba format Indonesia umum dulu (d/m/Y, d-m-Y) — Carbon::parse() generik
        // salah nebak "31/12/2026" sebagai m/d/Y (bulan 31 invalid) dan gagal diam-diam.
        foreach (['d/m/Y', 'd-m-Y', 'd/m/y', 'Y-m-d'] as $format) {
            try {
                return \Carbon\Carbon::createFromFormat($format, $value)->startOfDay()->toDateTimeString();
            } catch (\Throwable) {
                // coba format berikutnya
            }
        }

        try {
            return \Carbon\Carbon::parse($value)->toDateTimeString();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * File asli sering punya baris judul/metadata (misal "Data, 7 Sept 2026,
     * ... Insert KTP/NPWP ...") atau baris kosong SEBELUM baris header
     * beneran (hasil export Excel dgn judul di atas tabel). Makanya scan
     * beberapa baris pertama, bukan asumsi baris 1 selalu header — baris
     * yang dianggap header adalah baris pertama yang memuat SEMUA nama
     * kolom wajib (persis, abaikan spasi depan/belakang tiap sel).
     *
     * @return string[]|null
     */
    private function readHeader($handle): ?array
    {
        $maxScan = 15;
        for ($i = 0; $i < $maxScan; $i++) {
            $row = fgetcsv($handle);
            if ($row === false) {
                return null; // habis file, ga ketemu header
            }

            $row[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $row[0]);
            $row = array_map(fn ($h) => trim($this->toUtf8((string) $h)), $row);

            $missing = array_diff(self::REQUIRED_HEADERS, $row);
            if (empty($missing)) {
                return $row;
            }
        }

        return null; // ga ketemu baris header yang cocok dalam batas scan
    }
}

<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CabangSkillSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('Seeding sesi_cabang_skill dari Q_CustomerLocusAtribute...');

        // Ambil valid cabang_code sebagai whitelist
        $validCabang = DB::connection('sqlsrv')
            ->table('sesi_user_cabang')
            ->whereNotNull('cabang_code')
            ->distinct()
            ->pluck('cabang_code')
            ->map(fn($c) => strtoupper(trim($c)))
            ->flip()
            ->toArray();

        $this->command->info('Valid cabang ditemukan: ' . count($validCabang));

        // Lookup nama_skill -> id_skill numeric (id_skill sekarang bigint auto-increment,
        // bukan lagi nama sbg PK — jadi perlu di-pluck keyed by nama_skill)
        $masterSkill = DB::connection('sqlsrv')
            ->table('sesi_master_skill')
            ->where('flag', true)
            ->pluck('id_skill', 'nama_skill')
            ->toArray();

        $this->command->info('Master skill ditemukan: ' . count($masterSkill));

        DB::connection('sqlsrv')->table('sesi_cabang_skill')->delete();

        $seen    = [];
        $total   = 0;
        $skipped = 0;

        try {
            DB::connection('sqlsrv')
                ->table('Q_CustomerLocusAtribute')
                ->whereNotNull('county')
                ->where('county', '!=', '')
                ->whereNotNull('skills')
                ->where('skills', '!=', '')
                ->select('county', 'skills')
                ->orderBy('No_')
                ->chunk(1000, function ($rows) use (&$seen, &$total, &$skipped, $validCabang, $masterSkill) {
                    $inserts = [];

                    foreach ($rows as $row) {
                        // Parse cabang_code dari county (format: "01A-something")
                        $dashPos = strpos($row->county, '-');
                        if ($dashPos === false) {
                            $skipped++;
                            continue;
                        }

                        $cabangCode = strtoupper(trim(substr($row->county, 0, $dashPos)));

                        if (!isset($validCabang[$cabangCode])) {
                            $skipped++;
                            continue;
                        }

                        $raw = trim($row->skills);
                        $jumlahKoma = substr_count($raw, ',');

                        if ($jumlahKoma <= 1) {
                            $tokens = [preg_replace('/\s*,\s*/', ' ', $raw)];
                        } else {
                            $tokens = explode(',', $raw);
                        }

                        foreach ($tokens as $token) {
                            $skillNama = strtoupper(trim(preg_replace('/\s+/', ' ', $token)));
                            $skillNama = preg_replace('/^[^A-Z0-9]+|[^A-Z0-9]+$/', '', $skillNama); // strip karakter kotor (mis. "?") di awal/akhir — bug ?ACBAR dari source korup

                            if ($skillNama === '' || !isset($masterSkill[$skillNama])) {
                                $skipped++;
                                continue;
                            }

                            $key = $cabangCode . '|' . $skillNama;
                            if (isset($seen[$key])) {
                                continue;
                            }
                            $seen[$key] = true;

                            $inserts[] = [
                                'cabang_code' => $cabangCode,
                                'id_skill'    => $masterSkill[$skillNama],
                                'flag'        => true,
                                'created_at'  => now(),
                                'updated_at'  => now(),
                            ];
                        }
                    }

                    if (!empty($inserts)) {
                        foreach (array_chunk($inserts, 400) as $chunk) {
                            DB::connection('sqlsrv')->table('sesi_cabang_skill')->insert($chunk);
                        }
                        $total += count($inserts);
                    }

                    $this->command->info("Processed batch, total inserted: {$total}, skipped: {$skipped}");
                });

            $this->command->info("✓ Selesai! Total inserted: {$total}, skipped: {$skipped}");
        } catch (\Exception $e) {
            $this->command->warn("⚠ Q_CustomerLocusAtribute tidak ditemukan (external table dari production)");
            $this->command->info("ℹ Jika di production, data ini akan di-seed dari Quantum API");
        }
    }
}
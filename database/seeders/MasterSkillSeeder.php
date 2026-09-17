<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MasterSkillSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('Seeding sesi_master_skill dari Q_CustomerLocusAtribute.skills...');

        // delete() bukan truncate() — TRUNCATE ditolak SQL Server selama ada FK
        // constraint dari sesi_cabang_skill ke tabel ini, walau child-nya kosong.
        DB::connection('sqlsrv')->table('sesi_master_skill')->delete();

        $seen  = [];
        $total = 0;

        DB::connection('sqlsrv')
            ->table('Q_CustomerLocusAtribute')
            ->whereNotNull('skills')
            ->where('skills', '!=', '')
            ->select('skills')
            ->orderBy("No_")
            ->chunk(1000, function ($rows) use (&$seen, &$total) {
                $inserts = [];

                foreach ($rows as $row) {
                    $raw = trim($row->skills);

                    // Hitung jumlah koma
                    $jumlahKoma = substr_count($raw, ',');

                    if ($jumlahKoma <= 1) {
                        // 0 atau 1 koma → 1 skill, normalize koma → spasi
                        $tokens = [preg_replace('/\s*,\s*/', ' ', $raw)];
                    } else {
                        // >1 koma → multiple skills, split by koma
                        $tokens = explode(',', $raw);
                    }

                    foreach ($tokens as $token) {
                        $skill = strtoupper(trim(preg_replace('/\s+/', ' ', $token)));
                        $skill = preg_replace('/^[^A-Z0-9]+|[^A-Z0-9]+$/', '', $skill); // strip karakter kotor (mis. "?") di awal/akhir — bug ?ACBAR dari source korup
                        if ($skill === '' || isset($seen[$skill])) {
                            continue;
                        }
                        $seen[$skill] = true;
                        $inserts[] = [
                            'nama_skill' => $skill,
                            'flag'       => true,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                    }
                }

                if (!empty($inserts)) {
                    foreach (array_chunk($inserts, 400) as $chunk) {
                        DB::connection('sqlsrv')->table('sesi_master_skill')->insert($chunk);
                    }
                    $total += count($inserts);
                }

                $this->command->info("Total inserted so far: {$total}");
            });

        $this->command->info("✓ Selesai! Total master skill: {$total}");
    }
}
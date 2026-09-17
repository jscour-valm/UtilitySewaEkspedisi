<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class JenisBiayaSeeder extends Seeder
{
    public function run(): void
    {
        // Delete instead of truncate to avoid foreign key constraint issues
        DB::connection('sqlsrv')->table('sesi_jenis_biaya')->delete();

        DB::connection('sqlsrv')->table('sesi_jenis_biaya')->insert([
            [
                'nama_biaya' => 'Helper',
                'flag' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'nama_biaya' => 'Bongkar Muat',
                'flag' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'nama_biaya' => 'Bensin',
                'flag' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}

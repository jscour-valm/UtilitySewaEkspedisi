<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RasioSewaSeeder extends Seeder
{
    public function run(): void
    {
        DB::connection('sqlsrv')->table('sesi_rasio_sewa')->insert([
            [
                'persentase_maksimal' => 2.50,
                'effective_date' => now()->toDateString(),
                'end_date' => null,
                'flag' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}

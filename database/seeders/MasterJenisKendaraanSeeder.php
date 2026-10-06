<?php

namespace Database\Seeders;

use App\Models\MasterJenisKendaraan;
use Illuminate\Database\Seeder;

class MasterJenisKendaraanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $items = [
            'Pick Up' => 1.00,
            'CDE (Colt Diesel Engkel)' => 2.00,
            'CDD (Colt Diesel Double)' => 4.00,
            'Fuso' => 8.00,
            'Tronton' => 15.00,
            'Trailer 20ft' => 20.00,
            'Trailer 40ft' => 30.00,
        ];

        foreach ($items as $namaJenis => $muatanTon) {
            // withInactive(): kalau baris pernah di-soft-delete, reactivate (jangan bikin duplikat)
            MasterJenisKendaraan::withInactive()->firstOrCreate(
                ['nama_jenis' => $namaJenis],
                ['muatan_maksimal_ton' => $muatanTon, 'flag' => true],
            );
        }
    }
}

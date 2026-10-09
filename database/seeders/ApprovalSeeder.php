<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Tidak lagi mengisi apa pun: sesi_approval dikelola live lewat halaman Setting Approver (DCI).
 * Dipertahankan supaya pemanggilan dari DatabaseSeeder tetap aman.
 */
class ApprovalSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->warn('ApprovalSeeder dipensiunkan — sesi_approval sekarang dikelola live lewat halaman Setting Approver. Tidak ada yang dijalankan.');
    }
}

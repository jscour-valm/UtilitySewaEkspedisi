<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::connection('sqlsrv')->hasTable('sesi_jenis_biaya')) {
            Schema::connection('sqlsrv')->create('sesi_jenis_biaya', function (Blueprint $table) {
                $table->id('id_jenis_biaya');
                $table->string('nama_biaya', 100)->unique();
                $table->boolean('flag')->default(true);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->dropIfExists('sesi_jenis_biaya');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::connection('sqlsrv')->hasTable('sesi_perusahaan_ekspedisi')) {
            Schema::connection('sqlsrv')->create('sesi_perusahaan_ekspedisi', function (Blueprint $table) {
                $table->id('id_perusahaan');
                $table->string('nama_perusahaan', 255);
                $table->enum('badan_usaha', ['PT', 'CV', 'UD', 'Perorangan']);
                $table->string('no_telepon', 20);
                $table->text('alamat_kantor');
                $table->boolean('flag')->default(true);
                $table->timestamps();

                $table->unique('nama_perusahaan');
            });
        }
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->dropIfExists('sesi_perusahaan_ekspedisi');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::connection('sqlsrv')->hasTable('sesi_master_skill')) {
            Schema::connection('sqlsrv')->create('sesi_master_skill', function (Blueprint $table) {
                $table->id('id_master_skill');
                $table->string('nama_skill', 50)->unique();
                $table->boolean('flag')->default(true);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->dropIfExists('sesi_master_skill');
    }
};
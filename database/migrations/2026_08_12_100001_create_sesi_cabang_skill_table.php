<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::connection('sqlsrv')->dropIfExists('sesi_cabang_skill');
        
        Schema::connection('sqlsrv')->create('sesi_cabang_skill', function (Blueprint $table) {
            $table->string('cabang_code', 10);
            $table->string('id_skill', 50)->comment('No_ dari Q_CustomerLocusAtribute');
            $table->boolean('flag')->default(true);
            $table->timestamps();

            $table->primary(['cabang_code', 'id_skill']);
        });
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->dropIfExists('sesi_cabang_skill');
    }
};
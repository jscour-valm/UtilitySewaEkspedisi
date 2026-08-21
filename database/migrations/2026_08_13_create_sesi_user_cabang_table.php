<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('sqlsrv')->create('sesi_user_cabang', function (Blueprint $table) {
            $table->id('id_user_cabang');
            $table->string('username', 255);
            $table->string('cabang_code', 50)->nullable()->comment('references sesi_master_cabang.Code');
            $table->string('area', 50)->nullable()->comment('area/region untuk WM');
            $table->string('role', 10)->nullable()->comment('KG, WM, WH, DCI, KA');
            $table->boolean('flag')->default(true);
            $table->timestamps();

            // Composite unique key: username + cabang_code (allow null cabang for global access)
            $table->unique(['username', 'cabang_code']);
        });
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->dropIfExists('sesi_user_cabang');
    }
};

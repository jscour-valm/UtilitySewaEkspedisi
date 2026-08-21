<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('sqlsrv')->table('sesi_armada', function (Blueprint $table) {
            $table->string('id_cabang', 50)->change();
        });
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->table('sesi_armada', function (Blueprint $table) {
            $table->unsignedBigInteger('id_cabang')->change();
        });
    }
};

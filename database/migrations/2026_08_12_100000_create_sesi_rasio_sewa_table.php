<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::connection('sqlsrv')->hasTable('sesi_rasio_sewa')) {
            Schema::connection('sqlsrv')->create('sesi_rasio_sewa', function (Blueprint $table) {
                $table->id('id_rasio_sewa');
                $table->decimal('persentase_maksimal', 5, 2)->comment('e.g., 2.50');
                $table->date('effective_date')->nullable();
                $table->date('end_date')->nullable();
                $table->boolean('flag')->default(true)->comment('1=active, 0=deleted');
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->dropIfExists('sesi_rasio_sewa');
    }
};

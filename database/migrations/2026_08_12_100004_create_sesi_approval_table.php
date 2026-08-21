<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::connection('sqlsrv')->hasTable('sesi_approval')) {
            Schema::connection('sqlsrv')->create('sesi_approval', function (Blueprint $table) {
                $table->id('id_approval_rule');
                $table->unsignedBigInteger('id_cabang')->nullable()->comment('NULL = global rule');
                $table->enum('kategori_approval', ['normal', 'over_threshold']);
                $table->unsignedTinyInteger('tingkat')->comment('1=level 1 (WM), 2=level 2 (WH), etc');
                $table->enum('role_berwenang', ['WM', 'WH']);
                $table->unsignedBigInteger('id_approver_cadangan')->nullable()->comment('backup approver user id');
                $table->boolean('flag')->default(true);
                $table->timestamps();

                // Note: id_cabang nullable, bisa reference ke sesi_master_cabang tapi opsional
                // id_approver_cadangan bisa reference ke lntrn_users tapi opsional
            });
        }
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->dropIfExists('sesi_approval');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::connection('sqlsrv')->dropIfExists('sesi_pengajuan_sewa');

        Schema::connection('sqlsrv')->create('sesi_pengajuan_sewa', function (Blueprint $table) {
            $table->id('id_pengajuan_sewa');
            $table->unsignedBigInteger('id_armada');
            $table->string('id_cabang', 10)->comment('cabang_code, e.g. 01A');
            $table->date('tanggal_pengiriman');
            $table->decimal('value_muatan', 15, 2)->nullable()->comment('dari dokumen SJ/TO-ACB');
            $table->decimal('harga_sewa', 15, 2);
            $table->decimal('rasio_sewa', 5, 2)->nullable()->comment('computed: harga_sewa / value_muatan * 100');
            $table->enum('kategori_approval', ['normal', 'over_threshold'])->comment('locked saat submit');
            $table->enum('tujuan_penyewaan', ['Toko', 'PAC']);
            $table->string('id_skill', 20)->comment('kode skill area, e.g. ACKOT');
            $table->string('kategori_toko', 20)->nullable()->comment('DK, LKM, LKTM, EKS, dll');
            $table->enum('status_pengajuan', ['Pending', 'Approved', 'Rejected'])->default('Pending');
            $table->unsignedBigInteger('current_approval_rule_id')->nullable();
            $table->text('catatan_pengajuan')->nullable();
            $table->unsignedBigInteger('submitted_by')->comment('user id KaGud yang submit');
            $table->timestamp('submitted_at')->useCurrent();
            $table->boolean('flag')->default(true);
            $table->timestamps();

            $table->foreign('id_armada')->references('id_armada')->on('sesi_armada');
            $table->index(['id_cabang', 'status_pengajuan']);
            $table->index(['submitted_by']);
        });
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->dropIfExists('sesi_pengajuan_sewa');
    }
};
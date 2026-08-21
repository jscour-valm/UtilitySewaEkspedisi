<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::connection('sqlsrv')->hasTable('sesi_approval_log')) {
            Schema::connection('sqlsrv')->create('sesi_approval_log', function (Blueprint $table) {
                $table->id('id_approval_log');
                $table->unsignedBigInteger('id_pengajuan_sewa');
                $table->unsignedBigInteger('id_approval_rule');
                $table->unsignedBigInteger('id_approver')->comment('user id WM/WH yang decide');
                $table->enum('status', ['Approved', 'Rejected']);
                $table->timestamp('decided_at')->useCurrent();
                $table->text('alasan_penolakan')->nullable()->comment('hanya jika status=Rejected');
                $table->boolean('flag')->default(true);
                $table->timestamps();

                $table->foreign('id_pengajuan_sewa')->references('id_pengajuan_sewa')->on('sesi_pengajuan_sewa');
                $table->foreign('id_approval_rule')->references('id_approval_rule')->on('sesi_approval');
                $table->index(['id_pengajuan_sewa']);
                $table->index(['id_approver']);
            });
        }
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->dropIfExists('sesi_approval_log');
    }
};

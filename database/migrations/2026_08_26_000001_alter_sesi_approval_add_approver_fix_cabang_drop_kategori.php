<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $connection = DB::connection('sqlsrv');

        // 1. Drop check constraint pada kategori_approval (SQL Server - need to drop constraint before column)
        $connection->statement("
            DECLARE @sql NVARCHAR(MAX) = '';
            SELECT @sql += 'ALTER TABLE sesi_approval DROP CONSTRAINT ' + QUOTENAME(name) + '; '
            FROM sys.check_constraints
            WHERE parent_object_id = OBJECT_ID(N'sesi_approval')
            AND definition LIKE '%kategori_approval%';
            IF @sql <> ''
                EXEC(@sql);
        ");

        Schema::connection('sqlsrv')->table('sesi_approval', function (Blueprint $table) {
            // 2. Change id_cabang from unsignedBigInteger to string
            $table->string('id_cabang', 50)->nullable()->change();

            // 3. Add id_approver (approver utama, tidak ada FK ke external lntrn_users)
            $table->unsignedBigInteger('id_approver')->nullable()->after('role_berwenang');

            // 4. Drop kategori_approval kolom (WH approval otomatis over_threshold, nggak perlu explicit)
            $table->dropColumn('kategori_approval');
        });
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->table('sesi_approval', function (Blueprint $table) {
            // Reverse: convert id_cabang back to unsignedBigInteger
            $table->unsignedBigInteger('id_cabang')->nullable()->change();

            // Remove id_approver
            $table->dropColumn('id_approver');

            // Re-add kategori_approval
            $table->enum('kategori_approval', ['normal', 'over_threshold'])->default('normal')->after('id_cabang');
        });
    }
};

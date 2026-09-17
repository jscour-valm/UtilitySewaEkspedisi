<?php

namespace App\Models;

use App\Models\Concerns\HasFlag;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * PengajuanSewaToAcb — Junction table (many-to-many)
 * Links pengajuan sewa to Transfer Antar Cabang (from ERP API)
 *
 * Reference-Only Approach:
 * - `id_to_acb` is a VARCHAR reference to ERP's tbTransferAntarCabang.No_
 * - NOT a foreign key (ERP is separate database)
 * - Application validates the reference at runtime
 * - Future switch to real ERP = zero migration (no schema change needed)
 */
class PengajuanSewaToAcb extends Model
{
    use HasFlag;

    protected $connection = 'sqlsrv';
    protected $table = 'sesi_pengajuan_sewa_to_acb';
    protected $primaryKey = 'id_pengajuan_sewa_to_acb';
    public $timestamps = true;

    protected $fillable = [
        'id_pengajuan_sewa',
        'id_to_acb',
        'flag',
    ];

    protected $casts = [
        'flag' => 'boolean',
    ];

    /**
     * Relasi ke PengajuanSewa
     */
    public function pengajuanSewa(): BelongsTo
    {
        return $this->belongsTo(PengajuanSewa::class, 'id_pengajuan_sewa', 'id_pengajuan_sewa');
    }

    /**
     * Scope: aktif (flag = true)
     */
    public function scopeAktif($query)
    {
        return $query->where('flag', true);
    }

    /**
     * Scope: deleted (flag = false)
     */
    public function scopeDeleted($query)
    {
        return $query->onlyInactive();
    }
}

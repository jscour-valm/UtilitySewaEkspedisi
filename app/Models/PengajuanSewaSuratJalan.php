<?php

namespace App\Models;

use App\Models\Concerns\HasFlag;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * PengajuanSewaSuratJalan — Junction table (many-to-many)
 * Links pengajuan sewa to Surat Jalan (from Quantum API)
 *
 * Reference-Only Approach:
 * - `id_surat_jalan` is a VARCHAR reference to Quantum's tbSJ.No_
 * - NOT a foreign key (Quantum is separate database)
 * - Application validates the reference at runtime
 * - Future switch to real Quantum = zero migration (no schema change needed)
 */
class PengajuanSewaSuratJalan extends Model
{
    use HasFlag;

    protected $connection = 'sqlsrv';
    protected $table = 'sesi_pengajuan_sewa_surat_jalan';
    protected $primaryKey = 'id_pengajuan_sewa_sj';
    public $timestamps = true;

    protected $fillable = [
        'id_pengajuan_sewa',
        'id_surat_jalan',
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

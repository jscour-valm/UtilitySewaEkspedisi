<?php

namespace App\Models;

use App\Models\Concerns\HasFlag;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Log histori biaya_per_unit (sesi_tarif_kiriman_rutin) — append-only. 1 baris = 1 kali harga
 * berubah, dari usulan harga yang disetujui WH atau edit tarif langsung oleh DCI.
 */
class RiwayatTarifKirimanRutin extends Model
{
    use HasFlag;

    protected $connection = 'sqlsrv';

    protected $table = 'sesi_riwayat_tarif_kiriman_rutin';

    protected $primaryKey = 'id_riwayat';

    public $timestamps = false;

    protected $fillable = [
        'id_tarif',
        'biaya_lama',
        'biaya_baru',
        'tanggal_perubahan',
        'diubah_oleh',
        'flag',
    ];

    protected $casts = [
        'biaya_lama' => 'decimal:2',
        'biaya_baru' => 'decimal:2',
        'tanggal_perubahan' => 'datetime',
        'flag' => 'boolean',
    ];

    public function tarif(): BelongsTo
    {
        return $this->belongsTo(TarifKirimanRutin::class, 'id_tarif', 'id_tarif');
    }

    /** Tanpa FK ke lntrn_users (external) — pola sama kayak submitted_by di tabel lain. */
    public function diubahOleh(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diubah_oleh', 'id');
    }
}

<?php

namespace App\Models;

use App\Models\Concerns\HasFlag;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Log histori harga_sewa (sesi_perusahaan_skill) — append-only, review mentor
 * item 11. 1 baris = 1 kali harga berubah, dari usulan Part B atau Kelola Tarif.
 */
class RiwayatHargaSewaTruk extends Model
{
    use HasFlag;

    protected $connection = 'sqlsrv';

    protected $table = 'sesi_riwayat_harga_sewa_truk';

    protected $primaryKey = 'id_riwayat';

    public $timestamps = false;

    protected $fillable = [
        'id_vendor_skill',
        'harga_lama',
        'harga_baru',
        'tanggal_perubahan',
        'diubah_oleh',
        'flag',
    ];

    protected $casts = [
        'harga_lama' => 'decimal:2',
        'harga_baru' => 'decimal:2',
        'tanggal_perubahan' => 'datetime',
        'flag' => 'boolean',
    ];

    public function vendorSkill(): BelongsTo
    {
        return $this->belongsTo(PerusahaanSkill::class, 'id_vendor_skill', 'id_vendor_skill');
    }

    /** Tanpa FK ke lntrn_users (external) — pola sama kayak submitted_by di tabel lain. */
    public function diubahOleh(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diubah_oleh', 'id');
    }
}

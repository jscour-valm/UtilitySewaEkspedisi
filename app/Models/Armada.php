<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Armada extends Model
{
    protected $connection = 'sqlsrv';
    protected $table = 'sesi_armada';
    protected $primaryKey = 'id_armada';
    public $timestamps = false;

    protected $fillable = [
        'id_perusahaan',
        'id_cabang',
        'ktp_supir',
        'sim_supir',
        'id_skill',
        'nama_kendaraan',
        'plat_nomor',
        'muatan_maksimal',
        'flag',
    ];

    protected $casts = [
        'flag' => 'boolean',
        'muatan_maksimal' => 'decimal:2',
        'updated_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    public function perusahaan(): BelongsTo
    {
        return $this->belongsTo(PerusahaanEkspedisi::class, 'id_perusahaan', 'id_perusahaan');
    }

    public function pengajuan(): HasMany
    {
        return $this->hasMany(PengajuanSewa::class, 'id_armada', 'id_armada');
    }
}

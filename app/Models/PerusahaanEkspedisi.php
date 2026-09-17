<?php

namespace App\Models;

use App\Models\Concerns\HasFlag;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PerusahaanEkspedisi extends Model
{
    use HasFlag;

    protected $connection = 'sqlsrv';
    protected $table = 'sesi_perusahaan_ekspedisi';
    protected $primaryKey = 'id_perusahaan';
    public $timestamps = true;

    protected $fillable = [
        'nama_perusahaan',
        'badan_usaha',
        'ktp_npwp',
        'no_telepon',
        'alamat_kantor',
        'identitas_owner',
        'flag',
    ];

    protected $casts = [
        'identitas_owner' => 'array',
        'flag' => 'boolean',
    ];

    public function kendaraan(): HasMany
    {
        return $this->hasMany(Kendaraan::class, 'id_perusahaan', 'id_perusahaan');
    }
}

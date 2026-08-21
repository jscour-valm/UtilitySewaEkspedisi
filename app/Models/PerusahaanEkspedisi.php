<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PerusahaanEkspedisi extends Model
{
    protected $connection = 'sqlsrv';
    protected $table = 'sesi_perusahaan_ekspedisi';
    protected $primaryKey = 'id_perusahaan';
    public $timestamps = true;

    protected $fillable = [
        'nama_perusahaan',
        'badan_usaha',
        'no_telepon',
        'alamat_kantor',
        'flag',
    ];

    protected $casts = [
        'flag' => 'boolean',
    ];

    public function armada(): HasMany
    {
        return $this->hasMany(Armada::class, 'id_perusahaan', 'id_perusahaan');
    }
}

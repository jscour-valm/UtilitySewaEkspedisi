<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JenisBiaya extends Model
{
    protected $connection = 'sqlsrv';
    protected $table = 'sesi_jenis_biaya';
    protected $primaryKey = 'id_jenis_biaya';
    public $timestamps = true;

    protected $fillable = [
        'nama_biaya',
        'flag',
    ];

    protected $casts = [
        'flag' => 'boolean',
    ];

    public function biayaTambahan(): HasMany
    {
        return $this->hasMany(BiayaTambahan::class, 'id_jenis_biaya', 'id_jenis_biaya');
    }
}

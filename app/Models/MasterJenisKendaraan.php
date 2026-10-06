<?php

namespace App\Models;

use App\Models\Concerns\HasFlag;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MasterJenisKendaraan extends Model
{
    use HasFlag;

    protected $connection = 'sqlsrv';

    protected $table = 'sesi_master_jenis_kendaraan';

    protected $primaryKey = 'id_jenis_kendaraan';

    public $timestamps = true;

    protected $fillable = [
        'nama_jenis',
        'muatan_maksimal_ton',
        'flag',
    ];

    protected $casts = [
        'flag' => 'boolean',
        'muatan_maksimal_ton' => 'decimal:2',
    ];

    public function kendaraan(): HasMany
    {
        return $this->hasMany(Kendaraan::class, 'id_jenis_kendaraan', 'id_jenis_kendaraan');
    }
}

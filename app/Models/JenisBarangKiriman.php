<?php

namespace App\Models;

use App\Models\Concerns\HasFlag;
use Illuminate\Database\Eloquent\Model;

class JenisBarangKiriman extends Model
{
    use HasFlag;

    protected $connection = 'sqlsrv';
    protected $table = 'sesi_jenis_barang_kiriman';
    protected $primaryKey = 'id_jenis_barang';
    public $timestamps = true;

    protected $fillable = [
        'nama_barang',
        'flag',
    ];

    protected $casts = [
        'flag' => 'boolean',
    ];

    /**
     * Relasi hasMany ke TarifKirimanRutin
     * Satu jenis barang bisa dipunyai banyak tarif (berbagai vendor)
     */
    public function tarifKirimanRutin()
    {
        return $this->hasMany(TarifKirimanRutin::class, 'id_jenis_barang');
    }

    /**
     * Scope untuk hanya ambil yang aktif (flag=true)
     */
    public function scopeAktif($query)
    {
        return $query->where('flag', true);
    }
}

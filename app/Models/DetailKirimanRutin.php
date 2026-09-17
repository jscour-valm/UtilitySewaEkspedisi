<?php

namespace App\Models;

use App\Models\Concerns\HasFlag;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DetailKirimanRutin extends Model
{
    use HasFlag;

    protected $connection = 'sqlsrv';
    protected $table = 'sesi_detail_kiriman_rutin';
    protected $primaryKey = 'id_detail_kiriman';
    public $timestamps = false; // only created_at, no updated_at
    protected $fillable = ['id_pengajuan_sewa', 'id_jenis_barang', 'id_tarif_kiriman_rutin', 'quantity', 'harga_satuan', 'subtotal', 'flag'];
    protected $casts = [
        'quantity' => 'decimal:2',
        'harga_satuan' => 'decimal:2',
        'subtotal' => 'decimal:2',
    ];

    public function pengajuanSewa(): BelongsTo
    {
        return $this->belongsTo(PengajuanSewa::class, 'id_pengajuan_sewa', 'id_pengajuan_sewa');
    }

    public function tarif(): BelongsTo
    {
        return $this->belongsTo(TarifKirimanRutin::class, 'id_tarif_kiriman_rutin', 'id_tarif');
    }

    public function jenisBarang(): BelongsTo
    {
        return $this->belongsTo(JenisBarangKiriman::class, 'id_jenis_barang', 'id_jenis_barang');
    }

    public function scopeAktif($query)
    {
        return $query->where('flag', true);
    }

    public function scopeDeleted($query)
    {
        return $query->onlyInactive();
    }
}

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

    protected $fillable = [
        'id_pengajuan_sewa', 'id_jenis_barang', 'id_tarif_kiriman_rutin', 'quantity', 'harga_satuan', 'subtotal', 'flag',
        'usulan_update_master', 'usulan_status', 'usulan_decided_by', 'usulan_decided_at',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'harga_satuan' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'usulan_update_master' => 'boolean',
        'usulan_decided_at' => 'datetime',
    ];

    public function pengajuanSewa(): BelongsTo
    {
        return $this->belongsTo(PengajuanSewa::class, 'id_pengajuan_sewa', 'id_pengajuan_sewa');
    }

    /** Part B — user (WM/WH) yang decide usulan baris ini. Tanpa FK ke lntrn_users. */
    public function usulanDecidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usulan_decided_by', 'id');
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

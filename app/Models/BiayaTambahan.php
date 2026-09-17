<?php

namespace App\Models;

use App\Models\Concerns\HasFlag;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BiayaTambahan extends Model
{
    use HasFlag;

    protected $connection = 'sqlsrv';
    protected $table = 'sesi_biaya_tambahan';
    protected $primaryKey = 'id_biaya_tambahan';
    public $timestamps = true;

    protected $fillable = [
        'id_pengajuan_sewa',
        'id_jenis_biaya',
        'jumlah',
        'flag',
    ];

    protected $casts = [
        'jumlah' => 'decimal:2',
        'flag' => 'boolean',
    ];

    public function pengajuan(): BelongsTo
    {
        return $this->belongsTo(PengajuanSewa::class, 'id_pengajuan_sewa', 'id_pengajuan_sewa');
    }

    public function jenisBiaya(): BelongsTo
    {
        return $this->belongsTo(JenisBiaya::class, 'id_jenis_biaya', 'id_jenis_biaya');
    }
}

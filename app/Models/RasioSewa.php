<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RasioSewa extends Model
{
    protected $connection = 'sqlsrv';
    protected $table = 'sesi_rasio_sewa';
    protected $primaryKey = 'id_rasio_sewa';
    public $timestamps = true;

    protected $fillable = [
        'persentase_maksimal',
        'effective_date',
        'end_date',
        'flag',
    ];

    protected $casts = [
        'effective_date' => 'date',
        'end_date' => 'date',
        'flag' => 'boolean',
    ];

    public static function aktif()
    {
        return static::where('flag', true)
            ->where(function ($q) {
                $q->whereNull('effective_date')->orWhere('effective_date', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('end_date')->orWhere('end_date', '>=', now());
            })
            ->orderByDesc('effective_date')
            ->first();
    }
}

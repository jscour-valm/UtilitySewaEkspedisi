<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserUtility extends Model
{
    protected $connection = 'sqlsrv';
    protected $table = 'lntrn_user_utility';
    public $timestamps = false;
    public $incrementing = false;
    protected $primaryKey = null;
    protected $guarded = [];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function utility()
    {
        return $this->belongsTo(Utility::class, 'utility_id', 'id');
    }

    public function scopeSesi($query)
    {
        return $query->whereHas('utility', fn ($q) => $q->where('prefix', 'sesi'));
    }
}

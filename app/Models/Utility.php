<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Utility extends Model
{
    protected $connection = 'sqlsrv';
    protected $table = 'lntrn_utilities';
    public $timestamps = true;
    protected $guarded = [];

    public function userUtilities()
    {
        return $this->hasMany(UserUtility::class, 'utility_id', 'id');
    }
}

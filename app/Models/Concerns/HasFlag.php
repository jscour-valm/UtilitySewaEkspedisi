<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

/**
 * Global scope: semua query default cuma ambil baris flag = 1 (aktif).
 * Baris flag = 0 dianggap soft-deleted — tidak pernah muncul di frontend
 * (list, detail, relasi/eager-load, agregat).
 *
 * Bypass:
 *   Model::withInactive()                       // semua baris, flag apa pun
 *   Model::onlyInactive()                       // cuma flag = 0
 *   Model::withoutGlobalScope('flag')           // low-level
 *
 * JANGAN pasang ke model tabel lntrn_* / eksternal IT (tanpa kolom flag),
 * dan ke User (pakai SoftDeletes/deleted_at sendiri).
 */
trait HasFlag
{
    public static function bootHasFlag(): void
    {
        static::addGlobalScope('flag', function (Builder $builder) {
            $builder->where($builder->getModel()->getTable() . '.flag', true);
        });
    }

    public function initializeHasFlag(): void
    {
        $this->casts['flag'] = 'boolean';
    }

    public function scopeWithInactive(Builder $query): Builder
    {
        return $query->withoutGlobalScope('flag');
    }

    public function scopeOnlyInactive(Builder $query): Builder
    {
        return $query->withoutGlobalScope('flag')
            ->where($this->getTable() . '.flag', false);
    }
}

<?php

namespace App\Models;

use App\Models\Concerns\HasFlag;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TarifKirimanRutin extends Model
{
    use HasFlag;

    protected $connection = 'sqlsrv';
    protected $table = 'sesi_tarif_kiriman_rutin';
    protected $primaryKey = 'id_tarif';
    protected $fillable = ['id_vendor_skill', 'id_jenis_barang', 'biaya_per_unit', 'flag'];
    protected $casts = ['biaya_per_unit' => 'decimal:2'];

    /**
     * Vendor+skill/area+cabang (sesi_perusahaan_skill) tempat tarif ini
     * nempel — lihat migration 2026_09_03_000007_create_sesi_tarif_kiriman_rutin_table.php.
     * Buat akses vendor-nya, pakai $tarif->vendorSkill->perusahaan.
     */
    public function vendorSkill(): BelongsTo
    {
        return $this->belongsTo(PerusahaanSkill::class, 'id_vendor_skill', 'id_vendor_skill');
    }

    public function jenisBarang(): BelongsTo
    {
        return $this->belongsTo(JenisBarangKiriman::class, 'id_jenis_barang');
    }

    public function detailKirimanRutin(): HasMany
    {
        return $this->hasMany(DetailKirimanRutin::class, 'id_tarif_kiriman_rutin', 'id_tarif');
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

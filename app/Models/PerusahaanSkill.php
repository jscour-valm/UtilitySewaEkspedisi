<?php

namespace App\Models;

use App\Models\Concerns\HasFlag;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

/**
 * sesi_perusahaan_skill — patokan harga_sewa truk per vendor + skill/area +
 * cabang. Menggantikan pola "rate-card" lama (baris sesi_unit_kendaraan
 * dengan plat_nomor_truk NULL, comma-CSV banyak skill jadi 1 baris) — di
 * sini 1 baris = 1 kombinasi vendor+skill+cabang.
 *
 * Nama skill (nama_skill) sengaja diambil via DB::table() langsung (bukan
 * relasi Eloquent) karena memang belum ada model Skill di kodebase manapun
 * — ikut pola yang sama dipakai FormatHelper::skillNames().
 */
class PerusahaanSkill extends Model
{
    use HasFlag;

    protected $connection = 'sqlsrv';
    protected $table = 'sesi_perusahaan_skill';
    protected $primaryKey = 'id_vendor_skill';
    public $timestamps = true;

    protected $fillable = [
        'id_perusahaan', 'id_skill', 'cabang_code', 'harga_sewa', 'flag',
        'revisi', 'tanggal_revisi', 'update_date_source',
    ];

    protected $casts = [
        'harga_sewa'          => 'decimal:2',
        'flag'                => 'boolean',
        'tanggal_revisi'      => 'date',
        'update_date_source'  => 'date',
    ];

    public function perusahaan(): BelongsTo
    {
        return $this->belongsTo(PerusahaanEkspedisi::class, 'id_perusahaan', 'id_perusahaan');
    }

    public function tarifKirimanRutin(): HasMany
    {
        return $this->hasMany(TarifKirimanRutin::class, 'id_vendor_skill', 'id_vendor_skill');
    }

    public function getNamaSkillAttribute(): ?string
    {
        return DB::connection('sqlsrv')
            ->table('sesi_master_skill')
            ->where('id_skill', $this->id_skill)
            ->value('nama_skill');
    }

    /**
     * "Nama Cabang" & "Kode Area" (kolom Excel Sewa Truk) TIDAK disimpan di
     * sini — keduanya di-join dari cabang_code ke sesi_master_cabang
     * (tabel eksternal IT, kolom Name & Area) pas ditampilkan, bukan
     * duplikasi data. Query per-baris (N+1) di sini — cuma dipakai di
     * halaman detail/edit; buat list gunakan JOIN langsung di query
     * controller (lihat TarifKirimanRutinController) biar 1 query aja.
     */
    public function getNamaCabangAttribute(): ?string
    {
        if (!$this->cabang_code) {
            return null;
        }
        return DB::connection('sqlsrv')
            ->table('sesi_master_cabang')
            ->where('Code', $this->cabang_code)
            ->value('Name');
    }

    public function getKodeAreaAttribute(): ?string
    {
        if (!$this->cabang_code) {
            return null;
        }
        return DB::connection('sqlsrv')
            ->table('sesi_master_cabang')
            ->where('Code', $this->cabang_code)
            ->value('Area');
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

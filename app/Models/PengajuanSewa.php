<?php

namespace App\Models;

use App\Models\Concerns\HasFlag;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PengajuanSewa extends Model
{
    use HasFlag;

    protected $connection = 'sqlsrv';

    protected $table = 'sesi_pengajuan_sewa';

    protected $primaryKey = 'id_pengajuan_sewa';

    public $timestamps = true;

    protected $fillable = [
        'id_kendaraan',
        'id_perusahaan_ekspedisi',
        'id_cabang',
        'id_cabang_asal',
        'tanggal_pengiriman',
        'value_muatan',
        'harga_sewa',
        'rasio_sewa',
        'kategori_approval',
        'alur_approval',
        'tujuan_penyewaan',
        'jenis_pengajuan',
        'id_skill',
        'status_pengajuan',
        'current_approval_rule_id',
        'catatan_pengajuan',
        'submitted_by',
        'submitted_at',
        'flag',
        'kategori_toko',
        'usulan_harga_sewa',
        'usulan_status',
        'usulan_decided_by',
        'usulan_decided_at',
    ];

    protected $casts = [
        'tanggal_pengiriman' => 'date',
        'value_muatan' => 'decimal:2',
        'harga_sewa' => 'decimal:2',
        'rasio_sewa' => 'decimal:2',
        'flag' => 'boolean',
        'submitted_at' => 'datetime',
        'usulan_harga_sewa' => 'boolean',
        'usulan_decided_at' => 'datetime',
    ];

    public function kendaraan(): BelongsTo
    {
        return $this->belongsTo(Kendaraan::class, 'id_kendaraan', 'id_kendaraan');
    }

    public function pengaju(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by', 'id');
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by', 'id');
    }

    public function biayaTambahan(): HasMany
    {
        return $this->hasMany(BiayaTambahan::class, 'id_pengajuan_sewa', 'id_pengajuan_sewa');
    }

    public function approvalLogs(): HasMany
    {
        return $this->hasMany(ApprovalLog::class, 'id_pengajuan_sewa', 'id_pengajuan_sewa');
    }

    /**
     * Relasi ke Surat Jalan (junction table)
     * Reference-Only: id_surat_jalan adalah reference ke Quantum (bukan FK)
     */
    public function suratJalans(): HasMany
    {
        return $this->hasMany(PengajuanSewaSuratJalan::class, 'id_pengajuan_sewa', 'id_pengajuan_sewa');
    }

    /**
     * Relasi ke Transfer Antar Cabang (junction table)
     * Reference-Only: id_to_acb adalah reference ke ERP (bukan FK)
     */
    public function transferAntarCabang(): HasMany
    {
        return $this->hasMany(PengajuanSewaToAcb::class, 'id_pengajuan_sewa', 'id_pengajuan_sewa');
    }

    /**
     * Relasi ke Vendor (Perusahaan Ekspedisi) untuk Kiriman Rutin
     */
    public function perusahaanEkspedisi(): BelongsTo
    {
        return $this->belongsTo(PerusahaanEkspedisi::class, 'id_perusahaan_ekspedisi', 'id_perusahaan');
    }

    /**
     * Relasi ke Detail Kiriman Rutin (line items)
     */
    public function detailKirimanRutin(): HasMany
    {
        return $this->hasMany(DetailKirimanRutin::class, 'id_pengajuan_sewa', 'id_pengajuan_sewa');
    }

    /**
     * User (WM/WH) yang decide usulan harga master (Sewa Truk) di pengajuan ini.
     * Tanpa FK constraint ke lntrn_users (external), pola sama kayak submittedBy().
     */
    public function usulanDecidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usulan_decided_by', 'id');
    }

    public function hitungRasio()
    {
        if (! $this->value_muatan || $this->value_muatan == 0) {
            return null;
        }
        $totalBiaya = $this->harga_sewa + $this->biayaTambahan()->sum('jumlah');

        return round(($totalBiaya / $this->value_muatan) * 100, 2);
    }

    /**
     * Urutan approver sesuai spesifikasi mentor (30 Sept 2026): WM selalu validasi,
     * WC kalau PAC, WH kalau butuh WH (rasio sewa truk >2,5% atau area baru).
     * PAC + butuh WH = 3 tingkat WM → WC → WH.
     */
    public static function hitungAlur(bool $isPac, bool $butuhWh): string
    {
        return implode(',', array_filter(['WM', $isPac ? 'WC' : null, $butuhWh ? 'WH' : null]));
    }

    /** @return string[] mis. ['WM','WC','WH']. Pengajuan lama tanpa alur diturunkan dari kategori. */
    public function alurApproval(): array
    {
        return self::parseAlur($this->alur_approval, $this->kategori_approval);
    }

    public static function parseAlur(?string $alur, ?string $kategori): array
    {
        if ($alur) {
            return array_values(array_filter(array_map('trim', explode(',', $alur))));
        }

        return $kategori === 'over_threshold' ? ['WM', 'WH'] : ['WM'];
    }

    /**
     * KG pengaju cuma boleh edit selama masih menunggu validasi WM (tabel wewenang,
     * 2 Okt 2026). Setelah WM validasi, pengajuan terkunci.
     */
    public function bisaDieditPengaju(): bool
    {
        return strtolower((string) $this->status_pengajuan) === 'pending'
            && $this->approverBerikutnya() === 'WM';
    }

    /**
     * Peran yang sudah approve SEJAK pengajuan terakhir di-submit/di-edit. Edit setelah
     * divalidasi sekarang diblokir (bisaDieditPengaju), filter submitted_at tetap
     * dipertahankan untuk data lama.
     */
    public function peranSudahApprove(): array
    {
        return $this->approvalLogs
            ->filter(fn ($log) => strtolower($log->status) === 'approved')
            ->filter(fn ($log) => ! $this->submitted_at || ! $log->decided_at || $log->decided_at->gte($this->submitted_at))
            ->map(fn ($log) => $log->peran)
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /** Peran yang giliran bertindak sekarang, atau null kalau sudah final/ditolak. */
    public function approverBerikutnya(): ?string
    {
        return self::approverBerikutnyaDari($this->status_pengajuan, $this->alurApproval(), $this->peranSudahApprove());
    }

    /** Versi statis — dipakai tabel dashboard yang query mentah (tanpa model). */
    public static function approverBerikutnyaDari(?string $status, array $alur, array $peranSudahApprove): ?string
    {
        if (strtolower((string) $status) !== 'pending') {
            return null;
        }

        foreach ($alur as $peran) {
            if (! in_array($peran, $peranSudahApprove, true)) {
                return $peran;
            }
        }

        return null;
    }

    public function sudahDisetujuiOleh(string $peran): bool
    {
        return in_array($peran, $this->peranSudahApprove(), true);
    }

    /**
     * Part B — usulan harga master diputuskan di tier TERAKHIR alur pengajuan
     * (atau WH). Sementara sampai Fase 4 memisahkan usulan harga jadi proses sendiri.
     */
    public function isUsulanTerminalTierFor(string $role): bool
    {
        $alur = $this->alurApproval();

        return $role === 'WH' || $role === end($alur);
    }
}

<?php

namespace App\Models\Concerns;

use App\Models\AturanAlur;
use App\Models\PengajuanSewa;
use Illuminate\Support\Facades\Schema;

/**
 * Status proses persetujuan master (vendor baru, usulan harga): KG mengajukan, lalu tiap
 * peran di alur memutuskan bergiliran. Alur diambil dari pengaturan DCI saat diajukan dan
 * dikunci di kolom `alur_approval`; `giliran_approval` = peran yang sedang ditunggu.
 * Status: menunggu_validasi (tahap pertama) → menunggu_approval (tahap lanjutan) → approved;
 * penolakan di tahap mana pun langsung final (rejected).
 * Baris tanpa `alur_approval` (dibuat sebelum kolomnya ada) beralur WM → WH.
 * Model pemakai menentukan nama kolom status dan proses masternya.
 */
trait HasStatusPersetujuan
{
    public const STATUS_MENUNGGU_VALIDASI = 'menunggu_validasi';

    public const STATUS_MENUNGGU_APPROVAL = 'menunggu_approval';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    /** @var array<string, bool> nama tabel => kolom alur sudah ada */
    private static array $kolomAlurAda = [];

    abstract public function kolomStatusPersetujuan(): string;

    /** AturanAlur::MASTER_VENDOR atau AturanAlur::MASTER_HARGA. */
    abstract public function prosesMaster(): string;

    public function statusPersetujuan(): ?string
    {
        return $this->getAttribute($this->kolomStatusPersetujuan());
    }

    /** @return string[] alur yang dikunci untuk baris ini, mis. ['WM', 'WH'] */
    public function alurPersetujuan(): array
    {
        $alur = AturanAlur::rapikan($this->getAttribute('alur_approval'));

        return explode(',', $alur ?? 'WM,WH');
    }

    /** Peran yang sedang giliran memutuskan, null kalau sudah final. */
    public function giliranPersetujuan(): ?string
    {
        $status = $this->statusPersetujuan();
        if (! in_array($status, [self::STATUS_MENUNGGU_VALIDASI, self::STATUS_MENUNGGU_APPROVAL], true)) {
            return null;
        }

        $giliran = $this->getAttribute('giliran_approval');
        if ($giliran) {
            return $giliran;
        }

        $alur = $this->alurPersetujuan();

        return $status === self::STATUS_MENUNGGU_VALIDASI ? $alur[0] : ($alur[1] ?? $alur[0]);
    }

    /** Peran sesudah $peran di alur baris ini, null kalau $peran tahap terakhir. */
    public function peranSesudah(string $peran): ?string
    {
        $alur = $this->alurPersetujuan();
        $posisi = array_search($peran, $alur, true);

        return $posisi === false ? null : ($alur[$posisi + 1] ?? null);
    }

    /**
     * Nilai kolom saat proses dimulai (diajukan / diajukan ulang): status tahap pertama dan,
     * kalau kolomnya sudah ada, alur dari pengaturan DCI + peran tahap pertama.
     *
     * @return array<string, ?string>
     */
    public function awalPersetujuan(): array
    {
        $kolom = [$this->kolomStatusPersetujuan() => self::STATUS_MENUNGGU_VALIDASI];

        if (self::kolomAlurAda()) {
            $alur = AturanAlur::alurMaster($this->prosesMaster());
            $kolom['alur_approval'] = $alur;
            $kolom['giliran_approval'] = explode(',', $alur)[0];
        }

        return $kolom;
    }

    /** Kolom alur_approval & giliran_approval sudah dibuat (migration sudah jalan). */
    public static function kolomAlurAda(): bool
    {
        $model = new static;
        $tabel = $model->getTable();

        if (! isset(self::$kolomAlurAda[$tabel])) {
            try {
                self::$kolomAlurAda[$tabel] = ! app()->runningUnitTests()
                    && Schema::connection($model->getConnectionName())->hasColumn($tabel, 'alur_approval');
            } catch (\Throwable) {
                self::$kolomAlurAda[$tabel] = false;
            }
        }

        return self::$kolomAlurAda[$tabel];
    }

    /** Belum diputuskan final (masih menunggu salah satu peran). */
    public function sedangBerjalan(): bool
    {
        return $this->giliranPersetujuan() !== null;
    }

    public function sudahDisetujui(): bool
    {
        return $this->statusPersetujuan() === self::STATUS_APPROVED;
    }

    public function labelStatusPersetujuan(): string
    {
        $giliran = $this->giliranPersetujuan();
        if ($giliran !== null) {
            return AturanAlur::labelMenunggu($giliran);
        }

        return match ($this->statusPersetujuan()) {
            self::STATUS_APPROVED => 'Disetujui',
            self::STATUS_REJECTED => 'Ditolak',
            default => '-',
        };
    }

    /** Kelas Tailwind badge status persetujuan. Status menunggu = warna badge Pending pengajuan sewa. */
    public function warnaStatusPersetujuan(): string
    {
        return match ($this->statusPersetujuan()) {
            self::STATUS_APPROVED => 'bg-avian-green-light text-avian-green',
            self::STATUS_REJECTED => 'bg-red-50 text-red-600',
            default => PengajuanSewa::BADGE_STATUS['pending']['kelas'],
        };
    }

    public function scopeSedangBerjalan($query)
    {
        return $query->whereIn($this->kolomStatusPersetujuan(), [self::STATUS_MENUNGGU_VALIDASI, self::STATUS_MENUNGGU_APPROVAL]);
    }
}

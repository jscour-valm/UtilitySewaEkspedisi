<?php

namespace App\Mail;

use App\Helpers\FormatHelper;
use App\Models\PerusahaanEkspedisi;
use App\Models\UsulanHarga;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Support\Facades\DB;

/**
 * Email proses persetujuan master: vendor baru atau usulan harga.
 * $aksi = diajukan | diajukan_ulang | divalidasi | disetujui | ditolak.
 */
class NotifikasiMasterMail extends Mailable
{
    /** Tema markdown hijau: resources/views/vendor/mail/html/themes/hijau.css */
    public $theme = 'hijau';

    public function __construct(
        public Model $objek,
        public string $aksi,
        public string $url,
        public ?string $alasanPenolakan = null,
    ) {}

    private function isVendor(): bool
    {
        return $this->objek instanceof PerusahaanEkspedisi;
    }

    private function vendor(): ?PerusahaanEkspedisi
    {
        return $this->isVendor()
            ? $this->objek
            : PerusahaanEkspedisi::withInactive()->find($this->objek->id_perusahaan);
    }

    private function namaVendor(): string
    {
        $v = $this->vendor();
        if (! $v) {
            return '-';
        }

        return trim((($v->badan_usaha && $v->badan_usaha !== '-') ? $v->badan_usaha.' ' : '').$v->nama_perusahaan);
    }

    private function namaArea(): string
    {
        return $this->isVendor() ? '-' : (FormatHelper::skillNames((string) $this->objek->id_skill)[0] ?? '-');
    }

    private function namaBarang(): ?string
    {
        if ($this->isVendor() || ! $this->objek->id_jenis_barang) {
            return null;
        }

        return DB::connection('sqlsrv')->table('sesi_jenis_barang_kiriman')
            ->where('id_jenis_barang', $this->objek->id_jenis_barang)
            ->value('nama_barang');
    }

    private function cabang(): ?string
    {
        return $this->objek->id_cabang_pengaju;
    }

    public function envelope(): Envelope
    {
        $awalan = match ($this->aksi) {
            'diajukan' => '[Menunggu Validasi WM] ',
            'diajukan_ulang' => '[Diajukan Ulang - Menunggu Validasi WM] ',
            'divalidasi' => '[Divalidasi WM - Menunggu Approval WH] ',
            'disetujui' => '[APPROVED] ',
            'ditolak' => '[REJECTED] ',
        };

        if ($this->isVendor()) {
            $inti = 'Vendor Baru '.$this->namaVendor().' - Cabang '.$this->cabang();
        } elseif ($this->objek->jenis === UsulanHarga::JENIS_SEWA_TRUK) {
            $inti = 'Usulan Harga Sewa Truk '.$this->namaVendor().' - '.$this->namaArea().' ('.$this->objek->cabang_code.')';
        } else {
            $inti = 'Usulan Tarif Kiriman Rutin '.$this->namaVendor().' - '.($this->namaBarang() ?? '-').', '.$this->namaArea().' ('.$this->objek->cabang_code.')';
        }

        return new Envelope(subject: $awalan.$inti);
    }

    public function content(): Content
    {
        $apa = $this->isVendor() ? 'Pengajuan vendor baru' : 'Usulan perubahan harga master';
        $kode = $this->cabang();

        [$judul, $intro] = match ($this->aksi) {
            'diajukan' => ["$apa masuk", "$apa dari cabang $kode perlu divalidasi WM."],
            'diajukan_ulang' => ["$apa diajukan ulang", "$apa dari cabang $kode telah diperbaiki dan perlu divalidasi kembali oleh WM."],
            'divalidasi' => ["$apa menunggu approval WH", "$apa dari cabang $kode sudah divalidasi WM dan kini menunggu approval WH."],
            'disetujui' => ["$apa disetujui", $this->isVendor()
                ? "Vendor baru dari cabang $kode telah disetujui WH dan bisa dipakai di pengajuan sewa."
                : "Usulan dari cabang $kode telah disetujui WH. Harga master sudah diperbarui."],
            'ditolak' => ["$apa ditolak", "$apa dari cabang $kode ditolak dengan alasan:"],
        };

        $riwayat = $this->objek->approvalLogs()->with('approver')->get();

        $hargaLama = $this->isVendor() ? null : $this->objek->harga_lama;
        $hargaUsulan = $this->isVendor() ? null : (float) $this->objek->harga_usulan;

        return new Content(
            markdown: 'emails.notifikasi-master',
            with: [
                'judul' => $judul,
                'intro' => $intro,
                'isVendor' => $this->isVendor(),
                'vendor' => $this->vendor(),
                'namaVendor' => $this->namaVendor(),
                'namaCabang' => DB::connection('sqlsrv')->table('sesi_master_cabang')->where('Code', $kode)->value('Name') ?: $kode,
                'area' => $this->namaArea(),
                'barang' => $this->namaBarang(),
                'hargaLama' => $hargaLama !== null ? (float) $hargaLama : null,
                'hargaUsulan' => $hargaUsulan,
                'riwayat' => $riwayat,
            ],
        );
    }
}

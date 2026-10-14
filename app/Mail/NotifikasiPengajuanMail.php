<?php

namespace App\Mail;

use App\Helpers\FormatHelper;
use App\Models\AturanAlur;
use App\Models\PengajuanSewa;
use App\Models\RasioSewa;
use Carbon\Carbon;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Support\Facades\DB;

/**
 * Satu Mailable untuk semua event notifikasi pengajuan (beda subjek/sapaan/isi per $tipe):
 * baru | menunggu_approval | approved | rejected. $ulang = pengajuan diajukan ulang.
 * $peranBerikutnya (tipe menunggu_approval) = WC atau WH yang sekarang giliran.
 */
class NotifikasiPengajuanMail extends Mailable
{
    public function __construct(
        public PengajuanSewa $pengajuan,
        public string $tipe,
        public string $url,
        public ?string $alasanPenolakan = null,
        public ?array $dokumen = null,
        public bool $ulang = false,
        public ?string $peranBerikutnya = null,
    ) {}

    /** "Divalidasi WM" / "Disetujui WC" — langkah sebelum $peranBerikutnya di alur pengajuan. */
    private function langkahSebelumnya(): string
    {
        $alur = $this->pengajuan->alurApproval();
        $idx = array_search($this->peranBerikutnya, $alur, true);
        $sebelum = ($idx !== false && $idx > 0) ? $alur[$idx - 1] : ($alur[0] ?? 'WM');

        return AturanAlur::labelSelesai($sebelum);
    }

    /** Peran tahap pertama alur pengajuan. */
    private function tahapPertama(): string
    {
        return $this->pengajuan->alurApproval()[0] ?? 'WM';
    }

    private function namaVendor(): string
    {
        $p = $this->pengajuan;
        $perusahaan = $p->jenis_pengajuan === 'sewa_truk' ? $p->kendaraan?->perusahaan : $p->perusahaanEkspedisi;

        return $perusahaan?->nama_perusahaan ?? '-';
    }

    public function envelope(): Envelope
    {
        $p = $this->pengajuan;
        $tgl = Carbon::parse($p->tanggal_pengiriman)->format('d/m/Y');

        $inti = $p->jenis_pengajuan === 'sewa_truk'
            ? 'Pengajuan Sewa Truk '.($p->kendaraan?->plat_nomor_truk ?: $this->namaVendor())." Tgl $tgl"
            : 'Pengajuan Kiriman Rutin '.$this->namaVendor()." Tgl $tgl";

        $awalan = match ($this->tipe) {
            'baru' => ($this->ulang ? '[Diajukan Ulang - ' : '[').AturanAlur::labelMenunggu($this->tahapPertama()).'] ',
            'menunggu_approval' => '['.$this->langkahSebelumnya()." - Menunggu Approval {$this->peranBerikutnya}] ",
            'approved' => '[APPROVED] ',
            'rejected' => '[REJECTED] ',
            'dibatalkan' => '[DIBATALKAN] ',
        };

        return new Envelope(subject: $awalan.$inti);
    }

    public function content(): Content
    {
        $p = $this->pengajuan->loadMissing([
            'kendaraan.perusahaan',
            'perusahaanEkspedisi',
            'submittedBy',
            'biayaTambahan.jenisBiaya',
            'detailKirimanRutin.jenisBarang',
            'approvalLogs.approver',
            'approvalLogs.approval',
        ]);

        $biaya = $p->biayaTambahan->map(fn ($b) => [
            'nama' => $b->jenisBiaya?->nama_biaya ?? '-',
            'jumlah' => (float) $b->jumlah,
        ]);
        $totalBiayaTambahan = (float) $biaya->sum('jumlah');

        $kode = $p->id_cabang;
        [$judul, $intro] = match ($this->tipe) {
            'baru' => $this->ulang
                ? ['Pengajuan diajukan ulang', "Pengajuan sewa dari cabang $kode telah diperbaiki dan diajukan ulang, dan kembali ".lcfirst(AturanAlur::labelMenunggu($this->tahapPertama())).'.']
                : ['Pengajuan baru masuk', "Ada pengajuan sewa baru dari cabang $kode yang ".lcfirst(AturanAlur::labelMenunggu($this->tahapPertama())).'.'],
            'menunggu_approval' => [
                "Pengajuan menunggu approval {$this->peranBerikutnya}",
                "Pengajuan sewa dari cabang $kode sudah ".lcfirst($this->langkahSebelumnya())." dan kini menunggu approval {$this->peranBerikutnya}.",
            ],
            'approved' => count($p->alurApproval()) === 1
                ? ['Pengajuan disetujui', "Pengajuan sewa dari cabang $kode telah ".lcfirst(AturanAlur::labelSelesai($this->tahapPertama())).' (keputusan final).']
                : ['Pengajuan disetujui', "Pengajuan sewa dari cabang $kode telah disetujui."],
            'rejected' => ['Pengajuan ditolak', "Pengajuan sewa dari cabang $kode ditolak dengan alasan:"],
            'dibatalkan' => ['Pengajuan dibatalkan', "Pengajuan sewa dari cabang $kode dibatalkan oleh pengaju dengan alasan:"],
        };

        $rasioSetting = RasioSewa::aktif();

        return new Content(
            view: 'emails.notifikasi-pengajuan',
            with: [
                'judul' => $judul,
                'intro' => $intro,
                'namaCabang' => DB::connection('sqlsrv')->table('sesi_master_cabang')->where('Code', $p->id_cabang)->value('Name') ?: $p->id_cabang,
                'vendor' => $this->namaVendor(),
                'badanUsaha' => ($p->jenis_pengajuan === 'sewa_truk' ? $p->kendaraan?->perusahaan : $p->perusahaanEkspedisi)?->badan_usaha,
                'area' => implode(', ', FormatHelper::skillNames($p->id_skill ?? '')) ?: '-',
                'isRutin' => $p->jenis_pengajuan === 'pengiriman_rutin',
                'biaya' => $biaya,
                'totalBiayaTambahan' => $totalBiayaTambahan,
                'totalBiayaSewa' => (float) $p->harga_sewa + $totalBiayaTambahan,
                'ambangRasio' => (float) ($rasioSetting?->persentase_maksimal ?? RasioSewa::BATAS_DEFAULT),
                'dokumen' => $this->dokumen,
                'riwayat' => $p->approvalLogs->sortBy('decided_at')->values(),
            ],
        );
    }
}

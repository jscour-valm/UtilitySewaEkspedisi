<?php

namespace App\Services;

use App\Models\PengajuanSewa;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Lampiran Excel "Daftar SJ" (atau TO-ACB) yang dipilih di pengajuan.
 * File dibuat SEKALI saat email pengajuan baru & disimpan sebagai snapshot
 * (storage/app/lampiran-sj), karena SJ bisa hilang dari view "Belum Kirim" setelah
 * dikirim — email hasil final tetap melampirkan file yang sama.
 */
class LampiranSjService
{
    public function __construct(private DocumentLinkageService $dokumen) {}

    private function relPath(PengajuanSewa $p): string
    {
        return 'lampiran-sj/pengajuan-'.$p->id_pengajuan_sewa.'.xlsx';
    }

    private function ringkasanPath(PengajuanSewa $p): string
    {
        return 'lampiran-sj/pengajuan-'.$p->id_pengajuan_sewa.'.json';
    }

    /**
     * Agregat dokumen (jumlah, toko, tonase, nilai) untuk isi email. Pakai snapshot JSON kalau ada
     * (SJ mungkin sudah hilang dari view sumber), else hitung langsung. Null kalau tak ada dokumen.
     */
    public function ringkasan(PengajuanSewa $p): ?array
    {
        $disk = Storage::disk('local');
        if ($disk->exists($this->ringkasanPath($p))) {
            return json_decode($disk->get($this->ringkasanPath($p)), true);
        }

        $detail = $this->dokumen->getDetailDokumen($p);

        return $detail['jumlah_dokumen'] === 0 ? null : collect($detail)->except('items')->all();
    }

    /** Path absolut file tersimpan, atau buat baru kalau belum ada. Null kalau tidak ada dokumen / gagal. */
    public function ambil(PengajuanSewa $p): ?string
    {
        if (Storage::disk('local')->exists($this->relPath($p))) {
            return Storage::disk('local')->path($this->relPath($p));
        }

        return $this->buat($p);
    }

    /** Buat (atau timpa) snapshot file. Dipanggil saat pengajuan baru / diajukan ulang. */
    public function buat(PengajuanSewa $p): ?string
    {
        try {
            $detail = $this->dokumen->getDetailDokumen($p);
            if ($detail['jumlah_dokumen'] === 0) {
                return null;
            }

            $isAcb = $detail['tipe'] === 'TO-ACB';

            $sheet = ($book = new Spreadsheet)->getActiveSheet();
            $sheet->setTitle($isAcb ? 'Daftar TO-ACB' : 'Daftar SJ');

            $sheet->setCellValue('A1', 'Lampiran Pengajuan Sewa #'.$p->id_pengajuan_sewa);
            $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(13);
            $sheet->setCellValue('A2', 'Cabang: '.$p->id_cabang.'   |   Tgl Pengiriman: '.Carbon::parse($p->tanggal_pengiriman)->format('d/m/Y'));

            $headers = $isAcb
                ? ['No', 'No TO-ACB', 'Last Shipment No', 'Berat (kg)', 'Nilai (Rp)']
                : ['No', 'No SJ', 'Customer No', 'Nama Customer', 'Alamat', 'Kota', 'Berat (kg)', 'Nilai (Rp)'];
            $lastCol = chr(ord('A') + count($headers) - 1);
            $beratCol = $isAcb ? 'D' : 'G';
            $nilaiCol = $isAcb ? 'E' : 'H';

            $headerRow = 4;
            foreach ($headers as $i => $h) {
                $sheet->setCellValue(chr(ord('A') + $i).$headerRow, $h);
            }
            $sheet->getStyle("A{$headerRow}:{$lastCol}{$headerRow}")->applyFromArray([
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1F7A3D']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ]);

            $row = $headerRow + 1;
            foreach ($detail['items'] as $i => $it) {
                $sheet->setCellValue("A{$row}", $i + 1);
                $sheet->setCellValueExplicit("B{$row}", (string) $it['nomor'], DataType::TYPE_STRING);
                if ($isAcb) {
                    $sheet->setCellValue("C{$row}", $it['last_shipment_no'] ?? '-');
                } else {
                    $sheet->setCellValueExplicit("C{$row}", (string) ($it['customer_no'] ?? '-'), DataType::TYPE_STRING);
                    $sheet->setCellValue("D{$row}", $it['customer'] ?? '-');
                    $sheet->setCellValue("E{$row}", $it['alamat'] ?? '-');
                    $sheet->setCellValue("F{$row}", $it['kota'] ?? '-');
                }
                $sheet->setCellValue("{$beratCol}{$row}", $it['berat']);
                $sheet->setCellValue("{$nilaiCol}{$row}", $it['nilai']);
                $row++;
            }

            $first = $headerRow + 1;
            $last = $row - 1;

            // Baris total
            $sheet->setCellValue("A{$row}", 'TOTAL');
            $sheet->setCellValue("B{$row}", $detail['jumlah_dokumen'].($isAcb ? ' TO-ACB' : ' SJ'));
            if (! $isAcb) {
                $sheet->setCellValue("C{$row}", $detail['jumlah_toko'].' toko');
            }
            $sheet->setCellValue("{$beratCol}{$row}", "=SUM({$beratCol}{$first}:{$beratCol}{$last})");
            $sheet->setCellValue("{$nilaiCol}{$row}", "=SUM({$nilaiCol}{$first}:{$nilaiCol}{$last})");
            $sheet->getStyle("A{$row}:{$lastCol}{$row}")->getFont()->setBold(true);
            $sheet->getStyle("A{$row}:{$lastCol}{$row}")->getBorders()->getTop()->setBorderStyle(Border::BORDER_THIN);

            $sheet->getStyle("{$beratCol}{$first}:{$beratCol}{$row}")->getNumberFormat()->setFormatCode('#,##0.00');
            $sheet->getStyle("{$nilaiCol}{$first}:{$nilaiCol}{$row}")->getNumberFormat()->setFormatCode('#,##0');

            foreach (range('A', $lastCol) as $col) {
                $sheet->getColumnDimension($col)->setAutoSize(true);
            }
            $sheet->freezePane('A'.($headerRow + 1));

            Storage::disk('local')->makeDirectory('lampiran-sj');
            $path = Storage::disk('local')->path($this->relPath($p));
            (new Xlsx($book))->save($path);
            Storage::disk('local')->put($this->ringkasanPath($p), json_encode(collect($detail)->except('items')->all()));

            return $path;
        } catch (\Throwable $e) {
            Log::warning("Gagal membuat lampiran SJ pengajuan #{$p->id_pengajuan_sewa}: ".$e->getMessage());

            return null;
        }
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\AturanAlur;
use App\Models\AturanEmail;
use App\Models\PengajuanSewa;
use App\Models\RasioSewa;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

/**
 * Pengaturan DCI, satu halaman per pengaturan: batas rasio sewa, alur approval (pengajuan sewa,
 * vendor baru, usulan harga), penerima email per kejadian. Perubahan berlaku untuk pengajuan / email berikutnya; alur
 * pengajuan yang sudah diajukan tetap.
 */
class PengaturanController extends Controller
{
    public function index()
    {
        return redirect()->route('pengaturan.rasio');
    }

    public function rasio()
    {
        return view('pages.pengaturan.rasio', [
            'rasioAktif' => RasioSewa::aktif(),
            'riwayatRasio' => RasioSewa::withInactive()->orderByDesc('effective_date')->orderByDesc('id_rasio_sewa')->get(),
        ]);
    }

    public function alur()
    {
        return view('pages.pengaturan.alur', [
            'tabelSiap' => Schema::connection('sqlsrv')->hasTable('sesi_aturan_alur'),
            'alur' => AturanAlur::semua(),
            'alurMaster' => AturanAlur::semuaMaster(),
            'batas' => (float) (RasioSewa::aktif()?->persentase_maksimal ?? RasioSewa::BATAS_DEFAULT),
        ]);
    }

    public function email()
    {
        return view('pages.pengaturan.email', [
            'tabelSiap' => Schema::connection('sqlsrv')->hasTable('sesi_aturan_email'),
            'email' => AturanEmail::semua(),
        ]);
    }

    /**
     * Isian per baris = daftar tahap (`alur[kunci][0..2]`); tahap kosong dirapatkan.
     * Baris proses master memakai kunci "vendor_baru|-|-" / "usulan_harga|-|-".
     */
    public function simpanAlur(Request $request)
    {
        $data = $request->validate([
            'alur' => 'required|array',
            'alur.*' => 'array|max:'.AturanAlur::TAHAP_MAKS,
            'alur.*.*' => ['nullable', Rule::in(AturanAlur::PERAN)],
        ]);

        $label = [];
        foreach (array_keys(AturanAlur::ALUR_BAWAAN) as $kunci) {
            [$jenis, $tujuan, $rasio] = explode('|', $kunci);
            $label[$kunci] = (PengajuanSewa::JENIS[$jenis] ?? $jenis)." · $tujuan".match ($rasio) {
                'atas' => ' · di atas batas', 'bawah' => ' · di bawah batas', default => ''
            };
        }
        foreach (AturanAlur::LABEL_MASTER as $proses => $nama) {
            $label[AturanAlur::kunci($proses, '-', '-')] = $nama;
        }

        $simpan = [];
        foreach ($label as $kunci => $nama) {
            if (! isset($data['alur'][$kunci])) {
                continue;
            }
            $tahap = array_filter($data['alur'][$kunci]);
            $alur = AturanAlur::rapikan($tahap);
            if ($alur === null) {
                $pesan = $tahap === [] ? 'minimal satu tahap harus diisi' : 'satu peran hanya boleh dipakai sekali';

                return back()->withInput()->withErrors(['alur' => "Alur \"$nama\": $pesan."]);
            }
            $simpan[$kunci] = $alur;
        }

        DB::connection('sqlsrv')->transaction(function () use ($simpan) {
            foreach ($simpan as $kunci => $alur) {
                [$jenis, $tujuan, $rasio] = explode('|', $kunci);
                AturanAlur::updateOrCreate(
                    ['jenis_pengajuan' => $jenis, 'tujuan_penyewaan' => $tujuan, 'rasio' => $rasio],
                    ['alur' => $alur, 'updated_by' => auth()->id()],
                );
            }
        });
        AturanAlur::lupakanCache();

        return redirect()->route('pengaturan.alur')->with('success', 'Alur approval disimpan. Berlaku untuk yang diajukan setelah ini.');
    }

    public function simpanEmail(Request $request)
    {
        $data = $request->validate([
            'email' => 'required|array',
            'email.*' => 'array',
            'email.*.*' => 'nullable|in:to,cc',
        ]);

        $baris = [];
        $tanpaTo = [];
        foreach (AturanEmail::KEJADIAN as $kejadian => $k) {
            $pilihan = $data['email'][$kejadian] ?? [];
            $adaTo = false;
            foreach (AturanEmail::ROLE as $role) {
                $jenis = $pilihan[$role] ?? null;
                if ($jenis) {
                    $baris[] = ['kejadian' => $kejadian, 'role' => $role, 'jenis' => $jenis];
                    $adaTo = $adaTo || $jenis === 'to';
                }
            }
            if (! $adaTo) {
                $tanpaTo[] = $k['grup'].': '.$k['label'];
            }
        }

        if ($tanpaTo) {
            return back()->withInput()->withErrors(['email' => 'Setiap kejadian butuh minimal satu penerima "To": '.implode('; ', $tanpaTo)]);
        }

        // Disamakan per (kejadian, role): baris yang tidak berubah dibiarkan, yang berubah di-update,
        // yang baru ditambah, yang dikosongkan dihapus — bukan hapus-insert ulang semua baris.
        DB::connection('sqlsrv')->transaction(function () use ($baris) {
            $lama = AturanEmail::query()->get()->keyBy(fn ($r) => $r->kejadian.'|'.$r->role);
            foreach ($baris as $b) {
                $r = $lama->pull($b['kejadian'].'|'.$b['role']);
                if (! $r) {
                    AturanEmail::create($b + ['updated_by' => auth()->id()]);
                } elseif ($r->jenis !== $b['jenis']) {
                    $r->update(['jenis' => $b['jenis'], 'updated_by' => auth()->id()]);
                }
            }
            if ($lama->isNotEmpty()) {
                AturanEmail::whereIn('id_aturan_email', $lama->pluck('id_aturan_email'))->delete();
            }
        });
        AturanEmail::lupakanCache();

        return redirect()->route('pengaturan.email')->with('success', 'Penerima email disimpan.');
    }

    /**
     * Batas rasio baru mulai tanggal tertentu: batas yang sedang berlaku ditutup sehari sebelumnya,
     * batas terjadwal yang mulai pada/sesudah tanggal itu dinonaktifkan (diganti).
     */
    public function simpanRasio(Request $request)
    {
        $data = $request->validate([
            'persentase_maksimal' => 'required|numeric|min:0.01|max:100',
            'mulai_berlaku' => 'required|date|after_or_equal:today',
        ]);

        $mulai = Carbon::parse($data['mulai_berlaku'])->startOfDay();

        DB::connection('sqlsrv')->transaction(function () use ($data, $mulai) {
            RasioSewa::where('effective_date', '>=', $mulai->toDateString())->update(['flag' => false]);

            RasioSewa::where(fn ($q) => $q->whereNull('effective_date')->orWhere('effective_date', '<', $mulai->toDateString()))
                ->where(fn ($q) => $q->whereNull('end_date')->orWhere('end_date', '>=', $mulai->toDateString()))
                ->update(['end_date' => $mulai->copy()->subDay()->toDateString()]);

            RasioSewa::create([
                'persentase_maksimal' => $data['persentase_maksimal'],
                'effective_date' => $mulai->toDateString(),
                'end_date' => null,
                'flag' => true,
            ]);
        });

        return redirect()->route('pengaturan.rasio')->with('success', 'Batas rasio '.rtrim(rtrim(number_format((float) $data['persentase_maksimal'], 2, ',', '.'), '0'), ',').'% berlaku mulai '.$mulai->translatedFormat('d M Y').'.');
    }
}

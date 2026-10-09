<?php

namespace App\Http\Controllers;

use App\Models\AturanAlur;
use App\Models\AturanEmail;
use App\Models\RasioSewa;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

/**
 * Pengaturan DCI: alur approval pengajuan sewa, penerima email per kejadian, batas rasio sewa.
 * Perubahan berlaku untuk pengajuan / email berikutnya; alur pengajuan yang sudah diajukan tetap.
 */
class PengaturanController extends Controller
{
    public function index()
    {
        $schema = Schema::connection('sqlsrv');

        return view('pages.pengaturan.index', [
            'tabelSiap' => $schema->hasTable('sesi_aturan_alur') && $schema->hasTable('sesi_aturan_email'),
            'alur' => AturanAlur::semua(),
            'email' => AturanEmail::semua(),
            'rasioAktif' => RasioSewa::aktif(),
            'riwayatRasio' => RasioSewa::withInactive()->orderByDesc('effective_date')->orderByDesc('id_rasio_sewa')->get(),
        ]);
    }

    public function simpanAlur(Request $request)
    {
        $data = $request->validate([
            'alur' => 'required|array',
            'alur.*' => ['required', Rule::in(AturanAlur::PILIHAN_ALUR)],
        ]);

        DB::connection('sqlsrv')->transaction(function () use ($data) {
            foreach (array_keys(AturanAlur::ALUR_BAWAAN) as $kunci) {
                if (! isset($data['alur'][$kunci])) {
                    continue;
                }
                [$jenis, $tujuan, $rasio] = explode('|', $kunci);
                AturanAlur::updateOrCreate(
                    ['jenis_pengajuan' => $jenis, 'tujuan_penyewaan' => $tujuan, 'rasio' => $rasio],
                    ['alur' => $data['alur'][$kunci], 'updated_by' => auth()->id()],
                );
            }
        });
        AturanAlur::lupakanCache();

        return redirect()->route('pengaturan.index')->with('success', 'Alur approval disimpan. Berlaku untuk pengajuan yang diajukan setelah ini.');
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

        DB::connection('sqlsrv')->transaction(function () use ($baris) {
            AturanEmail::query()->delete();
            $sekarang = now();
            foreach ($baris as $b) {
                AturanEmail::create($b + ['updated_by' => auth()->id(), 'created_at' => $sekarang, 'updated_at' => $sekarang]);
            }
        });
        AturanEmail::lupakanCache();

        return redirect()->route('pengaturan.index')->with('success', 'Penerima email disimpan.');
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

        return redirect()->route('pengaturan.index')->with('success', 'Batas rasio '.rtrim(rtrim(number_format((float) $data['persentase_maksimal'], 2, ',', '.'), '0'), ',').'% berlaku mulai '.$mulai->translatedFormat('d M Y').'.');
    }
}

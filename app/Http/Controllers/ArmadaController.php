<?php

namespace App\Http\Controllers;
use Illuminate\Support\Facades\DB;
use App\Models\Armada;

use Illuminate\Http\Request;

class ArmadaController extends Controller
{
    public function index()
    {
        return view('pages.armada.listArmada');
    }

    public function show($id)
    {
        $armada = Armada::with('perusahaan')->findOrFail($id);

        // Get harga sewa terakhir dari pengajuan yang sudah approved
        $hargaSewaTerakhir = $armada->pengajuan()
            ->where('status_pengajuan', 'approved')
            ->orderByDesc('submitted_at')
            ->value('harga_sewa');

        return view('pages.armada.detail', compact('armada', 'hargaSewaTerakhir'));
    }
}

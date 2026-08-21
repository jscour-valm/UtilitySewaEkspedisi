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
        return view('pages.armada.detail', compact('armada'));
    }
}

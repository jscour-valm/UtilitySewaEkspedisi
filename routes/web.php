<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\ArmadaController;
use App\Http\Controllers\PengajuanController;

// Route::get ('/', [ArmadaController::class,"index"]);     

// Auth
Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.submit');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// Redirect root ke login
Route::get('/', fn() => redirect()->route('login'));

// Protected routes
Route::middleware(['auth', 'role:KG'])->group(function () {
    Route::get('/dashboard/kg', fn() => view('pages.dashboard.kg'))->name('dashboard.kg');
    Route::get('/pengajuan', fn() => view('pages.pengajuan.index'))->name('pengajuan.kg');
    Route::get('/pengajuan/{id}', [PengajuanController::class, 'show'])->name('pengajuan.show');

    // API endpoints untuk pengajuan
    Route::post('/api/pengajuan/armada', [PengajuanController::class, 'storeArmada'])->name('pengajuan.store-armada');
    Route::post('/api/pengajuan/submit', [PengajuanController::class, 'submitPengajuan'])->name('pengajuan.submit');
    Route::get('/api/pengajuan/skill-list', [PengajuanController::class, 'getSkillList'])->name('pengajuan.skill-list');
    Route::get('/api/pengajuan/kategori-toko-list', [PengajuanController::class, 'getKategoriTokoList']);
    Route::get('/api/pengajuan/jenis-biaya', [PengajuanController::class, 'getJenisBiaya']);
});

Route::middleware(['auth', 'role:WM'])->group(function () {
    Route::get('/dashboard/wm', fn() => view('pages.dashboard.wm'))->name('dashboard.wm');
});

Route::middleware(['auth', 'role:WH'])->group(function () {
    Route::get('/dashboard/wh', fn() => view('pages.dashboard.wh'))->name('dashboard.wh');
});

Route::middleware(['auth', 'role:DCI'])->group(function () {
    Route::get('/dashboard/dci', fn() => view('pages.dashboard.dci'))->name('dashboard.dci');
});

Route::middleware(['auth', 'role:KG,WM,WH,DCI'])->group(function () {
    Route::get('/armada', [ArmadaController::class, 'index'])->name('armada.idx');
    Route::get('/armada/{id}', [ArmadaController::class, 'show'])->name('armada.show');
});

// Fallback dashboard — redirect ke halaman role masing-masing
Route::middleware(['auth'])->get('/dashboard', function () {
    $role = auth()->user()->userUtility?->role;
    return match ($role) {
        'KG'  => redirect()->route('dashboard.kg'),
        'WM'  => redirect()->route('dashboard.wm'),
        'WH'  => redirect()->route('dashboard.wh'),
        'DCI' => redirect()->route('dashboard.dci'),
        default => abort(403),
    };
})->name('dashboard');
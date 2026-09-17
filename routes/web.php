<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\KendaraanController;
use App\Http\Controllers\PengajuanController;
use App\Http\Controllers\ApprovalController;
use App\Http\Controllers\WmDashboardController;
use App\Http\Controllers\DciDashboardController;
use App\Http\Controllers\DocumentLinkageController;
use App\Http\Controllers\TarifKirimanRutinController;
use App\Http\Controllers\JenisBiayaController;
use App\Http\Controllers\ApproverController;

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
    Route::get('/pengajuan/{id}/edit', [PengajuanController::class, 'edit'])->name('pengajuan.edit');

    // API endpoints untuk pengajuan
    Route::post('/api/pengajuan/perusahaan', [PengajuanController::class, 'storePerusahaan'])->name('pengajuan.store-perusahaan');
    Route::get('/api/pengajuan/perusahaan-list', [PengajuanController::class, 'getPerusahaanList'])->name('pengajuan.perusahaan-list');
    Route::get('/api/pengajuan/kendaraan-by-perusahaan', [PengajuanController::class, 'getKendaraanByPerusahaan'])->name('pengajuan.kendaraan-by-perusahaan');
    Route::get('/api/pengajuan/rate-card', [PengajuanController::class, 'resolveRateCard'])->name('pengajuan.rate-card');
    Route::post('/api/pengajuan/kendaraan', [PengajuanController::class, 'storeKendaraan'])->name('pengajuan.store-kendaraan');
    Route::post('/api/pengajuan/submit', [PengajuanController::class, 'submitPengajuan'])->name('pengajuan.submit');
    Route::put('/api/pengajuan/{id}', [PengajuanController::class, 'update'])->name('pengajuan.update');
    Route::get('/api/pengajuan/skill-list', [PengajuanController::class, 'getSkillList'])->name('pengajuan.skill-list');
    Route::get('/api/pengajuan/kategori-toko-list', [PengajuanController::class, 'getKategoriTokoList']);
    Route::get('/api/pengajuan/jenis-biaya', [PengajuanController::class, 'getJenisBiaya']);
    Route::get('/api/dokumen/list', [PengajuanController::class, 'getDokumenList'])->name('dokumen.list');

    // Tarif Kiriman Rutin — read-only untuk KG (step2 dropdown)
    Route::get('/api/tarif-kiriman-rutin', [TarifKirimanRutinController::class, 'list'])->name('tarif-kiriman-rutin.list');
    Route::get('/api/jenis-barang-kiriman', [TarifKirimanRutinController::class, 'listBarang'])->name('jenis-barang-kiriman.list');

    // Document Linkage API endpoints
    Route::post('/api/pengajuan/{id}/surat-jalan/link', [DocumentLinkageController::class, 'linkSuratJalan'])->name('pengajuan.link-surat-jalan');
    Route::delete('/api/pengajuan/{id}/surat-jalan/{sjId}/unlink', [DocumentLinkageController::class, 'unlinkSuratJalan'])->name('pengajuan.unlink-surat-jalan');
    Route::post('/api/pengajuan/{id}/transfer-antar-cabang/link', [DocumentLinkageController::class, 'linkTransferAntarCabang'])->name('pengajuan.link-to-acb');
    Route::delete('/api/pengajuan/{id}/transfer-antar-cabang/{toAcbId}/unlink', [DocumentLinkageController::class, 'unlinkTransferAntarCabang'])->name('pengajuan.unlink-to-acb');
    Route::get('/api/pengajuan/{id}/documents', [DocumentLinkageController::class, 'getLinkedDocuments'])->name('pengajuan.get-documents');
});

// Detail pengajuan — dishare KG (pemilik pengajuan) + WH/DCI (global read-only,
// lihat User::isGlobalAccess() & PengajuanController::show() yang pakai
// canAccessCabang(), bukan hardcode role). Tombol Edit di detailPengajuan.blade.php
// sendiri sudah di-gate auth()->id() == submitted_by, jadi otomatis view-only
// buat WH/DCI tanpa perlu view/controller terpisah.
Route::middleware(['auth', 'role:KG,WH,DCI'])->group(function () {
    Route::get('/pengajuan/{id}', [PengajuanController::class, 'show'])->name('pengajuan.show');

    // Dipindah dari grup role:KG — dipakai juga oleh halaman Kelola Perusahaan
    // (Master Data, DCI) buat edit profil vendor, bukan cuma wizard Pengajuan Sewa.
    Route::post('/api/pengajuan/perusahaan/{id}', [PengajuanController::class, 'updateVendor'])->name('pengajuan.update-vendor');
});

Route::middleware(['auth', 'role:WM'])->group(function () {
    Route::get('/dashboard/wm', [WmDashboardController::class, 'index'])->name('dashboard.wm');

    // WM Approval routes
    Route::get('/approval/pengajuan/{id}', [ApprovalController::class, 'showForWm'])->name('approval.show');
    Route::post('/approval/{id}/approve', [ApprovalController::class, 'approve'])->name('approval.approve');
    Route::post('/approval/{id}/reject', [ApprovalController::class, 'reject'])->name('approval.reject');
});

Route::middleware(['auth', 'role:WH'])->group(function () {
    Route::get('/dashboard/wh', fn() => view('pages.dashboard.wh'))->name('dashboard.wh');
});

// Kelola Tarif — akses VIEW (GET) dibuka ke WM/WH juga (round 16: mereka jadi
// pakai halaman ini read-only sbg pengganti /kendaraan lama yang sekarang
// KG-only). Endpoint yang NULIS data (update/store/destroy dsb) TETAP DCI-only,
// lihat grup role:DCI di bawah.
Route::middleware(['auth', 'role:WM,WH,DCI'])->group(function () {
    Route::get('/kelola-tarif/sewa-truk', [TarifKirimanRutinController::class, 'indexSewaTruk'])->name('kelola-tarif.sewa-truk');
    Route::get('/kelola-tarif/sewa-truk/{id}/edit', [TarifKirimanRutinController::class, 'editSewaTruk'])->name('kelola-tarif.sewa-truk.edit');
    Route::get('/kelola-tarif/kiriman-rutin', [TarifKirimanRutinController::class, 'indexKirimanRutin'])->name('kelola-tarif.kiriman-rutin');
    Route::get('/kelola-tarif/kiriman-rutin/{id}/edit', [TarifKirimanRutinController::class, 'editKirimanRutin'])->name('kelola-tarif.kiriman-rutin.edit');
});

Route::middleware(['auth', 'role:DCI'])->group(function () {
    Route::get('/dashboard/dci', [DciDashboardController::class, 'index'])->name('dashboard.dci');
    // Kelola Tarif — update TETAP DCI-only (GET index/edit-nya udah dipindah ke
    // grup role:WM,WH,DCI di atas).
    Route::put('/kelola-tarif/sewa-truk/{id}', [TarifKirimanRutinController::class, 'updateSewaTruk'])->name('kelola-tarif.sewa-truk.update');
    Route::put('/kelola-tarif/kiriman-rutin/{id}', [TarifKirimanRutinController::class, 'updateKirimanRutin'])->name('kelola-tarif.kiriman-rutin.update');

    // Tarif per jenis barang individual — masih dipakai step2 KG (fetch by id_vendor_skill)
    Route::post('/api/tarif-kiriman-rutin', [TarifKirimanRutinController::class, 'store'])->name('tarif-kiriman-rutin.store');
    Route::put('/api/tarif-kiriman-rutin/{id}', [TarifKirimanRutinController::class, 'update'])->name('tarif-kiriman-rutin.update');
    Route::delete('/api/tarif-kiriman-rutin/{id}', [TarifKirimanRutinController::class, 'destroy'])->name('tarif-kiriman-rutin.destroy');

    // Rate Card (vendor+cabang+area) — dasar tarif per-area (Task 15, 9 Sept)
    Route::get('/api/rate-card-kiriman-rutin', [TarifKirimanRutinController::class, 'listRateCard'])->name('rate-card-kiriman-rutin.list');
    Route::post('/api/rate-card-kiriman-rutin', [TarifKirimanRutinController::class, 'storeRateCard'])->name('rate-card-kiriman-rutin.store');
    Route::get('/api/master-skill', [TarifKirimanRutinController::class, 'listSkill'])->name('master-skill.list');
    Route::get('/api/master-skill/by-cabang/{cabang_code}', [TarifKirimanRutinController::class, 'skillByCabang'])->name('master-skill.by-cabang');

    // Master Jenis Barang Kiriman — CRUD only DCI
    Route::get('/master/jenis-barang-kiriman', [TarifKirimanRutinController::class, 'indexBarang'])->name('master.jenis-barang-kiriman');
    Route::post('/api/jenis-barang-kiriman', [TarifKirimanRutinController::class, 'storeBarang'])->name('jenis-barang-kiriman.store');
    Route::put('/api/jenis-barang-kiriman/{id}', [TarifKirimanRutinController::class, 'updateBarang'])->name('jenis-barang-kiriman.update');
    Route::delete('/api/jenis-barang-kiriman/{id}', [TarifKirimanRutinController::class, 'destroyBarang'])->name('jenis-barang-kiriman.destroy');

    // Master Jenis Biaya Tambahan — CRUD only DCI (model lama, baru sekarang ada halaman kelola)
    Route::get('/master/jenis-biaya-tambahan', [JenisBiayaController::class, 'index'])->name('master.jenis-biaya-tambahan');
    Route::post('/api/jenis-biaya', [JenisBiayaController::class, 'store'])->name('jenis-biaya.store');
    Route::put('/api/jenis-biaya/{id}', [JenisBiayaController::class, 'update'])->name('jenis-biaya.update');
    Route::delete('/api/jenis-biaya/{id}', [JenisBiayaController::class, 'destroy'])->name('jenis-biaya.destroy');

    // Kendaraan CRUD dari halaman Kelola Perusahaan (Master Data, di dalam edit
    // Sewa Truk/Kiriman Rutin) — beda dari pengajuan.store-kendaraan (wizard,
    // id_cabang implisit dari user login): di sini id_cabang eksplisit dari form
    // karena DCI global access (getCabangId() kosong).
    Route::post('/api/kendaraan', [KendaraanController::class, 'store'])->name('kendaraan.store');
    Route::put('/api/kendaraan/{id}', [KendaraanController::class, 'update'])->name('kendaraan.update');
    Route::delete('/api/kendaraan/{id}', [KendaraanController::class, 'destroy'])->name('kendaraan.destroy');

    // Setting Approver — kelola manual WM per cabang (sesi_user_cabang), buat
    // kasus khusus di luar sync otomatis (cabang baru/belum ada WM).
    Route::get('/setting-approver', [ApproverController::class, 'index'])->name('setting-approver.index');
    Route::post('/setting-approver', [ApproverController::class, 'store'])->name('setting-approver.store');
    Route::post('/setting-approver/by-area', [ApproverController::class, 'storeByArea'])->name('setting-approver.store-by-area');
    Route::delete('/setting-approver/{id}', [ApproverController::class, 'destroy'])->name('setting-approver.destroy');
});

// Round 16: /kendaraan dibalikin KG-only — WM/WH pakai Kelola Tarif read-only,
// DCI pakai Master Data/Kelola Tarif (edit penuh), keduanya lewat grup lain.
Route::middleware(['auth', 'role:KG'])->group(function () {
    Route::get('/kendaraan', [KendaraanController::class, 'index'])->name('kendaraan.idx');
    Route::get('/kendaraan/{id}', [KendaraanController::class, 'show'])->name('kendaraan.show');
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
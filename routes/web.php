<?php

use App\Http\Controllers\ApprovalController;
use App\Http\Controllers\ApproverController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\DciDashboardController;
use App\Http\Controllers\DocumentLinkageController;
use App\Http\Controllers\JenisBiayaController;
use App\Http\Controllers\JenisKendaraanController;
use App\Http\Controllers\KaDashboardController;
use App\Http\Controllers\KendaraanController;
use App\Http\Controllers\PengajuanController;
use App\Http\Controllers\PersetujuanMasterController;
use App\Http\Controllers\PerusahaanController;
use App\Http\Controllers\TarifKirimanRutinController;
use App\Http\Controllers\WcDashboardController;
use App\Http\Controllers\WhDashboardController;
use App\Http\Controllers\WmDashboardController;
use Illuminate\Support\Facades\Route;

// Auth
Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.submit');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
Route::get('/sesi-habis', [AuthController::class, 'sesiHabis'])->name('sesi.habis');

// Root: dashboard sesuai role kalau sudah login, selain itu ke login
Route::get('/', [AuthController::class, 'beranda'])->name('beranda');

// Protected routes
Route::middleware(['auth', 'role:KG'])->group(function () {
    Route::get('/dashboard/kg', fn () => view('pages.dashboard.kg'))->name('dashboard.kg')->middleware('query.sesi');
    Route::get('/pengajuan', fn () => view('pages.pengajuan.index'))->name('pengajuan.kg');
    Route::get('/pengajuan/{id}/edit', [PengajuanController::class, 'edit'])->name('pengajuan.edit');

    // API endpoints untuk pengajuan
    Route::post('/api/pengajuan/perusahaan', [PengajuanController::class, 'storePerusahaan'])->name('pengajuan.store-perusahaan');
    Route::get('/api/pengajuan/perusahaan-list', [PengajuanController::class, 'getPerusahaanList'])->name('pengajuan.perusahaan-list');
    Route::get('/api/pengajuan/kendaraan-by-perusahaan', [PengajuanController::class, 'getKendaraanByPerusahaan'])->name('pengajuan.kendaraan-by-perusahaan');
    Route::get('/api/pengajuan/rate-card', [PengajuanController::class, 'resolveRateCard'])->name('pengajuan.rate-card');
    Route::post('/api/pengajuan/kendaraan', [PengajuanController::class, 'storeKendaraan'])->name('pengajuan.store-kendaraan');
    Route::post('/api/pengajuan/submit', [PengajuanController::class, 'submitPengajuan'])->name('pengajuan.submit');
    Route::post('/api/pengajuan/{id}/notifikasi-baru', [PengajuanController::class, 'notifikasiBaru'])->name('pengajuan.notifikasi-baru');
    Route::post('/api/pengajuan/{id}/batalkan', [PengajuanController::class, 'batalkan'])->name('pengajuan.batalkan');
    Route::put('/api/pengajuan/{id}', [PengajuanController::class, 'update'])->name('pengajuan.update');
    Route::get('/api/pengajuan/skill-list', [PengajuanController::class, 'getSkillList'])->name('pengajuan.skill-list');
    Route::get('/api/pengajuan/kategori-toko-list', [PengajuanController::class, 'getKategoriTokoList']);
    Route::get('/api/pengajuan/jenis-biaya', [PengajuanController::class, 'getJenisBiaya']);
    Route::get('/api/pengajuan/cabang-list', [PengajuanController::class, 'getCabangList'])->name('pengajuan.cabang-list');
    Route::get('/api/dokumen/list', [PengajuanController::class, 'getDokumenList'])->name('dokumen.list');

    Route::get('/api/tarif-kiriman-rutin', [TarifKirimanRutinController::class, 'list'])->name('tarif-kiriman-rutin.list');
    Route::get('/api/jenis-barang-kiriman', [TarifKirimanRutinController::class, 'listBarang'])->name('jenis-barang-kiriman.list');

    // Document Linkage API endpoints
    Route::post('/api/pengajuan/{id}/surat-jalan/link', [DocumentLinkageController::class, 'linkSuratJalan'])->name('pengajuan.link-surat-jalan');
    Route::delete('/api/pengajuan/{id}/surat-jalan/{sjId}/unlink', [DocumentLinkageController::class, 'unlinkSuratJalan'])->name('pengajuan.unlink-surat-jalan');
    Route::post('/api/pengajuan/{id}/transfer-antar-cabang/link', [DocumentLinkageController::class, 'linkTransferAntarCabang'])->name('pengajuan.link-to-acb');
    Route::delete('/api/pengajuan/{id}/transfer-antar-cabang/{toAcbId}/unlink', [DocumentLinkageController::class, 'unlinkTransferAntarCabang'])->name('pengajuan.unlink-to-acb');
    Route::get('/api/pengajuan/{id}/documents', [DocumentLinkageController::class, 'getLinkedDocuments'])->name('pengajuan.get-documents');
});

Route::middleware(['auth', 'role:KG,WH,WC,DCI,KA'])->group(function () {
    Route::get('/pengajuan/{id}', [PengajuanController::class, 'show'])->name('pengajuan.show');

    Route::post('/api/pengajuan/perusahaan/{id}', [PengajuanController::class, 'updateVendor'])->name('pengajuan.update-vendor');
});

// Link di email notifikasi (1 email dibaca banyak role): redirect sesuai role
Route::middleware(['auth', 'role:KG,KA,WM,WC,WH,DCI'])->group(function () {
    Route::get('/pengajuan/{id}/buka', [PengajuanController::class, 'buka'])->name('pengajuan.buka');
});

// Link di email proses vendor baru / usulan harga master
Route::middleware(['auth', 'role:KG,WM,WC,WH,DCI'])->group(function () {
    Route::get('/persetujuan/{jenis}/{id}/buka', [PersetujuanMasterController::class, 'buka'])
        ->whereIn('jenis', ['vendor', 'harga'])->whereNumber('id')->name('persetujuan.buka');
});

Route::middleware(['auth', 'role:WM'])->group(function () {
    Route::get('/dashboard/wm', [WmDashboardController::class, 'index'])->name('dashboard.wm')->middleware('query.sesi');
});

Route::middleware(['auth', 'role:KA'])->group(function () {
    Route::get('/kaadmin/dashboard', [KaDashboardController::class, 'index'])->name('dashboard.ka')->middleware('query.sesi');
});

Route::middleware(['auth', 'role:WH'])->group(function () {
    Route::get('/dashboard/wh', [WhDashboardController::class, 'index'])->name('dashboard.wh')->middleware('query.sesi');
});

Route::middleware(['auth', 'role:WC'])->group(function () {
    Route::get('/dashboard/wc', [WcDashboardController::class, 'index'])->name('dashboard.wc')->middleware('query.sesi');
});

Route::middleware(['auth', 'role:WM,WC,WH'])->group(function () {
    Route::get('/approval/pengajuan/{id}', [ApprovalController::class, 'show'])->name('approval.show');
    Route::post('/approval/{id}/approve', [ApprovalController::class, 'approve'])->name('approval.approve');
    Route::post('/approval/{id}/reject', [ApprovalController::class, 'reject'])->name('approval.reject');
    Route::post('/approval/{id}/usulan', [ApprovalController::class, 'decideUsulan'])->name('approval.usulan.decide');
});

Route::middleware(['auth', 'role:KG,WM,WH,WC,DCI'])->group(function () {
    Route::get('/perusahaan', [PerusahaanController::class, 'index'])->name('perusahaan.index');
    Route::get('/perusahaan/facet/skill-options', [PerusahaanController::class, 'skillOptions'])->name('perusahaan.facet.skill-options');
    Route::get('/api/perusahaan/preview', [PerusahaanController::class, 'preview'])->name('perusahaan.preview');
    Route::get('/perusahaan/{id}', [PerusahaanController::class, 'show'])->whereNumber('id')->name('perusahaan.show');

    // Dropdown "Jenis Kendaraan" di form tambah/edit kendaraan — KG juga butuh baca ini,
    // beda dari CRUD-nya (nambah/edit entri master) yang cuma boleh DCI/WH/WM.
    Route::get('/api/jenis-kendaraan', [JenisKendaraanController::class, 'list'])->name('jenis-kendaraan.list');
});

Route::middleware(['auth', 'role:WM,WH,DCI'])->group(function () {
    Route::get('/perusahaan/sewa-truk/{id}/edit', [TarifKirimanRutinController::class, 'editSewaTruk'])->name('perusahaan.sewa-truk.edit');
    Route::get('/perusahaan/kiriman-rutin/{id}/edit', [TarifKirimanRutinController::class, 'editKirimanRutin'])->name('perusahaan.kiriman-rutin.edit');

    // Master Jenis Kendaraan — yang boleh kelola: DCI, WH, WM (review mentor item 4).
    Route::get('/master/jenis-kendaraan', [JenisKendaraanController::class, 'index'])->name('master.jenis-kendaraan');
    Route::post('/api/jenis-kendaraan', [JenisKendaraanController::class, 'store'])->name('jenis-kendaraan.store');
    Route::put('/api/jenis-kendaraan/{id}', [JenisKendaraanController::class, 'update'])->name('jenis-kendaraan.update');
    Route::delete('/api/jenis-kendaraan/{id}', [JenisKendaraanController::class, 'destroy'])->name('jenis-kendaraan.destroy');
});

Route::middleware(['auth', 'role:DCI'])->group(function () {
    Route::get('/dashboard/dci', [DciDashboardController::class, 'index'])->name('dashboard.dci')->middleware('query.sesi');
    Route::put('/perusahaan/sewa-truk/{id}', [TarifKirimanRutinController::class, 'updateSewaTruk'])->name('perusahaan.sewa-truk.update');
    Route::put('/perusahaan/kiriman-rutin/{id}', [TarifKirimanRutinController::class, 'updateKirimanRutin'])->name('perusahaan.kiriman-rutin.update');

    Route::post('/api/tarif-kiriman-rutin', [TarifKirimanRutinController::class, 'store'])->name('tarif-kiriman-rutin.store');
    Route::put('/api/tarif-kiriman-rutin/{id}', [TarifKirimanRutinController::class, 'update'])->name('tarif-kiriman-rutin.update');
    Route::delete('/api/tarif-kiriman-rutin/{id}', [TarifKirimanRutinController::class, 'destroy'])->name('tarif-kiriman-rutin.destroy');

    Route::get('/api/rate-card-kiriman-rutin', [TarifKirimanRutinController::class, 'listRateCard'])->name('rate-card-kiriman-rutin.list');
    Route::post('/api/rate-card-kiriman-rutin', [TarifKirimanRutinController::class, 'storeRateCard'])->name('rate-card-kiriman-rutin.store');
    Route::get('/api/master-skill', [TarifKirimanRutinController::class, 'listSkill'])->name('master-skill.list');
    Route::get('/api/master-skill/by-cabang/{cabang_code}', [TarifKirimanRutinController::class, 'skillByCabang'])->name('master-skill.by-cabang');

    Route::get('/master/jenis-barang-kiriman', [TarifKirimanRutinController::class, 'indexBarang'])->name('master.jenis-barang-kiriman');
    Route::post('/api/jenis-barang-kiriman', [TarifKirimanRutinController::class, 'storeBarang'])->name('jenis-barang-kiriman.store');
    Route::put('/api/jenis-barang-kiriman/{id}', [TarifKirimanRutinController::class, 'updateBarang'])->name('jenis-barang-kiriman.update');
    Route::delete('/api/jenis-barang-kiriman/{id}', [TarifKirimanRutinController::class, 'destroyBarang'])->name('jenis-barang-kiriman.destroy');

    Route::get('/master/jenis-biaya-tambahan', [JenisBiayaController::class, 'index'])->name('master.jenis-biaya-tambahan');
    Route::post('/api/jenis-biaya', [JenisBiayaController::class, 'store'])->name('jenis-biaya.store');
    Route::put('/api/jenis-biaya/{id}', [JenisBiayaController::class, 'update'])->name('jenis-biaya.update');
    Route::delete('/api/jenis-biaya/{id}', [JenisBiayaController::class, 'destroy'])->name('jenis-biaya.destroy');

    Route::post('/api/kendaraan', [KendaraanController::class, 'store'])->name('kendaraan.store');
    Route::put('/api/kendaraan/{id}', [KendaraanController::class, 'update'])->name('kendaraan.update');
    Route::delete('/api/kendaraan/{id}', [KendaraanController::class, 'destroy'])->name('kendaraan.destroy');

    Route::get('/setting-approver', [ApproverController::class, 'index'])->name('setting-approver.index');
    Route::post('/setting-approver', [ApproverController::class, 'store'])->name('setting-approver.store');
    Route::post('/setting-approver/by-area', [ApproverController::class, 'storeByArea'])->name('setting-approver.store-by-area');
    Route::delete('/setting-approver/{id}', [ApproverController::class, 'destroy'])->name('setting-approver.destroy');
});

Route::middleware(['auth'])->get('/dashboard', function () {
    $role = auth()->user()->userUtility?->role;

    return match ($role) {
        'KG' => redirect()->route('dashboard.kg'),
        'WM' => redirect()->route('dashboard.wm'),
        'WH' => redirect()->route('dashboard.wh'),
        'WC' => redirect()->route('dashboard.wc'),
        'DCI' => redirect()->route('dashboard.dci'),
        'KA' => redirect()->route('dashboard.ka'),
        default => abort(403),
    };
})->name('dashboard');

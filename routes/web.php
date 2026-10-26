<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PurchaseOrderController;
use App\Http\Controllers\ReceivingController;
use App\Http\Controllers\ProductionController;
use App\Http\Controllers\PackingController;
use App\Http\Controllers\MasterController;
use App\Http\Controllers\RiwayatController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\BatchController;
use App\Http\Controllers\TraceabilityController;
use App\Http\Controllers\StageController;
use App\Http\Controllers\YieldStandardController;
use App\Http\Controllers\ReportController;

Route::get('/', fn() => redirect()->route('dashboard'));

Route::middleware(['auth'])->group(function () {
    Route::get('/dashboard', [DashboardController::class,'index'])->name('dashboard');

    // Riwayat (Masuk & Keluar)
    Route::get('/riwayat', [RiwayatController::class,'index'])->name('riwayat.index');

    // Modul 1: PO
    Route::resource('po', PurchaseOrderController::class);

    // Modul 2: Bahan Baku
    Route::prefix('receiving')->name('receiving.')->group(function () {
        Route::get('/',               [ReceivingController::class,'index'])->name('index');
        Route::get('/create',         [ReceivingController::class,'create'])->name('create');
        Route::post('/',              [ReceivingController::class,'store'])->name('store');
        Route::delete('/{receiving}', [ReceivingController::class,'destroy'])->name('destroy');
        Route::post('/import-csv',    [ReceivingController::class,'importCsv'])->name('import');
    });

    // Modul 3: Ringkasan & analisa produksi.
    // Pencatatannya sendiri ada di /stage/{tahap} (StageController).
    Route::prefix('production')->name('production.')->group(function () {
        Route::get('/',                 [ProductionController::class,'index'])->name('index');
        Route::get('/analisa',          [ProductionController::class,'analisa'])->name('analisa');
        Route::get('/analisa/{po}/csv', [ProductionController::class,'exportCsv'])->name('analisa.export');
        Route::get('/analisa/{po}/pdf', [ProductionController::class,'exportPdf'])->name('analisa.pdf');
    });

    // Batch & Lot
    Route::prefix('batch')->name('batch.')->group(function () {
        Route::get('/',              [BatchController::class,'index'])->name('index');
        // HARUS DI ATAS route {batch}, kalau tidak "labels" ditangkap wildcard.
        Route::get('/labels',        [BatchController::class,'labels'])->name('labels');
        Route::get('/{batch}',       [BatchController::class,'show'])->name('show');
        Route::get('/{batch}/label', [BatchController::class,'printLabel'])->name('label');
    });

    // Tahapan produksi: Potong -> Trimming -> Potong Steak.
    // {stage} diikat lewat kolom `code` (lihat ProcessStage::getRouteKeyName).
    Route::prefix('stage')->name('stage.')->group(function () {
        // Route statis wajib di atas wildcard {stage}, kalau tidak "record"
        // akan ditangkap sebagai kode tahap.
        Route::get('/record/{record}',       [StageController::class,'show'])->name('show');
        Route::post('/record/{record}/void', [StageController::class,'void'])->name('void');
        Route::get('/{stage}',               [StageController::class,'index'])->name('index');
        Route::get('/{stage}/create',        [StageController::class,'create'])->name('create');
        Route::post('/{stage}',              [StageController::class,'store'])->name('store');
    });

    // Telusur / Traceability
    Route::prefix('trace')->name('trace.')->group(function () {
        Route::get('/',           [TraceabilityController::class,'index'])->name('index');
        Route::post('/resolve',   [TraceabilityController::class,'resolve'])->name('resolve');
        Route::get('/batch/{qr}', [TraceabilityController::class,'batch'])->name('batch');
        Route::get('/box/{qr}',   [TraceabilityController::class,'box'])->name('box');
    });

    // Modul 4: Packing. Satu baris = satu box fisik = satu QR
    Route::prefix('packing')->name('packing.')->group(function () {
        Route::get('/',       [PackingController::class,'index'])->name('index');
        Route::get('/create', [PackingController::class,'create'])->name('create');
        // Route statis wajib di atas /box/{box} agar tidak tertangkap wildcard.
        Route::get('/boxes',  [PackingController::class,'boxes'])->name('boxes');
        Route::get('/labels', [PackingController::class,'labels'])->name('labels');
        Route::post('/run',   [PackingController::class,'storeRun'])->name('run.store');
        Route::post('/weigh', [PackingController::class,'weighBox'])->name('weigh');
        Route::post('/box/{box}/adjust', [PackingController::class,'adjustWeight'])->name('box.adjust');
        Route::post('/box/{box}/void',   [PackingController::class,'voidBox'])->name('box.void');
    });

    // Laporan lintas modul
    Route::prefix('report')->name('report.')->group(function () {
        Route::get('/raw-material',     [ReportController::class,'rawMaterial'])->name('raw-material');
        Route::get('/raw-material/csv', [ReportController::class,'exportRawMaterialCsv'])->name('raw-material.csv');
        Route::get('/yield',            [ReportController::class,'yieldRecovery'])->name('yield');
    });

    // Master Data
    Route::prefix('master')->name('master.')->group(function () {
        // Group Bahan Baku
        Route::get('/material-groups',                    [MasterController::class,'materialGroups'])->name('material-groups');
        Route::post('/material-groups',                   [MasterController::class,'storeMaterialGroup'])->name('material-groups.store');
        Route::put('/material-groups/{materialGroup}',    [MasterController::class,'updateMaterialGroup'])->name('material-groups.update');
        Route::delete('/material-groups/{materialGroup}', [MasterController::class,'destroyMaterialGroup'])->name('material-groups.destroy');
        // Subgroup Bahan Baku
        Route::post('/subgroups',                         [MasterController::class,'storeSubgroup'])->name('subgroups.store');
        Route::put('/subgroups/{subgroup}',               [MasterController::class,'updateSubgroup'])->name('subgroups.update');
        Route::delete('/subgroups/{subgroup}',            [MasterController::class,'destroySubgroup'])->name('subgroups.destroy');
        // Estimasi Yield
        Route::post('/subgroups/{subgroup}/yield',        [MasterController::class,'saveYieldEstimates'])->name('subgroups.yield');
        // Group Produk
        Route::get('/product-groups',                     [MasterController::class,'productGroups'])->name('product-groups');
        Route::post('/product-groups',                    [MasterController::class,'storeProductGroup'])->name('product-groups.store');
        Route::put('/product-groups/{productGroup}',      [MasterController::class,'updateProductGroup'])->name('product-groups.update');
        // Tipe Produk
        Route::post('/product-types',                     [MasterController::class,'storeProductType'])->name('product-types.store');
        Route::put('/product-types/{productType}',        [MasterController::class,'updateProductType'])->name('product-types.update');
        // Supplier
        Route::get('/suppliers',                          [SupplierController::class,'index'])->name('suppliers');
        Route::post('/suppliers',                         [SupplierController::class,'store'])->name('suppliers.store');
        Route::put('/suppliers/{supplier}',               [SupplierController::class,'update'])->name('suppliers.update');
        Route::delete('/suppliers/{supplier}',            [SupplierController::class,'destroy'])->name('suppliers.destroy');
        // Standar Yield (toleransi penyusutan), versioned dan tidak ditimpa
        Route::get('/yield-standards',                    [YieldStandardController::class,'index'])->name('yield-standards');
        Route::post('/yield-standards',                   [YieldStandardController::class,'store'])->name('yield-standards.store');
        Route::post('/yield-standards/{standard}/close',  [YieldStandardController::class,'close'])->name('yield-standards.close');
    });

    // API AJAX
    Route::prefix('api')->name('api.')->group(function () {
        Route::post('po/calc-item',              [PurchaseOrderController::class,'calcItem'])->name('po.calc');
        Route::get('po/active',                  [PurchaseOrderController::class,'activePOs'])->name('po.active');
        Route::get('receiving/stock',            [ReceivingController::class,'stockCheck'])->name('receiving.stock');
        Route::get('packing/lots',               [PackingController::class,'apiAvailableLots'])->name('packing.lots');
        Route::get('master/products-grouped',    [MasterController::class,'productsGrouped'])->name('master.products-grouped');
        Route::get('master/subgroup-yields/{s}', [MasterController::class,'subgroupYields'])->name('master.subgroup-yields');
        Route::get('supplier/lookup',            [SupplierController::class,'lookup'])->name('supplier.lookup');
        Route::get('batch/available',            [BatchController::class,'apiAvailable'])->name('batch.available');
        Route::get('batch/lookup/{qr}',          [BatchController::class,'apiLookup'])->name('batch.lookup');
        Route::get('trace/{qr}',                 [TraceabilityController::class,'apiTrace'])->name('trace');
        Route::get('stage/{stage}/inputs',       [StageController::class,'apiAvailableInputs'])->name('stage.inputs');
    });
});

require __DIR__.'/auth.php';

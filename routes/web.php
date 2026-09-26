<?php

use App\Http\Controllers\ActiveBranchController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FinanceController;
use App\Http\Controllers\IngredientController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\PurchaseOrderController;
use App\Http\Controllers\SaleController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check() ? redirect()->route('dashboard') : redirect()->route('login');
});

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->name('login.store');
});
Route::post('/logout', [LoginController::class, 'destroy'])->middleware('auth')->name('logout');

Route::middleware(['auth'])->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::post('/active-branch', [ActiveBranchController::class, 'store'])
        ->middleware('branch.access')->name('active-branch.store');

    // ---- POS (route 'data' harus di depan wildcard {sale}) ----
    Route::get('/sales', [SaleController::class, 'index'])->name('sales.index');
    Route::get('/sales/data', [SaleController::class, 'data'])->name('sales.data');
    Route::get('/sales/create', [SaleController::class, 'create'])->name('sales.create');
    Route::post('/sales', [SaleController::class, 'store'])->middleware('branch.access')->name('sales.store');
    Route::get('/sales/{sale}', [SaleController::class, 'show'])->name('sales.show');

    // ---- Purchase Order ----
    Route::get('/purchase-orders', [PurchaseOrderController::class, 'index'])->name('purchase-orders.index');
    Route::get('/purchase-orders/data', [PurchaseOrderController::class, 'data'])->name('purchase-orders.data');
    Route::get('/purchase-orders/create', [PurchaseOrderController::class, 'create'])->name('purchase-orders.create');
    Route::post('/purchase-orders', [PurchaseOrderController::class, 'store'])->middleware('branch.access')->name('purchase-orders.store');
    Route::get('/purchase-orders/{purchaseOrder}', [PurchaseOrderController::class, 'show'])->name('purchase-orders.show');
    Route::post('/purchase-orders/{purchaseOrder}/approve', [PurchaseOrderController::class, 'approve'])->name('purchase-orders.approve');
    Route::post('/purchase-orders/{purchaseOrder}/reject', [PurchaseOrderController::class, 'reject'])->name('purchase-orders.reject');
    Route::post('/purchase-orders/{purchaseOrder}/ship', [PurchaseOrderController::class, 'ship'])->name('purchase-orders.ship');
    Route::post('/purchase-orders/{purchaseOrder}/receive', [PurchaseOrderController::class, 'receive'])->name('purchase-orders.receive');

    // ---- Katalog (baca: semua role; tulis: full-owner) ----
    Route::get('/ingredients/form/{ingredient?}', [IngredientController::class, 'form'])->name('ingredients.form');
    Route::get('/ingredients/data', [IngredientController::class, 'data'])->name('ingredients.data');
    Route::resource('ingredients', IngredientController::class)->except(['show'])->names('ingredients');
    Route::get('/products/form/{product?}', [ProductController::class, 'form'])->name('products.form');
    Route::get('/products/data', [ProductController::class, 'data'])->name('products.data');
    Route::resource('products', ProductController::class)->except(['show'])->names('products');

    // ---- Keuangan cabang (owner-mitra; full-owner ditolak) ----
    Route::middleware('financial.branch')->prefix('finance')->name('finance.')->group(function () {
        Route::get('/', [FinanceController::class, 'index'])->name('index');
        Route::post('/shifts', [FinanceController::class, 'open'])->middleware('branch.access')->name('shifts.open');
        Route::post('/shifts/{cashShift}/close', [FinanceController::class, 'close'])->name('shifts.close');
        Route::post('/transactions', [FinanceController::class, 'transaction'])->middleware('branch.access')->name('transactions.store');
    });
});

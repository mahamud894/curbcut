<?php

use App\Http\Controllers\SiteController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('welcome');
})->name('home');

Route::middleware(['auth'])->group(function () {
    Route::redirect('dashboard', '/sites')->name('dashboard');

    Route::get('sites', [SiteController::class, 'index'])->name('sites.index');
    Route::post('sites', [SiteController::class, 'store'])->name('sites.store');
    Route::get('sites/{site}', [SiteController::class, 'show'])->name('sites.show');
    Route::get('sites/{site}/issues', [SiteController::class, 'issues'])->name('sites.issues');
    Route::get('sites/{site}/pages', [SiteController::class, 'pages'])->name('sites.pages');
    Route::get('sites/{site}/scans/{scan}', [SiteController::class, 'scan'])->name('sites.scans.show');
    Route::post('sites/{site}/scans', [SiteController::class, 'startScan'])->name('sites.scans.store');
    Route::get('sites/{site}/settings', [SiteController::class, 'edit'])->name('sites.edit');
    Route::put('sites/{site}', [SiteController::class, 'update'])->name('sites.update');
    Route::delete('sites/{site}', [SiteController::class, 'destroy'])->name('sites.destroy');
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';

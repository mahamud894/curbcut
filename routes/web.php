<?php

use App\Http\Controllers\ChecklistController;
use App\Http\Controllers\IssueController;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\SiteController;
use App\Http\Controllers\StatementController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('welcome');
})->name('home');

// Public accessibility statements (no login required).
Route::get('accessibility-statement/{token}', [StatementController::class, 'public'])->name('statement.public');

Route::middleware(['auth'])->group(function () {
    Route::redirect('dashboard', '/sites')->name('dashboard');

    Route::get('sites', [SiteController::class, 'index'])->name('sites.index');
    Route::post('sites', [SiteController::class, 'store'])->name('sites.store');
    Route::get('sites/{site}', [SiteController::class, 'show'])->name('sites.show');
    Route::get('sites/{site}/issues', [SiteController::class, 'issues'])->name('sites.issues');
    Route::patch('sites/{site}/issues', [IssueController::class, 'bulk'])->name('sites.issues.bulk');
    Route::get('sites/{site}/issues/{issue}', [IssueController::class, 'show'])->name('sites.issues.show');
    Route::patch('sites/{site}/issues/{issue}', [IssueController::class, 'update'])->name('sites.issues.update');
    Route::post('sites/{site}/issues/{issue}/comments', [IssueController::class, 'comment'])->name('sites.issues.comment');
    Route::get('sites/{site}/pages', [SiteController::class, 'pages'])->name('sites.pages');
    Route::get('sites/{site}/checklist', [ChecklistController::class, 'index'])->name('sites.checklist');
    Route::put('sites/{site}/checklist/{criterion}', [ChecklistController::class, 'update'])->name('sites.checklist.update');
    Route::get('sites/{site}/statement', [StatementController::class, 'edit'])->name('sites.statement');
    Route::put('sites/{site}/statement', [StatementController::class, 'update'])->name('sites.statement.update');
    Route::put('sites/{site}/statement/publish', [StatementController::class, 'publish'])->name('sites.statement.publish');
    Route::post('sites/{site}/members', [MemberController::class, 'store'])->name('sites.members.store');
    Route::delete('sites/{site}/members/{user}', [MemberController::class, 'destroy'])->name('sites.members.destroy');
    Route::get('sites/{site}/scans/{scan}', [SiteController::class, 'scan'])->name('sites.scans.show');
    Route::post('sites/{site}/scans', [SiteController::class, 'startScan'])->name('sites.scans.store');
    Route::get('sites/{site}/settings', [SiteController::class, 'edit'])->name('sites.edit');
    Route::put('sites/{site}', [SiteController::class, 'update'])->name('sites.update');
    Route::delete('sites/{site}', [SiteController::class, 'destroy'])->name('sites.destroy');
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';

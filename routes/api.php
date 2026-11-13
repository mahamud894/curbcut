<?php

use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Controllers\ReportController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware(['auth:sanctum', 'throttle:api'])->scopeBindings()->group(function () {
    Route::get('me', [ApiController::class, 'me']);
    Route::get('sites', [ApiController::class, 'sites']);
    Route::get('sites/{site}', [ApiController::class, 'site']);
    Route::get('sites/{site}/scans', [ApiController::class, 'scans']);
    Route::post('sites/{site}/scans', [ApiController::class, 'startScan']);
    Route::get('sites/{site}/scans/{scan}', [ApiController::class, 'scan']);
    Route::get('sites/{site}/issues', [ApiController::class, 'issues']);
    Route::get('sites/{site}/issues/{issue}', [ApiController::class, 'issue']);
    Route::get('sites/{site}/pages', [ApiController::class, 'pages']);
    Route::get('sites/{site}/report', [ReportController::class, 'show']);
    Route::post('sites/{site}/checks', [ApiController::class, 'startCheck']);
    Route::get('sites/{site}/checks/{check}', [ApiController::class, 'check']);
});

<?php

declare(strict_types=1);

use App\Http\Controllers\Install\InstallController;
use Illuminate\Support\Facades\Route;

Route::prefix('install')->name('install.')->group(function () {
    Route::get('/',              [InstallController::class, 'index'])->name('index');
    Route::get('/step/{step}',   [InstallController::class, 'show'])->name('step');
    Route::post('/step/{step}',  [InstallController::class, 'process'])->name('process');
    Route::get('/complete',      [InstallController::class, 'complete'])->name('complete');
});

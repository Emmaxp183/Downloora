<?php

use App\Http\Controllers\Admin\TorrentController as AdminTorrentController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LibraryController;
use App\Http\Controllers\StoredFileAccessController;
use App\Http\Controllers\TorrentController;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

Route::inertia('/', 'Welcome', [
    'canRegister' => Features::enabled(Features::registration()),
])->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');
    Route::post('torrents', [TorrentController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('torrents.store');
    Route::get('library', [LibraryController::class, 'index'])->name('library.index');
    Route::get('files/{storedFile}/download', [StoredFileAccessController::class, 'download'])
        ->middleware('signed')
        ->name('files.download');
    Route::get('files/{storedFile}/stream', [StoredFileAccessController::class, 'stream'])
        ->middleware('signed')
        ->name('files.stream');
});

Route::prefix('admin')
    ->name('admin.')
    ->middleware(['auth', 'verified', 'admin'])
    ->group(function () {
        Route::get('users', [AdminUserController::class, 'index'])->name('users.index');
        Route::patch('users/{user}/quota', [AdminUserController::class, 'updateQuota'])->name('users.quota.update');
        Route::get('torrents', [AdminTorrentController::class, 'index'])->name('torrents.index');
        Route::delete('torrents/{torrent}', [AdminTorrentController::class, 'destroy'])->name('torrents.destroy');
    });

require __DIR__.'/settings.php';

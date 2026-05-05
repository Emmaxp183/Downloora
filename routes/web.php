<?php

use App\Http\Controllers\Admin\TorrentController as AdminTorrentController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\BillingController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\GoogleAuthController;
use App\Http\Controllers\LibraryController;
use App\Http\Controllers\MediaFolderAccessController;
use App\Http\Controllers\MediaImportController;
use App\Http\Controllers\StoredFileAccessController;
use App\Http\Controllers\TorrentController;
use App\Http\Controllers\TorrentFolderAccessController;
use App\Http\Controllers\WishlistItemController;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

Route::inertia('/', 'Welcome', [
    'canRegister' => Features::enabled(Features::registration()),
])->name('home');

Route::middleware('guest')->group(function () {
    Route::get('auth/google/redirect', [GoogleAuthController::class, 'redirect'])->name('auth.google.redirect');
    Route::get('auth/google/callback', [GoogleAuthController::class, 'callback'])->name('auth.google.callback');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');
    Route::post('billing/checkout/{plan}', [BillingController::class, 'checkout'])->name('billing.checkout');
    Route::get('billing/portal', [BillingController::class, 'portal'])->name('billing.portal');
    Route::get('billing/success', [BillingController::class, 'success'])->name('billing.success');
    Route::get('billing/cancel', [BillingController::class, 'cancel'])->name('billing.cancel');
    Route::post('torrents', [TorrentController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('torrents.store');
    Route::delete('torrents/{torrent}', [TorrentController::class, 'destroy'])->name('torrents.destroy');
    Route::post('media-imports/{mediaImport}/formats', [MediaImportController::class, 'storeFormat'])
        ->middleware('throttle:12,1')
        ->name('media-imports.formats.store');
    Route::delete('media-imports/{mediaImport}', [MediaImportController::class, 'destroy'])->name('media-imports.destroy');
    Route::post('wishlist', [WishlistItemController::class, 'store'])
        ->middleware('throttle:12,1')
        ->name('wishlist.store');
    Route::delete('wishlist/{wishlistItem}', [WishlistItemController::class, 'destroy'])->name('wishlist.destroy');
    Route::get('library', [LibraryController::class, 'index'])->name('library.index');
    Route::get('files/{storedFile}/download', [StoredFileAccessController::class, 'download'])
        ->middleware('signed')
        ->name('files.download');
    Route::get('files/{storedFile}/stream', [StoredFileAccessController::class, 'stream'])
        ->middleware('signed')
        ->name('files.stream');
    Route::delete('files/{storedFile}', [StoredFileAccessController::class, 'destroy'])->name('files.destroy');
    Route::get('folders/{torrent}/download', [TorrentFolderAccessController::class, 'download'])
        ->middleware('signed')
        ->name('folders.download');
    Route::delete('folders/{torrent}', [TorrentFolderAccessController::class, 'destroy'])->name('folders.destroy');
    Route::get('media-folders/{mediaImport}/download', [MediaFolderAccessController::class, 'download'])
        ->middleware('signed')
        ->name('media-folders.download');
    Route::delete('media-folders/{mediaImport}', [MediaFolderAccessController::class, 'destroy'])->name('media-folders.destroy');
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

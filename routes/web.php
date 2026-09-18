<?php

use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\AlertController;
use App\Http\Controllers\BriefingController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\IncidentController;
use App\Http\Controllers\IncidentNoteController;
use App\Http\Controllers\ItemController;
use App\Http\Controllers\SourceController;
use App\Http\Controllers\WatchlistTermController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard')->name('home');

Route::middleware(['auth', 'verified', 'two-factor'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    Route::get('items', [ItemController::class, 'index'])->name('items.index');
    Route::get('items/export', [ItemController::class, 'export'])->middleware('throttle:10,1')->name('items.export');
    Route::get('items/{item}/screenshot', [ItemController::class, 'screenshot'])->name('items.screenshot');

    Route::get('alerts', [AlertController::class, 'index'])->name('alerts.index');
    Route::get('incidents', [IncidentController::class, 'index'])->name('incidents.index');
    Route::get('incidents/{incident}', [IncidentController::class, 'show'])->name('incidents.show');
    Route::get('briefings', [BriefingController::class, 'index'])->name('briefings.index');
    Route::get('briefings/{briefing}', [BriefingController::class, 'show'])->name('briefings.show');

    Route::middleware('role:admin,analyst')->group(function () {
        Route::get('items/create', [ItemController::class, 'create'])->name('items.create');
        Route::post('items', [ItemController::class, 'store'])->name('items.store');
        Route::patch('items/{item}', [ItemController::class, 'update'])->name('items.update');

        Route::post('alerts/{alert}/acknowledge', [AlertController::class, 'acknowledge'])->name('alerts.acknowledge');

        Route::post('incidents', [IncidentController::class, 'store'])->name('incidents.store');
        Route::patch('incidents/{incident}', [IncidentController::class, 'update'])->name('incidents.update');
        Route::post('incidents/{incident}/items', [IncidentController::class, 'attach'])->name('incidents.items.attach');
        Route::delete('incidents/{incident}/items/{item}', [IncidentController::class, 'detach'])->name('incidents.items.detach');
        Route::post('incidents/{incident}/notes', [IncidentNoteController::class, 'store'])->name('incidents.notes.store');

        Route::post('briefings', [BriefingController::class, 'store'])->middleware('throttle:5,60')->name('briefings.store');

        Route::get('sources', [SourceController::class, 'index'])->name('sources.index');
        Route::post('sources', [SourceController::class, 'store'])->name('sources.store');
        Route::put('sources/{source}', [SourceController::class, 'update'])->name('sources.update');
        Route::delete('sources/{source}', [SourceController::class, 'destroy'])->name('sources.destroy');
        Route::post('sources/{source}/fetch', [SourceController::class, 'fetch'])->middleware('throttle:20,1')->name('sources.fetch');

        Route::get('watchlist', [WatchlistTermController::class, 'index'])->name('watchlist.index');
        Route::post('watchlist', [WatchlistTermController::class, 'store'])->name('watchlist.store');
        Route::delete('watchlist/{term}', [WatchlistTermController::class, 'destroy'])->name('watchlist.destroy');
    });

    Route::middleware('role:admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('users', [UserController::class, 'index'])->name('users.index');
        Route::post('users', [UserController::class, 'store'])->name('users.store');
        Route::patch('users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::delete('users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
        Route::get('audit', [AuditLogController::class, 'index'])->name('audit.index');
    });
});

require __DIR__.'/settings.php';

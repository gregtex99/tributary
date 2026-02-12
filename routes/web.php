<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Web\ActionItemPageController;
use App\Http\Controllers\Web\DashboardPageController;
use App\Http\Controllers\Web\MonitorController;
use App\Http\Controllers\Web\NudgePageController;
use App\Http\Controllers\Web\SettingsPageController;
use Illuminate\Support\Facades\Route;

// Public monitoring dashboard (token-protected)
Route::get('/dashboard', MonitorController::class)->name('monitor');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/', DashboardPageController::class)->name('dashboard');
    // /dashboard is now the public monitor route

    Route::get('/items', [ActionItemPageController::class, 'index'])->name('items.index');
    Route::get('/items/{item}', [ActionItemPageController::class, 'show'])->name('items.show');
    Route::patch('/items/{item}', [ActionItemPageController::class, 'update'])->name('items.update');
    Route::post('/items/{item}/transition', [ActionItemPageController::class, 'transition'])->name('items.transition');

    Route::get('/nudges', [NudgePageController::class, 'index'])->name('nudges.index');
    Route::post('/nudges/{item}/draft', [NudgePageController::class, 'draft'])->name('nudges.draft');
    Route::patch('/nudges/{nudge}', [NudgePageController::class, 'updateDraft'])->name('nudges.update');
    Route::post('/nudges/{nudge}/approve', [NudgePageController::class, 'approve'])->name('nudges.approve');
    Route::post('/nudges/{nudge}/skip', [NudgePageController::class, 'skip'])->name('nudges.skip');
    Route::post('/nudges/{nudge}/sent', [NudgePageController::class, 'sent'])->name('nudges.sent');

    Route::get('/settings', [SettingsPageController::class, 'index'])->name('settings.index');
    Route::post('/settings/rules', [SettingsPageController::class, 'store'])->name('settings.rules.store');
    Route::delete('/settings/rules/{rule}', [SettingsPageController::class, 'destroy'])->name('settings.rules.destroy');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';

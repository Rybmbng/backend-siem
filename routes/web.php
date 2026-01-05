<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\RuleController;
use App\Http\Controllers\Api\AgentController;
use App\Http\Controllers\NotificationController;

Route::get('/', function () {
    return redirect()->route('login');
});
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', [AgentController::class, 'dashboard'])->name('dashboard');    
    Route::get('/rules', [RuleController::class, 'index'])->name('rules.index');
    Route::post('/rules', [RuleController::class, 'store'])->name('rules.store');
    Route::get('/rules/toggle/{id}', [RuleController::class, 'toggle'])->name('rules.toggle');
    Route::delete('/rules/{id}', [RuleController::class, 'destroy'])->name('rules.delete');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/update', [NotificationController::class, 'updateSettings'])->name('notifications.update');
    Route::post('/notifications/test-wa', [NotificationController::class, 'testWhatsApp'])->name('notifications.test.wa');
    Route::post('/notifications/test-telegram', [NotificationController::class, 'testTelegram'])->name('notifications.test.telegram');
});
require __DIR__.'/auth.php';

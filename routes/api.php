<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AgentController; 

Route::post('/agent/register',  [AgentController::class, 'register']);
Route::post('/agent/heartbeat', [AgentController::class, 'heartbeat']);
Route::post('/agent/metrics',   [AgentController::class, 'storeMetrics']);
Route::post('/agent/log',       [AgentController::class, 'storeLog']);
Route::get('/agent/blacklist',  [AgentController::class, 'getBlacklist']);


Route::get('/dashboard/stats',     [AgentController::class, 'getDashboardStats']);
Route::get('/dashboard/logs',      [AgentController::class, 'getLatestLogs']);
Route::get('/dashboard/blacklist', [AgentController::class, 'getDashboardBlacklist']); // List tabel
Route::post('/dashboard/block',    [AgentController::class, 'manualBlock']);   // Tombol Block
Route::post('/dashboard/unblock',  [AgentController::class, 'manualUnblock']); // Tombol Unblock
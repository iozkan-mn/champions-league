<?php

use App\Http\Controllers\SimulationController;
use App\Http\Controllers\HomeController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');
Route::post('/games/generate-fixtures', [SimulationController::class, 'generate'])->name('games.generate-fixtures');
Route::post('/games/simulate-week', [SimulationController::class, 'simulateWeek'])->name('games.simulate-week');
Route::post('/games/reset-data', [SimulationController::class, 'reset'])->name('games.reset-data');
Route::post('/games/play-all-weeks', [SimulationController::class, 'simulateAll'])->name('games.play-all-weeks');

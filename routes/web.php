<?php

use App\Http\Controllers\GameController;
use App\Http\Controllers\HomeController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');
Route::post('/games/generate-fixtures', [GameController::class, 'generateFixtures'])->name('games.generate-fixtures');
Route::post('/games/simulate-week', [GameController::class, 'simulateWeek'])->name('games.simulate-week');
Route::post('/games/reset-data', [GameController::class, 'resetData'])->name('games.reset-data');
Route::post('/games/play-all-weeks', [GameController::class, 'playAllWeeks'])->name('games.play-all-weeks');

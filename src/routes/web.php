<?php

use App\Http\Controllers\PublicController;
use Illuminate\Support\Facades\Route;

Route::get('/', [PublicController::class, 'home']);
Route::get('/karya/{slug}', [PublicController::class, 'show']);
Route::post('/karya/{slug}/kolaborasi', [PublicController::class, 'inquire'])->middleware('throttle:3,10'); // 3 per 10 menit per IP

use App\Http\Controllers\Auth\LoginController;

Route::get('/masuk', [LoginController::class, 'show'])->name('login');
Route::post('/masuk', [LoginController::class, 'store'])->middleware('throttle:10,1');
Route::post('/keluar', [LoginController::class, 'destroy'])->name('logout')->middleware('auth');

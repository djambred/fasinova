<?php

use App\Http\Controllers\PublicController;
use Illuminate\Support\Facades\Route;

Route::get('/', [PublicController::class, 'home']);
Route::get('/karya/{slug}', [PublicController::class, 'show']);
Route::get('/pembimbing', [PublicController::class, 'lecturerIndex']);
Route::get('/pembimbing/{key}', [PublicController::class, 'lecturer'])->where('key', '[0-9]+(-[a-z0-9-]*)?');
Route::post('/karya/{slug}/kolaborasi', [PublicController::class, 'inquire'])->middleware('throttle:3,10'); // 3 per 10 menit per IP

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;

Route::get('/masuk', [LoginController::class, 'show'])->name('login');
Route::post('/masuk', [LoginController::class, 'store'])->middleware('throttle:10,1');
Route::get('/daftar', [RegisterController::class, 'show'])->name('register');
Route::post('/daftar', [RegisterController::class, 'store'])->middleware('throttle:10,10');   // 10 per 10 menit per IP (salah ketik tetap terhitung)
Route::post('/keluar', [LoginController::class, 'destroy'])->name('logout')->middleware('auth');

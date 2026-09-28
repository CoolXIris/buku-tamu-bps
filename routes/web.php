<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\GuestController;

Route::get('/', [GuestController::class, 'index'])->name('guests.index');
Route::post('/kunjungan', [GuestController::class, 'store'])->name('guests.store');
Route::get('/struk/{visitorEntry}', [GuestController::class, 'receipt'])->name('guests.receipt');

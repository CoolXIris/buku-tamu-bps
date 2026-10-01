<?php

use App\Http\Controllers\AdminAuthController;
use App\Http\Controllers\AdminDashboardController;
use App\Http\Controllers\AdminGuestController;
use App\Http\Controllers\GuestController;
use App\Http\Controllers\QueueScreenController;
use Illuminate\Support\Facades\Route;

Route::get('/', [GuestController::class, 'index'])->name('guests.index');
Route::post('/kunjungan', [GuestController::class, 'store'])->name('guests.store');
Route::get('/struk/{visitorEntry}', [GuestController::class, 'receipt'])->name('guests.receipt');
Route::get('/adminbps-tamu/layar-antrean', [QueueScreenController::class, 'index'])->name('queue.screen');
Route::get('/adminbps-tamu/layar-antrean/data', [QueueScreenController::class, 'data'])->name('queue.data');

Route::prefix('adminbps-tamu')->name('admin.')->group(function () {
    Route::get('/', [AdminDashboardController::class, 'home'])->name('home');
    Route::get('/login', [AdminAuthController::class, 'showLogin'])->name('login');
    Route::get('/google/redirect', [AdminAuthController::class, 'redirectToGoogle'])->name('google.redirect');
    Route::get('/google/callback', [AdminAuthController::class, 'handleGoogleCallback'])->name('google.callback');
    Route::middleware('auth')->group(function () {
        Route::post('/logout', [AdminAuthController::class, 'logout'])->name('logout');
        Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');
        Route::get('/daftar-tamu', [AdminGuestController::class, 'index'])->name('guests.index');
        Route::get('/daftar-tamu/export', [AdminGuestController::class, 'export'])->name('guests.export');
        Route::get('/daftar-tamu/{visitorEntry}', [AdminGuestController::class, 'show'])->name('guests.show');
        Route::patch('/daftar-tamu/{visitorEntry}', [AdminGuestController::class, 'update'])->name('guests.update');
        Route::delete('/daftar-tamu/{visitorEntry}', [AdminGuestController::class, 'destroy'])->name('guests.destroy');
        Route::patch('/daftar-tamu/{visitorEntry}/status', [AdminGuestController::class, 'updateStatus'])->name('guests.status');
    });
});

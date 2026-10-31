<?php

use App\Http\Controllers\Auth\GoogleAuthController;
use App\Http\Controllers\Auth\MicrosoftAuthController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\SessionController;
use App\Http\Controllers\ProfileAvatarController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/auth/google', [GoogleAuthController::class, 'redirect'])->name('auth.google');
Route::get('/auth/google/callback', [GoogleAuthController::class, 'callback'])->name('auth.google.callback');
Route::get('/auth/microsoft', [MicrosoftAuthController::class, 'redirect'])->name('auth.microsoft');
Route::get('/integrations/microsoft/connect', [MicrosoftAuthController::class, 'redirect'])
    ->middleware('auth')->name('integrations.microsoft.connect');
Route::get('/auth/microsoft/callback', [MicrosoftAuthController::class, 'callback'])->name('auth.microsoft.callback');
Route::post('/logout', [SessionController::class, 'destroy'])->name('logout');
Route::post('/profile/avatar/microsoft', [ProfileAvatarController::class, 'microsoft'])->middleware(['auth', 'throttle:6,1']);
Route::post('/profile/avatar', [ProfileAvatarController::class, 'update'])
    ->middleware('auth')
    ->name('profile.avatar.update');
Route::delete('/profile/avatar', [ProfileAvatarController::class, 'destroy'])
    ->middleware('auth')
    ->name('profile.avatar.destroy');
Route::patch('/profile', [ProfileController::class, 'update'])
    ->middleware('auth')
    ->name('profile.update');

Route::view('/forgot-password', 'app')->middleware('guest')->name('password.request');
Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])
    ->middleware('guest')
    ->name('password.email');
Route::view('/reset-password/{token}', 'app')->middleware('guest')->name('password.reset');
Route::post('/reset-password', [NewPasswordController::class, 'store'])
    ->middleware('guest')
    ->name('password.update');

Route::view('/', 'app')->name('login');
Route::view('/first-access/{token?}', 'app');
Route::view('/accept-invitation/{token}', 'app');
Route::view('/estoque/{path?}', 'app')->where('path', '.*');
Route::fallback(fn () => abort(404));

<?php

use App\Http\Controllers\Auth\VerifiedUserController;
use App\Http\Controllers\FriendController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use Inertia\Inertia;

Route::get('/', fn () => redirect()->route('dashboard'))->middleware('guest')->name('welcome');
Route::get('/welcome', fn () => Inertia::render('Welcome'))->name('landing');

Route::post('/reactivate', [ProfileController::class, 'reactivate'])
    ->middleware('guest')->name('reactivate');

Route::middleware(['auth', 'verified'])->group(function () {
    // Feed
    Route::get('/dashboard', fn (Request $r) => Inertia::render('Dashboard'))->name('dashboard');
    Route::get('/api/feed', [PostController::class, 'feed'])->name('feed');

    // Profiles
    Route::get('/profile/{user}', [ProfileController::class, 'show'])->name('profile.show');
    Route::get('/profile', [ProfileController::class, 'editSettings'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::post('/profile/deactivate', [ProfileController::class, 'deactivate'])->name('profile.deactivate');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Friends
    Route::get('/friends', [FriendController::class, 'index'])->name('friends.index');
    Route::post('/friends/{user}/request', [FriendController::class, 'request'])->name('friends.request');
    Route::post('/friends/{user}/accept', [FriendController::class, 'accept'])->name('friends.accept');
    Route::post('/friends/{user}/decline', [FriendController::class, 'decline'])->name('friends.decline');
    Route::post('/friends/{user}/cancel', [FriendController::class, 'cancel'])->name('friends.cancel');
    Route::post('/friends/{user}/unfriend', [FriendController::class, 'unfriend'])->name('friends.unfriend');
    Route::post('/users/{user}/follow', [FriendController::class, 'toggleFollow'])->name('users.follow');
    Route::post('/users/{user}/block', [FriendController::class, 'toggleBlock'])->name('users.block');
    Route::get('/settings/blocked', [FriendController::class, 'blockedIndex'])->name('blocked.index');
});

require __DIR__.'/auth.php';

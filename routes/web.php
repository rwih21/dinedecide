<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\RestaurantController;
use App\Http\Controllers\Admin\PromotedPlaceController;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('/search', [RestaurantController::class, 'index'])->name('restaurants.index');
    Route::post('/search', [RestaurantController::class, 'search'])->name('restaurants.search');
    Route::get('/browse', [RestaurantController::class, 'browse'])->name('restaurants.browse');
});

// Add temporarily to web.php, remove after testing
Route::get('/clear-cache', function () {
    session()->forget(['nearby_places', 'nearby_cached_at']);
    return 'Cache cleared!';
})->middleware('auth');

// Admin routes
Route::middleware(['auth', 'is_admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/promoted', [PromotedPlaceController::class, 'index'])->name('promoted.index');
    Route::get('/promoted/create', [PromotedPlaceController::class, 'create'])->name('promoted.create');
    Route::post('/promoted', [PromotedPlaceController::class, 'store'])->name('promoted.store');
    Route::get('/promoted/{promoted}/edit', [PromotedPlaceController::class, 'edit'])->name('promoted.edit');
    Route::put('/promoted/{promoted}', [PromotedPlaceController::class, 'update'])->name('promoted.update');
    Route::delete('/promoted/{promoted}', [PromotedPlaceController::class, 'destroy'])->name('promoted.destroy');
    Route::patch('/promoted/{promoted}/toggle', [PromotedPlaceController::class, 'toggle'])->name('promoted.toggle');
});
require __DIR__.'/auth.php';

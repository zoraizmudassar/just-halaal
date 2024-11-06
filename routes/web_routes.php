<?php

use App\Http\Controllers\Web\HomeController;
use Illuminate\Support\Facades\Route;

Route::get('/home', [HomeController::class, 'index'])->name('home');
Route::get('/shop', function () {
    return view('web.shop.index');
});
Route::get('/product/{id}', [HomeController::class, 'show'])->name('home.show');

// Add more routes as needed

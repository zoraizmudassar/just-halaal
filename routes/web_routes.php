<?php

use App\Http\Controllers\web\OrderController;
use App\Http\Controllers\web\ProfileController;
use App\Http\Controllers\web\AuthController;
use App\Http\Controllers\web\CartController;
use App\Http\Controllers\Web\HomeController;
use App\Http\Controllers\web\stripe\StripeController;
use Illuminate\Support\Facades\Route;


// Pages Routes
Route::get('/home', [HomeController::class, 'index'])->name('home.index');

// Product Routes
Route::get('/product/{id}', [HomeController::class, 'show'])->name('home.show');
Route::get('/shop/{id}', [HomeController::class, 'restaurant'])->name('home.restaurant');

// Cart Routes
Route::post('/cart/add', [CartController::class, 'add'])->name('cart.add');
Route::post('/cart/update', [CartController::class, 'updateCart'])->name('cart.update');
Route::post('/cart/remove', [CartController::class, 'removeFromCart'])->name('cart.remove');
Route::get('/cart', [CartController::class, 'show'])->name('cart.show');
Route::get('/checkout', function(){
    return view('web.checkout.index');
});

// Auth Routes
Route::post('/register', [AuthController::class, 'register'])->name('register');
Route::post('/login', [AuthController::class, 'login'])->name('login');
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

// Profile Routes
Route::get('/user/profile', [ProfileController::class, 'show'])->name('profile.show');
Route::post('/profile/update', [ProfileController::class, 'update'])->name('profile.update');
Route::get('/user/orders', [OrderController::class, 'index'])->name('user.orders');

// Stripe Routes
Route::get('stripe', [StripeController::class, 'index']);
Route::post('stripe', [StripeController::class, 'processPayment'])->name('stripe.process');
Route::get('/payment', [StripeController::class, 'showPaymentForm'])->name('payment.form');
Route::post('/payment', [StripeController::class, 'processPayment'])->name('payment.process');

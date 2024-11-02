<?php

use Illuminate\Support\Facades\Route;

Route::get('/home', function () {
    return view('web.home.index');
});

Route::get('/contact', function () {
    return view('contact');
});

// Add more routes as needed

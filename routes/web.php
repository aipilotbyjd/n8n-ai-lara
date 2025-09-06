<?php

use Illuminate\Support\Facades\Route;

// Swagger API Documentation Routes
Route::get('/api/documentation', function () {
    return view('vendor.l5-swagger.index');
});

Route::get('/', function () {
    return view('welcome');
});

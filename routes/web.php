<?php

use Illuminate\Support\Facades\Route;

// Swagger API Documentation Routes
// L5-Swagger package handles its own routes automatically

Route::get('/', function () {
    return view('welcome');
});

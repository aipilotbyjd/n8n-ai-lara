<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/workflow-canvas', function () {
    return view('workflow-canvas');
});

<?php

use Illuminate\Support\Facades\Route;

// Serve React App untuk root
Route::get('/', function () {
    $path = public_path('index.html');
    
    if (!file_exists($path)) {
        abort(404, 'index.html not found');
    }
    
    return response()->file($path);
});

// Serve React App untuk semua route yang bukan API
Route::get('/{any}', function () {
    $path = public_path('index.html');
    
    if (!file_exists($path)) {
        abort(404, 'index.html not found');
    }
    
    return response()->file($path);
})->where('any', '^(?!api).*$');
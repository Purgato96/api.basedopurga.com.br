<?php

use Illuminate\Support\Facades\Route;

// =====================
// Apenas rotas básicas - SEM INERTIA
// =====================

// Página inicial básica (opcional)
Route::get('/', function () {
    return view('welcome');
});

// Health check
Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'timestamp' => now(),
        'database' => \DB::connection()->getPdo() ? 'connected' : 'disconnected'
    ]);
});

// CORS Preflight para desenvolvimento
Route::options('/{any}', function () {
    return response('', 200);
})->where('any', '.*');

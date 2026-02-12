<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'app' => config('app.name'),
        'timestamp' => now()->toIso8601String(),
    ]);
});

Route::middleware('api.token')->group(function () {
    Route::get('/user', function (Request $request) {
        return response()->json([
            'message' => 'Authorized API token.',
            'ip' => $request->ip(),
        ]);
    });
});

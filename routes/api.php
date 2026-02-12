<?php

use App\Http\Controllers\Api\ActionItemController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\NudgeController;
use App\Http\Controllers\Api\RuleController;
use App\Http\Controllers\Api\SignalController;
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

    Route::get('/items', [ActionItemController::class, 'index']);
    Route::post('/items', [ActionItemController::class, 'store']);
    Route::get('/items/{id}', [ActionItemController::class, 'show']);
    Route::patch('/items/{id}', [ActionItemController::class, 'update']);
    Route::delete('/items/{id}', [ActionItemController::class, 'destroy']);
    Route::get('/items/{id}/transitions', [ActionItemController::class, 'transitions']);

    Route::get('/signals', [SignalController::class, 'index']);
    Route::post('/signals', [SignalController::class, 'store']);

    Route::get('/nudges/pending', [NudgeController::class, 'pending']);
    Route::post('/nudges/{itemId}/draft', [NudgeController::class, 'draft']);
    Route::post('/nudges/{itemId}/approve', [NudgeController::class, 'approve']);
    Route::post('/nudges/{itemId}/sent', [NudgeController::class, 'sent']);

    Route::get('/rules', [RuleController::class, 'index']);
    Route::post('/rules', [RuleController::class, 'store']);
    Route::delete('/rules/{id}', [RuleController::class, 'destroy']);

    Route::get('/dashboard', DashboardController::class);
});

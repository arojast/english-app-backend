<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\WordController;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->get('/profile', function (Request $request) {
    return $request->user();
});

Route::middleware('auth:sanctum')->get('/me', function (Request $request) {
    return $request->user();
});

Route::middleware('auth:sanctum')->post('/logout', [AuthController::class, 'logout']);

// Word routes
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/words', [WordController::class, 'store']);
    Route::get('/words/last', [WordController::class, 'last']);
    Route::get('/words', [WordController::class, 'findAll']);
    Route::get('/words/random-user', [WordController::class, 'randomUser']);
    Route::patch('/words/{id}/learned', [WordController::class, 'updateLearned']);
    Route::patch('/words/{id}/favorite', [WordController::class, 'updateFavorite']);
});

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

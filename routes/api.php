<?php

use Illuminate\Http\Request;
use App\Http\Controllers\Api\AuthController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ProjectController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::get('/projects/{project}', [ProjectController::class, 'show'])
    ->middleware('auth:sanctum');
    
Route::post('/register', [AuthController::class, 'register']);
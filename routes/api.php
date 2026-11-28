
<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ProjectController;
use App\Http\Controllers\Api\TaskController;

Route::post('/register', [AuthController::class, 'register']);

Route::middleware('auth:sanctum')->group(function () {

    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    Route::get('/projects', [ProjectController::class, 'index']);

    Route::post('/projects', [ProjectController::class, 'store']);

    Route::get('/projects/{project}', [ProjectController::class, 'show']);
    Route::put('/projects/{project}', [ProjectController::class, 'update']);
    Route::patch('/projects/{project}', [ProjectController::class, 'update']);
    Route::delete('/projects/{project}', [ProjectController::class, 'destroy']);
    Route::get('/projects/{project}/members', [ProjectController::class, 'members']);
    Route::post(
    '/projects/{project}/members',
    [ProjectController::class, 'addMember']
);
Route::patch(
    '/projects/{project}/members/{user}',
    [ProjectController::class, 'updateMemberRole']
);
	Route::delete(
    '/projects/{project}/members/{user}',
    [ProjectController::class, 'removeMember']
);
Route::post('/projects/{project}/tasks', [TaskController::class, 'store']);

});
Route::patch(
    '/projects/{project}/tasks/{task}',
    [TaskController::class, 'update']
);
Route::patch(
    '/projects/{project}/tasks/{task}/status',
    [TaskController::class, 'updateStatus']
);
Route::delete(
    '/projects/{project}/tasks/{task}',
    [TaskController::class, 'destroy']
);
Route::get(
    '/projects/{project}/tasks',
    [TaskController::class, 'index']
);
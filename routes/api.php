<?php

use App\Http\Controllers\AcademicTermController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CourseController;
use App\Http\Controllers\ProgramController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');


Route::prefix('/v1')->group(function () {

    Route::prefix('/auth')->name('auth.')->group(function () {
        Route::post('/login', [AuthController::class, 'login'])->name('login');

        Route::middleware('auth:sanctum')->group(function () {
            Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
                
            Route::get('/me', [AuthController::class, 'me'])->name('me'); 
        }); 
    });

    Route::apiResource('programs', ProgramController::class)->middleware('auth:sanctum');
    Route::apiResource('courses', CourseController::class)->middleware('auth:sanctum');
    Route::apiResource('academic-terms', AcademicTermController::class)->middleware('auth:sanctum');
    
}); 
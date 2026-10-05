<?php

use App\Http\Controllers\AcademicRecordController;
use App\Http\Controllers\AcademicTermController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BulkGradeController;
use App\Http\Controllers\CourseController;
use App\Http\Controllers\CourseOfferingController;
use App\Http\Controllers\CourseOfferingStudentController;
use App\Http\Controllers\EnrollmentController;
use App\Http\Controllers\GradeController;
use App\Http\Controllers\ProgramController;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\StudentEnrollmentController;
use App\Http\Controllers\StudentGradeController;
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
    Route::apiResource('students', StudentController::class)->middleware('auth:sanctum');
    Route::apiResource('course-offerings', CourseOfferingController::class)->middleware('auth:sanctum');
    Route::apiResource('enrollments', EnrollmentController::class)->middleware('auth:sanctum');

    // Grading
    Route::apiResource('grades', GradeController::class)->middleware('auth:sanctum');
    Route::put('course-offerings/{course_offering}/grades', [BulkGradeController::class, 'update'])->middleware('auth:sanctum');

    // Nested Student Routes
    Route::get('students/{student}/grades', [StudentGradeController::class, 'index'])->middleware('auth:sanctum');
    Route::get('students/{student}/academic-record', [AcademicRecordController::class, 'show'])->middleware('auth:sanctum');
    Route::get('students/{student}/enrollments', [StudentEnrollmentController::class, 'index'])->middleware('auth:sanctum');

    // Nested Course Offering Routes
    Route::get('course-offerings/{course_offering}/students', [CourseOfferingStudentController::class, 'index'])->middleware('auth:sanctum');

});

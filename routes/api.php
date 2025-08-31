<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Middleware\EnsureUserHasRole;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ApiAuthController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\CourseController;
use App\Http\Controllers\EligibleCoursesController;
use App\Http\Controllers\SelectedCoursesController;
use App\Http\Controllers\AcademicRecordController;
use App\Http\Controllers\DataImportController;
// Public authentication routes
Route::post('/register', [RegisteredUserController::class, 'store'])->name('register');
Route::post('/login', [ApiAuthController::class, 'login']);
Route::post('/import/pharmacy-students', [DataImportController::class, 'importPharmacyStudentRecords']);
// Authenticated routes
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });
    Route::get('/student/eligible-courses', [EligibleCoursesController::class, 'getEligibleCourses']);
    Route::get('/student/selected-courses', [SelectedCoursesController::class, 'index']);
    Route::post('/student/selected-courses', [SelectedCoursesController::class, 'store']);
    Route::get('/student/grades', [AcademicRecordController::class, 'getGrades']);
    Route::get('/student/failed-courses', [AcademicRecordController::class, 'getFailedCourses']);
    Route::get('/student/graduation-status', [AcademicRecordController::class, 'getGraduationStatus']);

    Route::middleware('auth:sanctum')->post('/logout', [ApiAuthController::class, 'logout'])->name('logout');
    // Admin-only test route
    Route::middleware(EnsureUserHasRole::class . ':admin')->get('/admin/test', function () {
        return response()->json(['message' => 'Welcome, Admin!']);
    });
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/profile', [ProfileController::class, 'show']);
    Route::put('/profile', [ProfileController::class, 'update']);
});

Route::middleware('auth:sanctum')->group(function () {
    // List all courses, view a course (all users)
    Route::get('/courses', [CourseController::class, 'index']);
    Route::get('/courses/{id}', [CourseController::class, 'show']);

    // Admin-only: create, update, delete
    Route::post('/courses', [CourseController::class, 'store']);
    Route::put('/courses/{id}', [CourseController::class, 'update']);
    Route::delete('/courses/{id}', [CourseController::class, 'destroy']);
    Route::get('/study-plans', [\App\Http\Controllers\StudyPlanController::class, 'index']);
    Route::get('/admin/study-plans', [\App\Http\Controllers\StudyPlanController::class, 'allPlans']);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/study-plans', [\App\Http\Controllers\StudyPlanController::class, 'store']);
    Route::put('/study-plans/{id}', [\App\Http\Controllers\StudyPlanController::class, 'update']);
    Route::delete('/study-plans/{id}', [\App\Http\Controllers\StudyPlanController::class, 'destroy']);
    Route::post('/study-plans/{id}/courses', [\App\Http\Controllers\StudyPlanController::class, 'addCourse']);
    Route::delete('/study-plans/{id}/courses/{course_id}', [\App\Http\Controllers\StudyPlanController::class, 'removeCourse']);
});
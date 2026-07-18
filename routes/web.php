<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Core\AuthController;
use App\Http\Controllers\Core\DashboardController;
use App\Http\Controllers\Core\UserController;
use App\Http\Controllers\Core\DepartmentController;
use App\Http\Controllers\Core\DeveloperPanelController;
use App\Http\Controllers\Core\PositionController;
use App\Http\Controllers\Core\EmployeeController;
use App\Http\Controllers\Core\TeacherController;
use App\Http\Controllers\Core\ProgramController;
use App\Http\Controllers\Core\BatchController;
use App\Http\Controllers\Core\ClassController;
use App\Http\Controllers\Core\StudentController;
use App\Http\Controllers\Core\CompanyController;
use App\Http\Controllers\Core\ModuleController;
use App\Http\Controllers\Core\PermissionController;
use App\Http\Controllers\Core\JobOrderController;
use App\Http\Controllers\Core\InterviewController;
use App\Http\Controllers\Core\MatchingController;
use App\Http\Controllers\Core\ApplicationController;
use App\Http\Controllers\Core\DocumentController;
use App\Http\Controllers\Core\CoeController;
use App\Http\Controllers\Core\VisaController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

Route::get('/', function () {
    return redirect()->route('login');
});

Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// Requires authentication
Route::middleware('auth')->group(function () {
    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    
    // Developer Panel
    Route::get('/developer-panel', [DeveloperPanelController::class, 'index'])->name('developer-panel.index');

    // User Management
    Route::prefix('users')->name('users.')->group(function () {
        Route::get('/', [UserController::class, 'index'])->name('index');
        Route::get('/create', [UserController::class, 'create'])->name('create');
        Route::post('/', [UserController::class, 'store'])->name('store');
        Route::get('/{id}/edit', [UserController::class, 'edit'])->name('edit');
        Route::put('/{id}', [UserController::class, 'update'])->name('update');
        Route::delete('/{id}', [UserController::class, 'destroy'])->name('destroy');
    });

    // Department Management
    Route::prefix('departments')->name('departments.')->group(function () {
        Route::get('/', [DepartmentController::class, 'index'])->name('index');
        Route::get('/create', [DepartmentController::class, 'create'])->name('create');
        Route::post('/', [DepartmentController::class, 'store'])->name('store');
        Route::get('/{id}', [DepartmentController::class, 'show'])->name('show');
        Route::get('/{id}/edit', [DepartmentController::class, 'edit'])->name('edit');
        Route::put('/{id}', [DepartmentController::class, 'update'])->name('update');
        Route::delete('/{id}', [DepartmentController::class, 'destroy'])->name('destroy');
    });

    // Position Management
    Route::prefix('positions')->name('positions.')->group(function () {
        Route::get('/', [PositionController::class, 'index'])->name('index');
        Route::get('/create', [PositionController::class, 'create'])->name('create');
        Route::post('/', [PositionController::class, 'store'])->name('store');
        Route::get('/{id}', [PositionController::class, 'show'])->name('show');
        Route::get('/{id}/edit', [PositionController::class, 'edit'])->name('edit');
        Route::put('/{id}', [PositionController::class, 'update'])->name('update');
        Route::delete('/{id}', [PositionController::class, 'destroy'])->name('destroy');
    });

    // Employee Management
    Route::prefix('employees')->name('employees.')->group(function () {
        Route::get('/', [EmployeeController::class, 'index'])->name('index');
        Route::get('/create', [EmployeeController::class, 'create'])->name('create');
        Route::post('/', [EmployeeController::class, 'store'])->name('store');
        Route::get('/{id}', [EmployeeController::class, 'show'])->name('show');
        Route::get('/{id}/edit', [EmployeeController::class, 'edit'])->name('edit');
        Route::put('/{id}', [EmployeeController::class, 'update'])->name('update');
        Route::delete('/{id}', [EmployeeController::class, 'destroy'])->name('destroy');
    });

    // Teacher Management
    Route::prefix('teachers')->name('teachers.')->group(function () {
        Route::get('/', [TeacherController::class, 'index'])->name('index');
        Route::get('/create', [TeacherController::class, 'create'])->name('create');
        Route::post('/', [TeacherController::class, 'store'])->name('store');
        Route::get('/{id}', [TeacherController::class, 'show'])->name('show');
        Route::get('/{id}/edit', [TeacherController::class, 'edit'])->name('edit');
        Route::put('/{id}', [TeacherController::class, 'update'])->name('update');
        Route::delete('/{id}', [TeacherController::class, 'destroy'])->name('destroy');
    });

    // Program Management
    Route::prefix('programs')->name('programs.')->group(function () {
        Route::get('/', [ProgramController::class, 'index'])->name('index');
        Route::get('/create', [ProgramController::class, 'create'])->name('create');
        Route::post('/', [ProgramController::class, 'store'])->name('store');
        Route::get('/{id}', [ProgramController::class, 'show'])->name('show');
        Route::get('/{id}/edit', [ProgramController::class, 'edit'])->name('edit');
        Route::put('/{id}', [ProgramController::class, 'update'])->name('update');
        Route::delete('/{id}', [ProgramController::class, 'destroy'])->name('destroy');
    });

    // Batch Management
    Route::prefix('batches')->name('batches.')->group(function () {
        Route::get('/', [BatchController::class, 'index'])->name('index');
        Route::get('/create', [BatchController::class, 'create'])->name('create');
        Route::post('/', [BatchController::class, 'store'])->name('store');
        Route::get('/{id}', [BatchController::class, 'show'])->name('show');
        Route::get('/{id}/edit', [BatchController::class, 'edit'])->name('edit');
        Route::put('/{id}', [BatchController::class, 'update'])->name('update');
        Route::delete('/{id}', [BatchController::class, 'destroy'])->name('destroy');
    });

    // Class Management
    Route::prefix('classes')->name('classes.')->group(function () {
        Route::get('/', [ClassController::class, 'index'])->name('index');
        Route::get('/create', [ClassController::class, 'create'])->name('create');
        Route::post('/', [ClassController::class, 'store'])->name('store');
        Route::get('/{id}', [ClassController::class, 'show'])->name('show');
        Route::get('/{id}/edit', [ClassController::class, 'edit'])->name('edit');
        Route::put('/{id}', [ClassController::class, 'update'])->name('update');
        Route::delete('/{id}', [ClassController::class, 'destroy'])->name('destroy');
    });

    // Student Management
    Route::prefix('students')->name('students.')->group(function () {
        Route::get('/', [StudentController::class, 'index'])->name('index');
        Route::get('/create', [StudentController::class, 'create'])->name('create');
        Route::post('/', [StudentController::class, 'store'])->name('store');
        Route::get('/{id}', [StudentController::class, 'show'])->name('show');
        Route::get('/{id}/edit', [StudentController::class, 'edit'])->name('edit');
        Route::put('/{id}', [StudentController::class, 'update'])->name('update');
        Route::delete('/{id}', [StudentController::class, 'destroy'])->name('destroy');
    });

    // Company Management
    Route::prefix('companies')->name('companies.')->group(function () {
        Route::get('/', [CompanyController::class, 'index'])->name('index');
        Route::get('/create', [CompanyController::class, 'create'])->name('create');
        Route::post('/', [CompanyController::class, 'store'])->name('store');
        Route::get('/{id}', [CompanyController::class, 'show'])->name('show');
        Route::get('/{id}/edit', [CompanyController::class, 'edit'])->name('edit');
        Route::put('/{id}', [CompanyController::class, 'update'])->name('update');
        Route::delete('/{id}', [CompanyController::class, 'destroy'])->name('destroy');
    });

    // Job Order Management
    Route::prefix('job-orders')->name('job-orders.')->group(function () {
        Route::get('/', [JobOrderController::class, 'index'])->name('index');
        Route::get('/create', [JobOrderController::class, 'create'])->name('create');
        Route::post('/', [JobOrderController::class, 'store'])->name('store');
        Route::get('/{id}', [JobOrderController::class, 'show'])->name('show');
        Route::get('/{id}/edit', [JobOrderController::class, 'edit'])->name('edit');
        Route::put('/{id}', [JobOrderController::class, 'update'])->name('update');
        Route::delete('/{id}', [JobOrderController::class, 'destroy'])->name('destroy');
    });

    // Interview Management
    Route::prefix('interviews')->name('interviews.')->group(function () {
        Route::get('/', [InterviewController::class, 'index'])->name('index');
        Route::get('/create', [InterviewController::class, 'create'])->name('create');
        Route::post('/', [InterviewController::class, 'store'])->name('store');
        Route::get('/{id}', [InterviewController::class, 'show'])->name('show');
        Route::get('/{id}/edit', [InterviewController::class, 'edit'])->name('edit');
        Route::put('/{id}', [InterviewController::class, 'update'])->name('update');
        Route::delete('/{id}', [InterviewController::class, 'destroy'])->name('destroy');
    });

    // Matching Management
    Route::prefix('matchings')->name('matchings.')->group(function () {
        Route::get('/', [MatchingController::class, 'index'])->name('index');
        Route::get('/create', [MatchingController::class, 'create'])->name('create');
        Route::post('/', [MatchingController::class, 'store'])->name('store');
        Route::get('/{id}', [MatchingController::class, 'show'])->name('show');
        Route::get('/{id}/edit', [MatchingController::class, 'edit'])->name('edit');
        Route::put('/{id}', [MatchingController::class, 'update'])->name('update');
        Route::delete('/{id}', [MatchingController::class, 'destroy'])->name('destroy');
    });

    // Application Management
    Route::prefix('applications')->name('applications.')->group(function () {
        Route::get('/', [ApplicationController::class, 'index'])->name('index');
        Route::get('/create', [ApplicationController::class, 'create'])->name('create');
        Route::post('/', [ApplicationController::class, 'store'])->name('store');
        Route::get('/{id}', [ApplicationController::class, 'show'])->name('show');
        Route::get('/{id}/edit', [ApplicationController::class, 'edit'])->name('edit');
        Route::put('/{id}', [ApplicationController::class, 'update'])->name('update');
        Route::delete('/{id}', [ApplicationController::class, 'destroy'])->name('destroy');
    });

    // Document Management
    Route::prefix('documents')->name('documents.')->group(function () {
        Route::get('/', [DocumentController::class, 'index'])->name('index');
        Route::get('/create', [DocumentController::class, 'create'])->name('create');
        Route::post('/', [DocumentController::class, 'store'])->name('store');
        Route::get('/{id}', [DocumentController::class, 'show'])->name('show');
        Route::get('/{id}/edit', [DocumentController::class, 'edit'])->name('edit');
        Route::put('/{id}', [DocumentController::class, 'update'])->name('update');
        Route::delete('/{id}', [DocumentController::class, 'destroy'])->name('destroy');
    });

    // COE Management
    Route::prefix('coes')->name('coes.')->group(function () {
        Route::get('/', [CoeController::class, 'index'])->name('index');
        Route::get('/create', [CoeController::class, 'create'])->name('create');
        Route::post('/', [CoeController::class, 'store'])->name('store');
        Route::get('/{id}', [CoeController::class, 'show'])->name('show');
        Route::get('/{id}/edit', [CoeController::class, 'edit'])->name('edit');
        Route::put('/{id}', [CoeController::class, 'update'])->name('update');
        Route::delete('/{id}', [CoeController::class, 'destroy'])->name('destroy');
    });

    // Visa Management
    Route::prefix('visas')->name('visas.')->group(function () {
        Route::get('/', [VisaController::class, 'index'])->name('index');
        Route::get('/create', [VisaController::class, 'create'])->name('create');
        Route::post('/', [VisaController::class, 'store'])->name('store');
        Route::get('/{id}', [VisaController::class, 'show'])->name('show');
        Route::get('/{id}/edit', [VisaController::class, 'edit'])->name('edit');
        Route::put('/{id}', [VisaController::class, 'update'])->name('update');
        Route::delete('/{id}', [VisaController::class, 'destroy'])->name('destroy');
    });

    // Module Management
    Route::prefix('modules')->name('modules.')->group(function () {
        Route::get('/', [ModuleController::class, 'index'])->name('index');
        Route::get('/create', [ModuleController::class, 'create'])->name('create');
        Route::post('/', [ModuleController::class, 'store'])->name('store');
        Route::get('/{id}', [ModuleController::class, 'show'])->name('show');
        Route::get('/{id}/edit', [ModuleController::class, 'edit'])->name('edit');
        Route::put('/{id}', [ModuleController::class, 'update'])->name('update');
        Route::delete('/{id}', [ModuleController::class, 'destroy'])->name('destroy');
    });

    // Permission Management
    Route::prefix('permissions')->name('permissions.')->group(function () {
        Route::get('/', [PermissionController::class, 'index'])->name('index');
        Route::get('/create', [PermissionController::class, 'create'])->name('create');
        Route::post('/', [PermissionController::class, 'store'])->name('store');
        Route::get('/{id}', [PermissionController::class, 'show'])->name('show');
        Route::get('/{id}/edit', [PermissionController::class, 'edit'])->name('edit');
        Route::put('/{id}', [PermissionController::class, 'update'])->name('update');
        Route::delete('/{id}', [PermissionController::class, 'destroy'])->name('destroy');
    });
});
